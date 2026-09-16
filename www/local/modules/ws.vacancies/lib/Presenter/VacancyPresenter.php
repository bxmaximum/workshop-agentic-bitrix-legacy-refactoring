<?php

declare(strict_types=1);

namespace Ws\Vacancies\Presenter;

use Ws\Vacancies\Dto\NavigationDto;
use Ws\Vacancies\Dto\SectionDto;
use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Helper\UrlBuilder;

/**
 * DTO → массивы для arResult шаблона. Экранирование как у GetNext(): KEY экранирован, ~KEY сырой.
 */
final class VacancyPresenter
{
	/**
	 * @return array<string, mixed>
	 */
	public function item(VacancyDto $dto, string $baseUrl, bool $forList): array
	{
		$previewEscaped = htmlspecialcharsEx($dto->previewText);
		if ($forList && mb_strlen($previewEscaped) > 220)
		{
			$previewEscaped = mb_substr($previewEscaped, 0, 217) . '...';
		}

		return [
			'ID' => $dto->id,
			'NAME' => htmlspecialcharsEx($dto->name),
			'~NAME' => $dto->name,
			'CODE' => $dto->code,
			'IBLOCK_SECTION_ID' => $dto->sectionId,
			'URL' => $dto->url,
			'PREVIEW_TEXT' => $previewEscaped,
			'~PREVIEW_TEXT' => $dto->previewText,
			'PREVIEW_TEXT_TYPE' => $dto->previewTextType,
			'DETAIL_TEXT' => htmlspecialcharsEx($dto->detailText),
			'~DETAIL_TEXT' => $dto->detailText,
			'DETAIL_TEXT_TYPE' => $dto->detailTextType,
			'ACTIVE_FROM' => $dto->activeFrom,
			'DATE_TEXT' => $dto->dateText,
			'DATE_FORMATTED' => $dto->dateFormatted,
			'CITY' => $dto->city,
			'CITY_ID' => $dto->cityId,
			'EXPERIENCE' => $dto->experience,
			'SALARY_FROM' => $dto->salaryFrom,
			'SALARY_TO' => $dto->salaryTo,
			'SALARY_TEXT' => $dto->salaryText,
			'HOT' => $dto->isHot,
			'IS_NEW' => $dto->isNew,
			'IS_FAVORITE' => $dto->isFavorite,
			'TAGS' => $this->tags($dto->tags, $baseUrl),
			'SECTION_NAME' => $dto->sectionName,
			'SECTION_URL' => $dto->sectionUrl,
			'VIEWS' => $dto->views,
			'RESPONSE_COUNT' => $dto->responseCount,
			'WEEK_RESPONSE_COUNT' => $dto->weekResponseCount,
			'CONTACT_EMAIL' => $dto->contactEmail,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function popular(VacancyDto $dto): array
	{
		return [
			'ID' => $dto->id,
			'NAME' => htmlspecialcharsEx($dto->name),
			'~NAME' => $dto->name,
			'CODE' => $dto->code,
			'URL' => $dto->url,
			'VIEWS' => $dto->views,
			'VIEWS_FORMATTED' => number_format($dto->views, 0, '.', ' '),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function section(SectionDto $dto): array
	{
		return [
			'ID' => $dto->id,
			'NAME' => $dto->name,
			'CODE' => $dto->code,
			'COUNT' => $dto->count,
			'URL' => $dto->url,
			'SELECTED' => $dto->selected,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function navigation(NavigationDto $nav): array
	{
		return [
			'PAGE' => $nav->page,
			'PAGES' => $nav->pages,
			'TOTAL' => $nav->total,
			'PAGE_SIZE' => $nav->pageSize,
			'PREV_URL' => $nav->prevUrl,
			'NEXT_URL' => $nav->nextUrl,
			'URLS' => $nav->urls,
		];
	}

	/**
	 * Текущие значения фильтра для формы: чекбоксы как 'Y' | ''.
	 *
	 * @return array<string, mixed>
	 */
	public function filter(VacancyFilterDto $filter, int $currentPage): array
	{
		return [
			'city' => $filter->city,
			'section' => $filter->section,
			'exp' => $filter->exp,
			'salary' => $filter->salary,
			'q' => $filter->q,
			'hot' => $filter->hot ? 'Y' : '',
			'fav' => $filter->fav ? 'Y' : '',
			'sort' => $filter->sort,
			'page' => $currentPage,
		];
	}

	/**
	 * Баг №14: в href тег кодируется через http_build_query, в тексте — экранируется шаблоном.
	 *
	 * @param list<string> $tags
	 * @return list<array{NAME: string, URL: string}>
	 */
	private function tags(array $tags, string $baseUrl): array
	{
		return array_map(
			static fn(string $tag): array => [
				'NAME' => $tag,
				'URL' => UrlBuilder::list($baseUrl, [], ['q' => $tag]),
			],
			$tags,
		);
	}
}
