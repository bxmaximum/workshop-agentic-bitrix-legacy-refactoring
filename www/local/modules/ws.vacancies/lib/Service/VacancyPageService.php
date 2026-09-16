<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Bitrix\Main\Localization\Loc;
use Ws\Vacancies\Dto\DetailPageDto;
use Ws\Vacancies\Dto\ListPageDto;
use Ws\Vacancies\Dto\NavigationDto;
use Ws\Vacancies\Dto\PageMetaDto;
use Ws\Vacancies\Dto\PageSettingsDto;
use Ws\Vacancies\Dto\SidebarDto;
use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Dto\VacancyListDto;
use Ws\Vacancies\Helper\UrlBuilder;
use Ws\Vacancies\Repository\VacancyRepository;

Loc::loadMessages(__FILE__);

/**
 * Сборка страниц раздела: данные + навигация + метатеги. Компонент только раскладывает это в arResult.
 */
final class VacancyPageService
{
	private const SORT_CODES = ['date', 'salary', 'views', 'name'];

	public function __construct(
		private readonly VacancyService $vacancies,
		private readonly SidebarService $sidebar,
		private readonly VacancyRepository $repository,
	) {
	}

	public function getIblockId(): int
	{
		return $this->repository->getIblockId();
	}

	public function listPage(VacancyFilterDto $filter, PageSettingsDto $settings): ListPageDto
	{
		$list = $this->vacancies->getList($filter, $settings->baseUrl);
		$sidebar = $this->sidebar->getForList($filter->section, $settings->popularCount, $settings->baseUrl);

		$iblockId = $this->repository->getIblockId();
		$cities = $this->repository->getCities($iblockId);

		$urlFilter = $this->filterForUrl($filter, $settings->defaultSort);

		return new ListPageDto(
			list: $list,
			nav: $this->buildNavigation($list, $urlFilter, $settings->baseUrl),
			sortUrls: $this->buildSortUrls($urlFilter, $settings),
			resetUrl: $settings->baseUrl,
			filterActive: $this->isFilterActive($filter, $settings->defaultSort),
			sidebar: $sidebar,
			cities: $cities,
			experience: $this->repository->getExperienceList($iblockId),
			meta: $this->listMeta($list, $filter, $sidebar, $cities, $settings->baseUrl),
		);
	}

	/**
	 * null — вакансия не найдена или неактивна (404).
	 */
	public function detailPage(int $id, string $code, bool $isPost, PageSettingsDto $settings): ?DetailPageDto
	{
		$item = $this->vacancies->getDetail($id, $code, $isPost, $settings->baseUrl);
		if ($item === null)
		{
			return null;
		}

		$sidebar = $this->sidebar->getForDetail(
			$item,
			$settings->popularCount,
			$settings->relatedCount,
			$settings->baseUrl,
		);

		return new DetailPageDto(
			item: $item,
			sidebar: $sidebar,
			meta: $this->detailMeta($item, $settings->baseUrl),
		);
	}

	public function notFoundMeta(): PageMetaDto
	{
		return new PageMetaDto(title: (string)Loc::getMessage('WS_VACANCIES_PAGE_NOT_FOUND'));
	}

	/**
	 * Баг №17: description списка — названия первых 5 вакансий текущей страницы.
	 *
	 * @param array<int, string> $cities
	 */
	private function listMeta(
		VacancyListDto $list,
		VacancyFilterDto $filter,
		SidebarDto $sidebar,
		array $cities,
		string $baseUrl,
	): PageMetaDto {
		$listTitle = (string)Loc::getMessage('WS_VACANCIES_PAGE_LIST');
		$title = $listTitle;

		foreach ($sidebar->sections as $section)
		{
			if ($filter->section > 0 && $section->id === $filter->section)
			{
				$title .= ': ' . $section->name;
				break;
			}
		}
		if ($filter->city > 0 && isset($cities[$filter->city]))
		{
			$title .= ' — ' . $cities[$filter->city];
		}
		if ($list->currentPage > 1)
		{
			$title .= (string)Loc::getMessage('WS_VACANCIES_PAGE_NUMBER', ['#N#' => $list->currentPage]);
		}

		$names = array_map(static fn(VacancyDto $dto): string => $dto->name, $list->items);
		$description = $names !== []
			? (string)Loc::getMessage('WS_VACANCIES_PAGE_LIST_DESCRIPTION', ['#NAMES#' => implode(', ', array_slice($names, 0, 5))])
			: '';

		return new PageMetaDto(
			title: $title,
			description: $description,
			breadcrumbs: [['name' => $listTitle, 'url' => $baseUrl]],
		);
	}

	/**
	 * Баг №17: title детальной — «#NAME# (от X до Y ₽)».
	 */
	private function detailMeta(VacancyDto $item, string $baseUrl): PageMetaDto
	{
		$title = $item->name;
		if ($item->salaryText !== '' && $item->salaryText !== 'по договорённости')
		{
			$title .= ' (' . $item->salaryText . ')';
		}

		$description = trim(strip_tags($item->previewText));
		if (mb_strlen($description) > 160)
		{
			$description = mb_substr($description, 0, 157) . '...';
		}

		$breadcrumbs = [['name' => (string)Loc::getMessage('WS_VACANCIES_PAGE_LIST'), 'url' => $baseUrl]];
		if ($item->sectionName !== '' && $item->sectionUrl !== '')
		{
			$breadcrumbs[] = ['name' => $item->sectionName, 'url' => $item->sectionUrl];
		}
		$breadcrumbs[] = ['name' => $item->name, 'url' => ''];

		return new PageMetaDto(
			title: $title,
			description: $description,
			keywords: implode(', ', $item->tags),
			breadcrumbs: $breadcrumbs,
		);
	}

	/**
	 * Параметры фильтра для ссылок; сортировка по умолчанию в URL не попадает.
	 *
	 * @return array<string, mixed>
	 */
	private function filterForUrl(VacancyFilterDto $filter, string $defaultSort): array
	{
		$params = [
			'city' => $filter->city,
			'section' => $filter->section,
			'exp' => $filter->exp,
			'salary' => $filter->salary,
			'q' => $filter->q,
			'hot' => $filter->hot ? 'Y' : '',
			'fav' => $filter->fav ? 'Y' : '',
			'sort' => $filter->sort,
		];
		if ($params['sort'] === $defaultSort)
		{
			unset($params['sort']);
		}

		return $params;
	}

	/**
	 * @param array<string, mixed> $urlFilter
	 */
	private function buildNavigation(VacancyListDto $list, array $urlFilter, string $baseUrl): NavigationDto
	{
		$urls = [];
		for ($i = 1; $i <= $list->totalPages; $i++)
		{
			$urls[$i] = UrlBuilder::list($baseUrl, $urlFilter, ['page' => ($i > 1 ? $i : '')]);
		}

		return new NavigationDto(
			page: $list->currentPage,
			pages: $list->totalPages,
			total: $list->totalCount,
			pageSize: $list->pageSize,
			prevUrl: $list->currentPage > 1 ? $urls[$list->currentPage - 1] : '',
			nextUrl: $list->currentPage < $list->totalPages ? $urls[$list->currentPage + 1] : '',
			urls: $urls,
		);
	}

	/**
	 * @param array<string, mixed> $urlFilter
	 * @return array<string, string>
	 */
	private function buildSortUrls(array $urlFilter, PageSettingsDto $settings): array
	{
		$urls = [];
		foreach (self::SORT_CODES as $sortCode)
		{
			$urls[$sortCode] = UrlBuilder::list($settings->baseUrl, $urlFilter, [
				'sort' => $sortCode === $settings->defaultSort ? '' : $sortCode,
				'page' => '',
			]);
		}

		return $urls;
	}

	private function isFilterActive(VacancyFilterDto $filter, string $defaultSort): bool
	{
		return $filter->section > 0
			|| $filter->city > 0
			|| $filter->exp > 0
			|| $filter->salary > 0
			|| $filter->hot
			|| $filter->fav
			|| $filter->q !== ''
			|| $filter->sort !== $defaultSort;
	}
}
