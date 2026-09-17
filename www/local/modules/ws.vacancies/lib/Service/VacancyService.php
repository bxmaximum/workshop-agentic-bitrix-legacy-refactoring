<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Dto\VacancyListDto;
use Ws\Vacancies\Dto\VacancyStatsDto;
use Ws\Vacancies\Helper\Formatter;
use Ws\Vacancies\Helper\UrlBuilder;
use Ws\Vacancies\Repository\VacancyRepository;
use Ws\Vacancies\Repository\VacancyResponseRepository;
use Ws\Vacancies\Repository\VacancyStatRepository;

final class VacancyService
{
	public function __construct(
		private readonly VacancyRepository $vacancies,
		private readonly VacancyStatRepository $stats,
		private readonly VacancyResponseRepository $responses,
		private readonly FavoriteService $favorites,
	) {
	}

	public function getList(VacancyFilterDto $filter, string $baseUrl = UrlBuilder::DEFAULT_BASE_URL): VacancyListDto
	{
		$filter = $filter->withFavoriteIds($this->favorites->getFavoriteIds());

		$sort = match ($filter->sort)
		{
			'views' => ['STAT.VIEWS' => 'DESC', 'ID' => 'DESC'],
			'salary' => ['PROPERTY_SALARY_FROM' => 'DESC,NULLS', 'ID' => 'DESC'],
			'name' => ['NAME' => 'ASC', 'ID' => 'DESC'],
			default => ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
		};

		$result = $this->vacancies->findList($filter, $sort);
		$total = $result['total'];
		$pageSize = max(1, $filter->pageSize);
		$totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 1;
		$currentPage = min(max(1, $filter->page), $totalPages);

		$ids = array_map(static fn(array $row): int => (int)$row['ID'], $result['rows']);
		$viewsMap = $this->stats->getViewsMap($ids);
		$responseMap = $this->responses->getValidCountMap($ids);
		$sectionNames = $this->loadSectionNames($result['rows'], $baseUrl);

		$items = [];
		foreach ($result['rows'] as $row)
		{
			$id = (int)$row['ID'];
			$items[] = $this->mapToDto(
				$row,
				$baseUrl,
				views: $viewsMap[$id] ?? 0,
				responseCount: $responseMap[$id] ?? 0,
				sectionName: $sectionNames[$id]['name'] ?? '',
				sectionUrl: $sectionNames[$id]['url'] ?? '',
				isFavorite: $this->favorites->isFavorite($id),
			);
		}

		return new VacancyListDto(
			items: $items,
			totalCount: $total,
			totalPages: $totalPages,
			currentPage: $currentPage,
			pageSize: $pageSize,
		);
	}

	/**
	 * Приоритет ID над CODE (баг №5). Инкремент просмотров на GET (баг №13).
	 */
	public function getDetail(
		int $id,
		string $code,
		bool $isPost,
		string $baseUrl = UrlBuilder::DEFAULT_BASE_URL,
	): ?VacancyDto {
		$row = null;
		if ($id > 0)
		{
			$row = $this->vacancies->getById($id);
		}
		elseif ($code !== '')
		{
			$row = $this->vacancies->getByCode($code);
		}

		if ($row === null)
		{
			return null;
		}

		$vacancyId = (int)$row['ID'];

		if (!$isPost)
		{
			$this->stats->incrementViews($vacancyId);
		}

		$views = $this->stats->getViews($vacancyId);
		$responseCount = $this->responses->getValidCountByVacancyId($vacancyId);
		$weekCount = $this->responses->getWeekCountIncludingSpam($vacancyId);

		$sectionName = '';
		$sectionUrl = '';
		$sectionId = (int)$row['IBLOCK_SECTION_ID'];
		if ($sectionId > 0)
		{
			$sections = $this->vacancies->getSectionsWithCounts($this->vacancies->getIblockId());
			foreach ($sections as $section)
			{
				if ((int)$section['ID'] === $sectionId)
				{
					$sectionName = (string)$section['NAME'];
					$sectionUrl = UrlBuilder::section($sectionId, $baseUrl);
					break;
				}
			}

			if ($sectionName === '')
			{
				// неактивный раздел — название всё равно показываем
				$sectionName = $this->vacancies->getSectionName($sectionId);
			}
		}

		return $this->mapToDto(
			$row,
			$baseUrl,
			views: $views,
			responseCount: $responseCount,
			sectionName: $sectionName,
			sectionUrl: $sectionUrl,
			isFavorite: $this->favorites->isFavorite($vacancyId),
			weekResponseCount: $weekCount,
		);
	}

	/**
	 * @return list<VacancyDto>
	 */
	public function getRelated(VacancyDto $vacancy, int $limit, string $baseUrl = UrlBuilder::DEFAULT_BASE_URL): array
	{
		$rows = $this->vacancies->getRelated(
			$vacancy->id,
			$vacancy->sectionId,
			$vacancy->cityId,
			$limit,
		);

		$ids = array_map(static fn(array $row): int => (int)$row['ID'], $rows);
		$viewsMap = $this->stats->getViewsMap($ids);

		$items = [];
		foreach ($rows as $row)
		{
			$id = (int)$row['ID'];
			$items[] = $this->mapToDto(
				$row,
				$baseUrl,
				views: $viewsMap[$id] ?? 0,
				responseCount: 0,
				sectionName: '',
				sectionUrl: '',
				isFavorite: false,
			);
		}

		return $items;
	}

	/**
	 * Счётчики вакансии для AJAX (просмотры и отклики без SPAM), без инкремента.
	 */
	public function getStats(int $vacancyId): VacancyStatsDto
	{
		return new VacancyStatsDto(
			vacancyId: $vacancyId,
			views: $this->stats->getViews($vacancyId),
			responseCount: $this->responses->getValidCountByVacancyId($vacancyId),
		);
	}

	public function getIblockId(): int
	{
		return $this->vacancies->getIblockId();
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<int, array{name: string, url: string}>
	 */
	private function loadSectionNames(array $rows, string $baseUrl): array
	{
		$cache = [];
		$result = [];

		$sections = $this->vacancies->getSectionsWithCounts($this->vacancies->getIblockId());
		foreach ($sections as $section)
		{
			$cache[(int)$section['ID']] = (string)$section['NAME'];
		}

		foreach ($rows as $row)
		{
			$id = (int)$row['ID'];
			$sectionId = (int)$row['IBLOCK_SECTION_ID'];
			$result[$id] = [
				'name' => $cache[$sectionId] ?? '',
				'url' => $sectionId > 0 ? UrlBuilder::section($sectionId, $baseUrl) : '',
			];
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function mapToDto(
		array $row,
		string $baseUrl,
		int $views,
		int $responseCount,
		string $sectionName,
		string $sectionUrl,
		bool $isFavorite,
		int $weekResponseCount = 0,
	): VacancyDto {
		$salaryFrom = (int)($row['SALARY_FROM'] ?? 0);
		$salaryTo = (int)($row['SALARY_TO'] ?? 0);
		$activeFrom = (string)($row['ACTIVE_FROM'] ?? '');

		return VacancyDto::fromRow(
			$row,
			[],
			[
				'salaryFrom' => $salaryFrom,
				'salaryTo' => $salaryTo,
				'salaryText' => Formatter::salary($salaryFrom, $salaryTo),
				'city' => (string)($row['CITY'] ?? ''),
				'cityId' => (int)($row['CITY_ID'] ?? 0),
				'experience' => (string)($row['EXPERIENCE'] ?? ''),
				'experienceId' => (int)($row['EXPERIENCE_ID'] ?? 0),
				'isHot' => (bool)($row['HOT'] ?? false),
				'isNew' => $this->isNew($activeFrom),
				'tags' => $this->normalizeTags(is_array($row['TAGS'] ?? null) ? $row['TAGS'] : []),
				'dateFormatted' => Formatter::date($activeFrom),
				'dateText' => Formatter::daysAgo($activeFrom),
				'activeFrom' => $activeFrom,
				'views' => $views,
				'responseCount' => $responseCount,
				'weekResponseCount' => $weekResponseCount,
				'isFavorite' => $isFavorite,
				'url' => UrlBuilder::vacancy((string)($row['CODE'] ?? ''), (int)($row['ID'] ?? 0), $baseUrl),
				'sectionName' => $sectionName,
				'sectionUrl' => $sectionUrl,
				'sectionId' => (int)($row['IBLOCK_SECTION_ID'] ?? 0),
				'contactEmail' => (string)($row['CONTACT_EMAIL'] ?? ''),
			],
		);
	}

	/**
	 * Теги без пустых и дублей, по алфавиту без учёта регистра.
	 *
	 * @param list<mixed> $tags
	 * @return list<string>
	 */
	private function normalizeTags(array $tags): array
	{
		$tags = array_map(static fn($tag): string => trim((string)$tag), $tags);
		$tags = array_values(array_unique(array_filter($tags, static fn(string $tag): bool => $tag !== '')));
		usort($tags, static fn(string $a, string $b): int => strcasecmp($a, $b));

		return $tags;
	}

	/**
	 * Баг №18: (time() - ts) < 3 * 86400 → возраст <= 2 дней.
	 */
	private function isNew(string $activeFrom): bool
	{
		if ($activeFrom === '')
		{
			return false;
		}

		$ts = MakeTimeStamp($activeFrom);
		if ($ts <= 0)
		{
			return false;
		}

		return (time() - $ts) < 3 * 86400;
	}
}
