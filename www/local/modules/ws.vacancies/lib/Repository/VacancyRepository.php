<?php

declare(strict_types=1);

namespace Ws\Vacancies\Repository;

use Bitrix\Iblock\Elements\ElementVacancyTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Ws\Vacancies\Dto\VacancyFilterDto;

final class VacancyRepository
{
	private const IBLOCK_CODE = 'VACANCIES';
	private const IBLOCK_TYPE = 'legacy';
	private const API_CODE = 'Vacancy';

	private ?int $iblockId = null;
	private ?int $tagsPropertyId = null;

	public function __construct()
	{
		Loader::includeModule('iblock');
		IblockTable::compileEntity(self::API_CODE);
	}

	public function getIblockId(): int
	{
		if ($this->iblockId !== null)
		{
			return $this->iblockId;
		}

		$row = IblockTable::query()
			->setSelect(['ID'])
			->where('CODE', self::IBLOCK_CODE)
			->where('IBLOCK_TYPE_ID', self::IBLOCK_TYPE)
			->setLimit(1)
			->fetch();

		if (!$row)
		{
			$row = IblockTable::query()
				->setSelect(['ID'])
				->where('CODE', self::IBLOCK_CODE)
				->setLimit(1)
				->fetch();
		}

		$this->iblockId = $row ? (int)$row['ID'] : 0;

		return $this->iblockId;
	}

	public function existsActive(int $vacancyId): bool
	{
		if ($vacancyId <= 0)
		{
			return false;
		}

		$row = $this->baseActiveQuery()
			->setSelect(['ID'])
			->where('ID', $vacancyId)
			->setLimit(1)
			->fetch();

		return $row !== false;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getById(int $id): ?array
	{
		if ($id <= 0)
		{
			return null;
		}

		$object = $this->baseActiveQuery()
			->setSelect($this->elementSelect())
			->where('ID', $id)
			->setLimit(1)
			->fetchObject();

		return $object ? $this->mapElementObject($object) : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getByCode(string $code): ?array
	{
		$code = preg_replace('/[^a-z0-9\-_]/i', '', $code) ?? '';
		if ($code === '')
		{
			return null;
		}

		$object = $this->baseActiveQuery()
			->setSelect($this->elementSelect())
			->where('CODE', $code)
			->setLimit(1)
			->fetchObject();

		return $object ? $this->mapElementObject($object) : null;
	}

	/**
	 * @param array<string, string> $sort
	 * @return array{rows: list<array<string, mixed>>, total: int}
	 */
	public function findList(VacancyFilterDto $filter, array $sort): array
	{
		$totalQuery = $this->buildFilteredQuery($filter);
		$totalRow = $totalQuery
			->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
			->fetch();
		$total = $totalRow ? (int)$totalRow['CNT'] : 0;

		$pageSize = max(1, $filter->pageSize);
		$totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 1;
		$page = min(max(1, $filter->page), $totalPages);
		$offset = ($page - 1) * $pageSize;

		$listQuery = $this->buildFilteredQuery($filter)
			->setSelect($this->elementSelect())
			->setOrder($this->normalizeSort($sort))
			->setLimit($pageSize)
			->setOffset($offset);

		$collection = $listQuery->fetchCollection();
		$rows = [];
		foreach ($collection as $object)
		{
			$rows[] = $this->mapElementObject($object);
		}

		return [
			'rows' => $rows,
			'total' => $total,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getRelated(int $vacancyId, int $sectionId, int $cityId, int $limit): array
	{
		$result = [];
		$excludeIds = [$vacancyId];

		if ($sectionId > 0 && $limit > 0)
		{
			$collection = $this->baseActiveQuery()
				->setSelect($this->elementSelect())
				->where('IBLOCK_SECTION_ID', $sectionId)
				->whereNot('ID', $vacancyId)
				->setOrder(['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'])
				->setLimit($limit)
				->fetchCollection();

			foreach ($collection as $object)
			{
				$row = $this->mapElementObject($object);
				$result[] = $row;
				$excludeIds[] = (int)$row['ID'];
			}
		}

		$need = $limit - count($result);
		if ($need > 0 && $cityId > 0)
		{
			$query = $this->baseActiveQuery()
				->setSelect($this->elementSelect())
				->where('CITY.VALUE', $cityId)
				->whereNotIn('ID', $excludeIds)
				->setOrder(['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'])
				->setLimit($need);

			foreach ($query->fetchCollection() as $object)
			{
				$result[] = $this->mapElementObject($object);
			}
		}

		return $result;
	}

	/**
	 * @return list<array{ID: int, NAME: string, CODE: string, COUNT: int}>
	 */
	public function getSectionsWithCounts(int $iblockId): array
	{
		if ($iblockId <= 0)
		{
			$iblockId = $this->getIblockId();
		}

		$sections = SectionTable::query()
			->setSelect(['ID', 'NAME', 'CODE', 'SORT'])
			->where('IBLOCK_ID', $iblockId)
			->where('ACTIVE', 'Y')
			->where('GLOBAL_ACTIVE', 'Y')
			->setOrder(['SORT' => 'ASC', 'NAME' => 'ASC'])
			->fetchAll();

		$result = [];
		foreach ($sections as $section)
		{
			$sectionId = (int)$section['ID'];
			$countRow = $this->baseActiveQuery()
				->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
				->where('IBLOCK_SECTION_ID', $sectionId)
				->fetch();

			$result[] = [
				'ID' => $sectionId,
				'NAME' => (string)$section['NAME'],
				'CODE' => (string)($section['CODE'] ?? ''),
				'COUNT' => $countRow ? (int)$countRow['CNT'] : 0,
			];
		}

		return $result;
	}

	/**
	 * @return array<int, string> enumId => value
	 */
	public function getCities(int $iblockId): array
	{
		return $this->getEnumMap($iblockId, 'CITY');
	}

	/**
	 * @return array<int, string> enumId => value
	 */
	public function getExperienceList(int $iblockId): array
	{
		return $this->getEnumMap($iblockId, 'EXPERIENCE');
	}

	/**
	 * Множественные строковые свойства инфоблока v2 лежат в b_iblock_element_prop_m{IBLOCK_ID}.
	 *
	 * @return list<string>
	 */
	public function getTags(int $elementId): array
	{
		$propertyId = $this->getTagsPropertyId();
		$iblockId = $this->getIblockId();
		if ($propertyId <= 0 || $elementId <= 0 || $iblockId <= 0)
		{
			return [];
		}

		$helper = Application::getConnection()->getSqlHelper();
		$table = 'b_iblock_element_prop_m' . $iblockId;
		$sql = 'SELECT VALUE FROM ' . $helper->quote($table)
			. ' WHERE IBLOCK_ELEMENT_ID = ' . (int)$elementId
			. ' AND IBLOCK_PROPERTY_ID = ' . (int)$propertyId
			. ' ORDER BY ID ASC';

		$tags = [];
		$rs = Application::getConnection()->query($sql);
		while ($row = $rs->fetch())
		{
			$tag = trim((string)$row['VALUE']);
			if ($tag !== '')
			{
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	public function getSectionName(int $sectionId): string
	{
		if ($sectionId <= 0)
		{
			return '';
		}

		$row = SectionTable::query()
			->setSelect(['NAME'])
			->where('ID', $sectionId)
			->setLimit(1)
			->fetch();

		return $row ? (string)$row['NAME'] : '';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getRawById(int $id): ?array
	{
		if ($id <= 0)
		{
			return null;
		}

		$object = ElementVacancyTable::query()
			->setSelect($this->elementSelect())
			->where('ID', $id)
			->setLimit(1)
			->fetchObject();

		return $object ? $this->mapElementObject($object, false) : null;
	}

	private function baseActiveQuery(): Query
	{
		$now = new DateTime();

		return ElementVacancyTable::query()
			->where('IBLOCK_ID', $this->getIblockId())
			->where('ACTIVE', true)
			->where(
				Query::filter()
					->logic('or')
					->whereNull('ACTIVE_FROM')
					->where('ACTIVE_FROM', '<=', $now)
			)
			->where(
				Query::filter()
					->logic('or')
					->whereNull('ACTIVE_TO')
					->where('ACTIVE_TO', '>=', $now)
			);
	}

	private function buildFilteredQuery(VacancyFilterDto $filter): Query
	{
		$query = $this->baseActiveQuery();

		if ($filter->city > 0)
		{
			$query->where('CITY.VALUE', $filter->city);
		}

		if ($filter->section > 0)
		{
			// INCLUDE_SUBSECTIONS: для плоского дерева достаточно IBLOCK_SECTION_ID
			$query->where('IBLOCK_SECTION_ID', $filter->section);
		}

		if ($filter->exp > 0)
		{
			$query->where('EXPERIENCE.VALUE', $filter->exp);
		}

		// Баг №8: только SALARY_FROM >= salary, SALARY_TO игнорируется
		if ($filter->salary > 0)
		{
			$query->where('SALARY_FROM.VALUE', '>=', $filter->salary);
		}

		if ($filter->hot)
		{
			$query->whereNotNull('HOT.VALUE');
			$query->where('HOT.VALUE', '>', 0);
		}

		if ($filter->fav)
		{
			$favIds = $filter->favoriteIds !== [] ? $filter->favoriteIds : [0];
			$query->whereIn('ID', $favIds);
		}

		// Баг №9: q по NAME / PREVIEW_TEXT / PROPERTY_TAGS
		if ($filter->q !== '')
		{
			$q = $filter->q;
			$orFilter = Query::filter()
				->logic('or')
				->whereLike('NAME', '%' . $q . '%')
				->whereLike('PREVIEW_TEXT', '%' . $q . '%');

			$tagIds = $this->findElementIdsByTagLike($q);
			if ($tagIds !== [])
			{
				$orFilter->whereIn('ID', $tagIds);
			}
			else
			{
				// если тегов нет — оставляем невозможное условие, чтобы OR не «проглатывал» пустой whereIn
				$orFilter->whereLike('NAME', '%' . $q . '%');
			}

			$query->where($orFilter);
		}

		return $query;
	}

	/**
	 * @return list<int>
	 */
	private function findElementIdsByTagLike(string $q): array
	{
		$propertyId = $this->getTagsPropertyId();
		$iblockId = $this->getIblockId();
		if ($propertyId <= 0 || $iblockId <= 0 || $q === '')
		{
			return [];
		}

		$helper = Application::getConnection()->getSqlHelper();
		$table = 'b_iblock_element_prop_m' . $iblockId;
		$sql = 'SELECT DISTINCT IBLOCK_ELEMENT_ID FROM ' . $helper->quote($table)
			. ' WHERE IBLOCK_PROPERTY_ID = ' . (int)$propertyId
			. " AND VALUE LIKE '%" . $helper->forSql($q) . "%'";

		$ids = [];
		$rs = Application::getConnection()->query($sql);
		while ($row = $rs->fetch())
		{
			$ids[] = (int)$row['IBLOCK_ELEMENT_ID'];
		}

		return array_values(array_unique($ids));
	}

	private function getTagsPropertyId(): int
	{
		if ($this->tagsPropertyId !== null)
		{
			return $this->tagsPropertyId;
		}

		$row = PropertyTable::query()
			->setSelect(['ID'])
			->where('IBLOCK_ID', $this->getIblockId())
			->where('CODE', 'TAGS')
			->setLimit(1)
			->fetch();

		$this->tagsPropertyId = $row ? (int)$row['ID'] : 0;

		return $this->tagsPropertyId;
	}

	/**
	 * Значения списочного свойства инфоблока.
	 *
	 * @return array<int, array{ID: int, VALUE: string, XML_ID: string, SORT: int}>
	 */
	public function getEnumList(int $iblockId, string $propertyCode): array
	{
		if ($iblockId <= 0)
		{
			$iblockId = $this->getIblockId();
		}

		$property = PropertyTable::query()
			->setSelect(['ID'])
			->where('IBLOCK_ID', $iblockId)
			->where('CODE', $propertyCode)
			->setLimit(1)
			->fetch();

		if (!$property)
		{
			return [];
		}

		$rows = PropertyEnumerationTable::query()
			->setSelect(['ID', 'VALUE', 'XML_ID', 'SORT'])
			->where('PROPERTY_ID', (int)$property['ID'])
			->setOrder(['SORT' => 'ASC', 'VALUE' => 'ASC'])
			->fetchAll();

		$result = [];
		foreach ($rows as $row)
		{
			$id = (int)$row['ID'];
			$result[$id] = [
				'ID' => $id,
				'VALUE' => (string)$row['VALUE'],
				'XML_ID' => (string)($row['XML_ID'] ?? ''),
				'SORT' => (int)$row['SORT'],
			];
		}

		return $result;
	}

	/**
	 * @return array<int, string>
	 */
	private function getEnumMap(int $iblockId, string $propertyCode): array
	{
		return array_map(
			static fn(array $row): string => $row['VALUE'],
			$this->getEnumList($iblockId, $propertyCode),
		);
	}

	/**
	 * @return list<string>
	 */
	private function elementSelect(): array
	{
		return [
			'ID',
			'IBLOCK_ID',
			'NAME',
			'CODE',
			'IBLOCK_SECTION_ID',
			'PREVIEW_TEXT',
			'PREVIEW_TEXT_TYPE',
			'DETAIL_TEXT',
			'DETAIL_TEXT_TYPE',
			'ACTIVE',
			'ACTIVE_FROM',
			'DATE_CREATE',
			'CITY',
			'CITY.ITEM',
			'SALARY_FROM',
			'SALARY_TO',
			'EXPERIENCE',
			'EXPERIENCE.ITEM',
			'HOT',
			'HOT.ITEM',
			'CONTACT_EMAIL',
		];
	}

	/**
	 * @param array<string, string> $sort
	 * @return array<string, string>
	 */
	private function normalizeSort(array $sort): array
	{
		$result = [];
		foreach ($sort as $field => $direction)
		{
			$dir = strtoupper(explode(',', (string)$direction)[0]);
			$dir = $dir === 'ASC' ? 'ASC' : 'DESC';

			$mappedField = match ($field)
			{
				'PROPERTY_SALARY_FROM', 'SALARY_FROM' => 'SALARY_FROM.VALUE',
				default => $field,
			};
			$result[$mappedField] = $dir;
		}

		if ($result === [])
		{
			$result = ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'];
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mapElementObject(object $object, bool $loadTags = true): array
	{
		$activeFrom = $object->getActiveFrom();
		$activeFromString = $activeFrom instanceof DateTime ? $activeFrom->toString() : (string)$activeFrom;

		$cityProp = $object->getCity();
		$expProp = $object->getExperience();
		$hotProp = $object->getHot();
		$salaryFromProp = $object->getSalaryFrom();
		$salaryToProp = $object->getSalaryTo();
		$contactProp = $object->getContactEmail();

		$id = (int)$object->getId();

		return [
			'ID' => $id,
			'IBLOCK_ID' => (int)$object->getIblockId(),
			'NAME' => (string)$object->getName(),
			'CODE' => (string)$object->getCode(),
			'IBLOCK_SECTION_ID' => (int)$object->getIblockSectionId(),
			'PREVIEW_TEXT' => (string)$object->getPreviewText(),
			'PREVIEW_TEXT_TYPE' => (string)$object->getPreviewTextType(),
			'DETAIL_TEXT' => (string)$object->getDetailText(),
			'DETAIL_TEXT_TYPE' => (string)$object->getDetailTextType(),
			'ACTIVE' => $object->getActive() ? 'Y' : 'N',
			'ACTIVE_FROM' => $activeFromString,
			'DATE_CREATE' => $object->getDateCreate() instanceof DateTime
				? $object->getDateCreate()->toString()
				: (string)$object->getDateCreate(),
			'CITY' => (string)($cityProp?->getItem()?->getValue() ?? ''),
			'CITY_ID' => (int)($cityProp?->getValue() ?? 0),
			'EXPERIENCE' => (string)($expProp?->getItem()?->getValue() ?? ''),
			'EXPERIENCE_ID' => (int)($expProp?->getValue() ?? 0),
			'SALARY_FROM' => (int)($salaryFromProp?->getValue() ?? 0),
			'SALARY_TO' => (int)($salaryToProp?->getValue() ?? 0),
			'HOT' => $hotProp !== null && (string)$hotProp->getValue() !== '' && (int)$hotProp->getValue() > 0,
			'CONTACT_EMAIL' => (string)($contactProp?->getValue() ?? ''),
			'TAGS' => $loadTags ? $this->getTags($id) : [],
		];
	}
}
