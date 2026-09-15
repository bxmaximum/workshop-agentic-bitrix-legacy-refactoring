<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Ws\Vacancies\Dto\SectionDto;
use Ws\Vacancies\Dto\SidebarDto;
use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Repository\VacancyRepository;
use Ws\Vacancies\Repository\VacancyResponseRepository;
use Ws\Vacancies\Repository\VacancyStatRepository;

final class SidebarService
{
	public function __construct(
		private readonly VacancyRepository $vacancies,
		private readonly VacancyStatRepository $stats,
		private readonly VacancyResponseRepository $responses,
		private readonly VacancyService $vacancyService,
	) {
	}

	public function getForList(
		int $currentSectionId,
		int $popularCount,
		string $baseUrl = '/vacancies/',
	): SidebarDto {
		$iblockId = $this->vacancies->getIblockId();
		$sections = [];
		foreach ($this->vacancies->getSectionsWithCounts($iblockId) as $section)
		{
			$id = (int)$section['ID'];
			$sections[] = new SectionDto(
				id: $id,
				name: (string)$section['NAME'],
				code: (string)$section['CODE'],
				count: (int)$section['COUNT'],
				url: $baseUrl . '?section=' . $id,
				selected: $id === $currentSectionId,
			);
		}

		$popular = $this->loadPopular($popularCount, null, $baseUrl);
		$weekSummary = $this->responses->getWeekSummary();

		return new SidebarDto(
			sections: $sections,
			popular: $popular,
			weekSummary: $weekSummary['total'] > 0 ? $weekSummary : null,
			related: [],
		);
	}

	/**
	 * На детальной: похожие + популярные без текущей; направления и сводка пусты (баг №16).
	 */
	public function getForDetail(
		VacancyDto $currentVacancy,
		int $popularCount,
		int $relatedCount,
		string $baseUrl = '/vacancies/',
	): SidebarDto {
		// +1 к лимиту, чтобы после исключения текущей набрать нужное число
		$popular = $this->loadPopular($popularCount + 1, $currentVacancy->id, $baseUrl);
		$popular = array_slice($popular, 0, $popularCount);

		$related = $this->vacancyService->getRelated($currentVacancy, $relatedCount, $baseUrl);

		return new SidebarDto(
			sections: [],
			popular: $popular,
			weekSummary: null,
			related: $related,
		);
	}

	/**
	 * @return list<VacancyDto>
	 */
	private function loadPopular(int $limit, ?int $excludeId, string $baseUrl): array
	{
		if ($limit <= 0)
		{
			return [];
		}

		$popularIds = $this->stats->getTopPopularIds($limit);
		$iblockId = $this->vacancies->getIblockId();
		$result = [];

		foreach ($popularIds as $popularId => $views)
		{
			if ($excludeId !== null && $popularId === $excludeId)
			{
				continue;
			}

			$row = $this->vacancies->getRawById($popularId);
			if ($row === null)
			{
				continue;
			}

			if (($row['ACTIVE'] ?? '') !== 'Y' || (int)($row['IBLOCK_ID'] ?? 0) !== $iblockId)
			{
				continue;
			}

			$code = (string)($row['CODE'] ?? '');
			$url = $code !== ''
				? $baseUrl . '?CODE=' . rawurlencode($code)
				: $baseUrl . '?ID=' . $popularId;

			$result[] = VacancyDto::fromRow(
				$row,
				[],
				[
					'views' => $views,
					'url' => $url,
					'salaryText' => '',
					'dateText' => '',
					'dateFormatted' => '',
					'tags' => [],
				],
			);

			if (count($result) >= $limit)
			{
				break;
			}
		}

		return $result;
	}
}
