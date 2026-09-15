<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Dto\VacancyListDto;
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

	public function getList(VacancyFilterDto $filter, string $baseUrl = '/vacancies/'): VacancyListDto
	{
		$filter = $filter->withFavoriteIds($this->favorites->getFavoriteIds());

		// Баг №1: при sort=views выборка по дате, затем usort по просмотрам текущей страницы
		$sortByViews = $filter->sort === 'views';
		$sort = match ($filter->sort)
		{
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

		if ($sortByViews && count($items) > 1)
		{
			usort(
				$items,
				static function (VacancyDto $a, VacancyDto $b): int {
					if ($a->views === $b->views)
					{
						return $b->id <=> $a->id;
					}

					return $b->views <=> $a->views;
				}
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
	public function getDetail(int $id, string $code, bool $isPost, string $baseUrl = '/vacancies/'): ?VacancyDto
	{
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
					$sectionUrl = $this->buildSectionUrl($baseUrl, $sectionId);
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
	public function getRelated(VacancyDto $vacancy, int $limit, string $baseUrl = '/vacancies/'): array
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
				'url' => $sectionId > 0 ? $this->buildSectionUrl($baseUrl, $sectionId) : '',
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
				'salaryText' => $this->formatSalary($salaryFrom, $salaryTo),
				'city' => (string)($row['CITY'] ?? ''),
				'cityId' => (int)($row['CITY_ID'] ?? 0),
				'experience' => (string)($row['EXPERIENCE'] ?? ''),
				'experienceId' => (int)($row['EXPERIENCE_ID'] ?? 0),
				'isHot' => (bool)($row['HOT'] ?? false),
				'isNew' => $this->isNew($activeFrom),
				'tags' => is_array($row['TAGS'] ?? null) ? $row['TAGS'] : [],
				'dateFormatted' => $this->formatDate($activeFrom),
				'dateText' => $this->daysAgo($activeFrom),
				'activeFrom' => $activeFrom,
				'views' => $views,
				'responseCount' => $responseCount,
				'weekResponseCount' => $weekResponseCount,
				'isFavorite' => $isFavorite,
				'url' => $this->vacancyUrl($row, $baseUrl),
				'sectionName' => $sectionName,
				'sectionUrl' => $sectionUrl,
				'sectionId' => (int)($row['IBLOCK_SECTION_ID'] ?? 0),
				'contactEmail' => (string)($row['CONTACT_EMAIL'] ?? ''),
			],
		);
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

	/**
	 * @param array<string, mixed> $item
	 */
	private function vacancyUrl(array $item, string $baseUrl): string
	{
		$code = (string)($item['CODE'] ?? '');
		if ($code !== '')
		{
			return $baseUrl . '?CODE=' . rawurlencode($code);
		}

		return $baseUrl . '?ID=' . (int)($item['ID'] ?? 0);
	}

	private function buildSectionUrl(string $baseUrl, int $sectionId): string
	{
		return $baseUrl . '?section=' . $sectionId;
	}

	private function formatSalary(int $from, int $to, string $currency = '₽'): string
	{
		if ($from <= 0 && $to <= 0)
		{
			return 'по договорённости';
		}

		if ($from > 0 && $to > 0)
		{
			if ($from === $to)
			{
				return $this->formatNumber($from) . ' ' . $currency;
			}

			return 'от ' . $this->formatNumber($from) . ' до ' . $this->formatNumber($to) . ' ' . $currency;
		}

		if ($from > 0)
		{
			return 'от ' . $this->formatNumber($from) . ' ' . $currency;
		}

		return 'до ' . $this->formatNumber($to) . ' ' . $currency;
	}

	private function formatNumber(int $n): string
	{
		return number_format($n, 0, '.', ' ');
	}

	private function formatDate(string $dateString): string
	{
		if ($dateString === '')
		{
			return '';
		}

		$ts = MakeTimeStamp($dateString);

		return $ts > 0 ? FormatDate('d.m.Y', $ts) : '';
	}

	private function daysAgo(string $dateString): string
	{
		if ($dateString === '')
		{
			return '';
		}

		$ts = MakeTimeStamp($dateString);
		if ($ts <= 0)
		{
			return $dateString;
		}

		$today = mktime(0, 0, 0);
		$day = mktime(0, 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
		$diff = (int)(($today - $day) / 86400);

		if ($diff <= 0)
		{
			return 'сегодня';
		}
		if ($diff === 1)
		{
			return 'вчера';
		}
		if ($diff < 30)
		{
			return $diff . ' ' . $this->plural($diff, 'день', 'дня', 'дней') . ' назад';
		}

		return date('d.m.Y', $ts);
	}

	private function plural(int $n, string $one, string $two, string $five): string
	{
		$n = abs($n) % 100;
		$n1 = $n % 10;
		if ($n > 10 && $n < 20)
		{
			return $five;
		}
		if ($n1 > 1 && $n1 < 5)
		{
			return $two;
		}
		if ($n1 === 1)
		{
			return $one;
		}

		return $five;
	}
}
