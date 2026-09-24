<?php

declare(strict_types=1);

namespace Workshop\Mcp;

use Mcp\Exception\ToolCallException;

/**
 * Ядро Битрикса внутри процесса MCP-сервера.
 *
 * Поднимается один раз, при первом вызове тула, теми же флагами, что bootBitrix() в tests/Pest.php.
 * Лениво — чтобы сервер стартовал и отдавал список тулов, даже если БД стенда сейчас не запущена.
 */
final class Kernel
{
	private static bool $booted = false;

	public static function boot(): void
	{
		if (self::$booted)
		{
			return;
		}

		$root = realpath(getenv('BITRIX_DOCUMENT_ROOT') ?: dirname(__DIR__, 2) . '/www');
		if ($root === false || !is_file($root . '/bitrix/modules/main/include/prolog_before.php'))
		{
			throw new ToolCallException(
				'Не найдено ядро Битрикса: ожидается www/bitrix рядом с mcp/ или путь к корню сайта в BITRIX_DOCUMENT_ROOT.'
			);
		}

		$_SERVER['DOCUMENT_ROOT'] = $root;

		foreach (['NO_KEEP_STATISTIC', 'NOT_CHECK_PERMISSIONS', 'BX_NO_ACCELERATOR_RESET', 'BX_CRONTAB', 'BX_WITH_ON_AFTER_EPILOG'] as $flag)
		{
			if (!defined($flag))
			{
				define($flag, true);
			}
		}

		// Всё, что ядро напечатает при загрузке (init.php, предупреждения), уводим в STDERR.
		$level = ob_get_level();
		ob_start();
		try
		{
			require $root . '/bitrix/modules/main/include/prolog_before.php';
		}
		finally
		{
			while (ob_get_level() > $level)
			{
				$noise = (string)ob_get_clean();
				if (trim($noise) !== '')
				{
					fwrite(STDERR, '[bitrix] ' . $noise . PHP_EOL);
				}
			}
		}

		self::$booted = true;
	}
}
