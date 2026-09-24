<?php

declare(strict_types=1);

namespace Workshop\Mcp;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

/**
 * Что агент может спросить у стенда. Всё только читает.
 *
 * Описание тула для модели берётся из докблока, схема параметров — из типов PHP.
 * ToolCallException модель видит как текст ошибки и может поправить вызов сама.
 */
final class StandTools
{
	private const MAX_ROWS = 50;

	/**
	 * Настройки модуля Битрикса: значения из b_option и default_option.php. Секреты маскируются.
	 *
	 * @param string $moduleId ID модуля: main, iblock, ws.faq
	 */
	#[McpTool(name: 'module_options', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function moduleOptions(
		#[Schema(pattern: '^[a-z0-9_.]+$')]
		string $moduleId,
	): array
	{
		Kernel::boot();

		if (!ModuleManager::isModuleInstalled($moduleId))
		{
			throw new ToolCallException("Модуль {$moduleId} не установлен. Список модулей — ресурс bitrix://modules.");
		}

		return [
			'module' => $moduleId,
			'version' => ModuleManager::getVersion($moduleId) ?: null,
			'options' => $this->maskSecrets(Option::getForModule($moduleId)),
			'defaults' => $this->maskSecrets(Option::getDefaults($moduleId)),
		];
	}

	/**
	 * Инфоблок по символьному коду или ID: его поля, список свойств и первые элементы.
	 *
	 * @param string $iblock CODE или ID инфоблока, например VACANCIES
	 * @param string|null $elementCode символьный код элемента, если нужен один элемент
	 * @param int $limit сколько элементов вернуть, до 50
	 */
	#[McpTool(name: 'iblock_elements', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function iblockElements(string $iblock, ?string $elementCode = null, int $limit = 10): array
	{
		Kernel::boot();
		Loader::requireModule('iblock');

		$info = IblockTable::getList([
			'select' => ['ID', 'CODE', 'API_CODE', 'NAME', 'IBLOCK_TYPE_ID', 'ACTIVE'],
			'filter' => ctype_digit($iblock) ? ['=ID' => (int)$iblock] : ['=CODE' => $iblock],
			'limit' => 1,
		])->fetch();

		if (!$info)
		{
			throw new ToolCallException("Инфоблок {$iblock} не найден. Проверь CODE: SELECT ID, CODE, NAME FROM b_iblock.");
		}

		$iblockId = (int)$info['ID'];

		$filter = ['=IBLOCK_ID' => $iblockId];
		if ($elementCode !== null && $elementCode !== '')
		{
			$filter['=CODE'] = $elementCode;
		}

		$properties = PropertyTable::getList([
			'select' => ['ID', 'CODE', 'NAME', 'PROPERTY_TYPE', 'MULTIPLE'],
			'filter' => ['=IBLOCK_ID' => $iblockId],
			'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
		])->fetchAll();

		$elements = ElementTable::getList([
			'select' => ['ID', 'CODE', 'NAME', 'ACTIVE', 'IBLOCK_SECTION_ID', 'ACTIVE_FROM', 'SORT'],
			'filter' => $filter,
			'order' => ['ID' => 'ASC'],
			'limit' => $this->clamp($limit),
		])->fetchAll();

		return [
			'iblock' => $this->normalize($info),
			'elementsTotal' => ElementTable::getCount(['=IBLOCK_ID' => $iblockId]),
			'properties' => array_map($this->normalize(...), $properties),
			'elements' => array_map($this->normalize(...), $elements),
		];
	}

	/**
	 * Один SELECT к базе стенда, до 50 строк. Выполняется в транзакции READ ONLY с таймаутом 5 секунд.
	 *
	 * @param string $sql один SELECT, SHOW, DESCRIBE или EXPLAIN, например: SELECT STATUS, COUNT(*) FROM legacy_vacancy_response GROUP BY STATUS
	 * @param int $limit сколько строк вернуть, до 50
	 */
	#[McpTool(name: 'sql_select', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function sqlSelect(string $sql, int $limit = 20): array
	{
		$sql = SqlGuard::assertReadOnly($sql);
		Kernel::boot();

		$connection = Application::getConnection();
		try
		{
			$connection->queryExecute('SET SESSION MAX_EXECUTION_TIME = 5000'); // MySQL; в MariaDB переменной нет
		}
		catch (SqlQueryException)
		{
		}
		$connection->queryExecute('START TRANSACTION READ ONLY');

		try
		{
			$result = $connection->query($sql);
			$rows = [];
			while (count($rows) < $this->clamp($limit) && ($row = $result->fetch()))
			{
				$rows[] = $this->normalize($row);
			}

			return [
				'rows' => $rows,
				'truncated' => $result->getSelectedRowsCount() > count($rows),
			];
		}
		catch (SqlQueryException $e)
		{
			throw new ToolCallException('Ошибка SQL: ' . $e->getDatabaseMessage());
		}
		finally
		{
			$connection->queryExecute('ROLLBACK');
		}
	}

	/**
	 * Установленные модули стенда и их версии.
	 */
	#[McpResource(uri: 'bitrix://modules', name: 'modules', mimeType: 'application/json')]
	public function modules(): array
	{
		Kernel::boot();

		$modules = [];
		foreach (array_keys(ModuleManager::getInstalledModules()) as $id)
		{
			$modules[$id] = ModuleManager::getVersion($id) ?: null;
		}

		return $modules;
	}

	private function clamp(int $limit): int
	{
		return max(1, min(self::MAX_ROWS, $limit));
	}

	/**
	 * Значения из БД в JSON-пригодный вид: даты строкой, длинные тексты обрезаем.
	 */
	private function normalize(array $row): array
	{
		foreach ($row as $key => $value)
		{
			if (is_object($value))
			{
				$value = method_exists($value, '__toString') ? (string)$value : get_class($value);
			}
			if (is_string($value) && mb_strlen($value) > 500)
			{
				$value = mb_substr($value, 0, 500) . '…';
			}
			$row[$key] = $value;
		}

		return $row;
	}

	private function maskSecrets(array $options): array
	{
		foreach ($options as $name => $value)
		{
			if ($value !== '' && preg_match('/pass|secret|token|key|salt|hash/i', (string)$name))
			{
				$options[$name] = '***';
			}
		}

		return $options;
	}
}
