<?php

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Context;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Dto\VacancyResponseInputDto;
use Ws\Vacancies\Repository\VacancyRepository;
use Ws\Vacancies\Service\FavoriteService;
use Ws\Vacancies\Service\SidebarService;
use Ws\Vacancies\Service\VacancyResponseService;
use Ws\Vacancies\Service\VacancyService;

Loc::loadMessages(__FILE__);

/**
 * Тонкий ООП-компонент раздела «Вакансии».
 * Бизнес-логика — в модуле ws.vacancies (Service → Repository → Model).
 */
final class LegacyVacanciesComponent extends CBitrixComponent
{
	private VacancyService $vacancyService;
	private VacancyResponseService $responseService;
	private FavoriteService $favoriteService;
	private SidebarService $sidebarService;
	private VacancyRepository $vacancyRepository;

	public function onPrepareComponentParams($arParams): array
	{
		$arParams['IBLOCK_ID'] = isset($arParams['IBLOCK_ID']) ? (int)$arParams['IBLOCK_ID'] : 0;

		$arParams['PAGE_SIZE'] = isset($arParams['PAGE_SIZE']) ? (int)$arParams['PAGE_SIZE'] : 0;
		if ($arParams['PAGE_SIZE'] <= 0)
		{
			$arParams['PAGE_SIZE'] = 5;
		}
		if ($arParams['PAGE_SIZE'] > 50)
		{
			$arParams['PAGE_SIZE'] = 50;
		}

		$arParams['POPULAR_COUNT'] = isset($arParams['POPULAR_COUNT']) ? (int)$arParams['POPULAR_COUNT'] : 0;
		if ($arParams['POPULAR_COUNT'] <= 0)
		{
			$arParams['POPULAR_COUNT'] = 5;
		}

		$arParams['RELATED_COUNT'] = isset($arParams['RELATED_COUNT']) ? (int)$arParams['RELATED_COUNT'] : 0;
		if ($arParams['RELATED_COUNT'] <= 0)
		{
			$arParams['RELATED_COUNT'] = 3;
		}

		$arParams['FORM_TIMEOUT'] = isset($arParams['FORM_TIMEOUT']) ? (int)$arParams['FORM_TIMEOUT'] : 60;
		if ($arParams['FORM_TIMEOUT'] < 0)
		{
			$arParams['FORM_TIMEOUT'] = 0;
		}

		$arParams['SHOW_POPULAR'] = (isset($arParams['SHOW_POPULAR']) && $arParams['SHOW_POPULAR'] === 'N') ? 'N' : 'Y';
		$arParams['SHOW_FORM'] = (isset($arParams['SHOW_FORM']) && $arParams['SHOW_FORM'] === 'N') ? 'N' : 'Y';

		$arParams['BASE_URL'] = isset($arParams['BASE_URL']) ? trim((string)$arParams['BASE_URL']) : '';
		if ($arParams['BASE_URL'] === '')
		{
			$arParams['BASE_URL'] = '/vacancies/';
		}

		$arParams['DEFAULT_SORT'] = isset($arParams['DEFAULT_SORT']) ? trim((string)$arParams['DEFAULT_SORT']) : 'date';
		if (!in_array($arParams['DEFAULT_SORT'], ['date', 'salary', 'views', 'name'], true))
		{
			$arParams['DEFAULT_SORT'] = 'date';
		}

		$arParams['FORM_EVENT_NAME'] = isset($arParams['FORM_EVENT_NAME']) ? trim((string)$arParams['FORM_EVENT_NAME']) : '';
		if ($arParams['FORM_EVENT_NAME'] === '')
		{
			$arParams['FORM_EVENT_NAME'] = 'LEGACY_VACANCY_RESPONSE';
		}

		$arParams['FORM_EMAIL_TO'] = isset($arParams['FORM_EMAIL_TO']) ? trim((string)$arParams['FORM_EMAIL_TO']) : '';

		return $arParams;
	}

	public function executeComponent(): void
	{
		global $APPLICATION, $USER;

		require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legacy_helpers.php';

		if (!Loader::includeModule('iblock'))
		{
			ShowError(Loc::getMessage('LEGACY_VACANCIES_ERR_NO_MODULE'));

			return;
		}

		if (!Loader::includeModule('ws.vacancies'))
		{
			ShowError(Loc::getMessage('LEGACY_VACANCIES_ERR_NO_MODULE'));

			return;
		}

		$this->resolveServices();

		$iblockId = (int)$this->arParams['IBLOCK_ID'];
		if ($iblockId <= 0)
		{
			$iblockId = $this->vacancyService->getIblockId();
		}
		if ($iblockId <= 0)
		{
			ShowError(Loc::getMessage('LEGACY_VACANCIES_ERR_NO_IBLOCK'));

			return;
		}

		$baseUrl = (string)$this->arParams['BASE_URL'];
		$request = Context::getCurrent()->getRequest();
		$server = Context::getCurrent()->getServer();

		// Баг №4: строго верхний регистр ID/CODE
		$elementId = (int)($request->get('ID') ?? 0);
		$elementCode = trim((string)($request->get('CODE') ?? ''));
		$elementCode = preg_replace('/[^a-z0-9\-_]/i', '', $elementCode) ?? '';

		// старые ссылки вида /vacancies/?vacancy=123
		if ($elementId <= 0 && $request->get('vacancy') !== null)
		{
			$elementId = (int)$request->get('vacancy');
		}

		$filterDto = VacancyFilterDto::fromRequest(
			$request,
			(int)$this->arParams['PAGE_SIZE'],
			(string)$this->arParams['DEFAULT_SORT'],
		);

		$this->arResult = [
			'MODE' => 'list',
			'IBLOCK_ID' => $iblockId,
			'BASE_URL' => $baseUrl,
			'ITEMS' => [],
			'ITEM' => [],
			'RELATED' => [],
			'POPULAR' => [],
			'SECTIONS' => [],
			'CITIES' => $this->vacancyRepository->getCities($iblockId),
			'EXPERIENCE' => $this->vacancyRepository->getExperienceList($iblockId),
			'FILTER' => [
				'city' => $filterDto->city,
				'section' => $filterDto->section,
				'exp' => $filterDto->exp,
				'salary' => $filterDto->salary,
				'q' => $filterDto->q,
				'hot' => $filterDto->hot ? 'Y' : '',
				'fav' => $filterDto->fav ? 'Y' : '',
				'sort' => $filterDto->sort,
				'page' => $filterDto->page,
			],
			'NAV' => [],
			'FORM' => ['ERRORS' => [], 'VALUES' => [], 'SENT' => false],
			'FAVORITES' => $this->favoriteService->getFavoriteIds(),
			'ERROR' => '',
			'FILTER_ACTIVE' => false,
			'SORT_URLS' => [],
			'RESET_URL' => $baseUrl,
			'WEEK_SUMMARY' => null,
		];

		if ($elementId > 0 || $elementCode !== '')
		{
			$this->executeDetail(
				$elementId,
				$elementCode,
				$baseUrl,
				$request->isPost() && $request->getPost('legacy_respond') !== null,
				(string)($request->get('sent') ?? '') === 'Y',
				$USER,
				$APPLICATION,
				$server,
			);

			return;
		}

		$this->executeList($filterDto, $baseUrl, $APPLICATION);
	}

	private function resolveServices(): void
	{
		$locator = ServiceLocator::getInstance();

		$this->vacancyService = $locator->get(VacancyService::class);
		$this->responseService = $locator->get(VacancyResponseService::class);
		$this->favoriteService = $locator->get(FavoriteService::class);
		$this->sidebarService = $locator->get(SidebarService::class);
		$this->vacancyRepository = $locator->get(VacancyRepository::class);
	}

	private function executeDetail(
		int $elementId,
		string $elementCode,
		string $baseUrl,
		bool $isFormPost,
		bool $sentFlag,
		mixed $USER,
		mixed $APPLICATION,
		mixed $server,
	): void {
		$this->arResult['MODE'] = 'detail';

		$isPost = Context::getCurrent()->getRequest()->isPost();
		$item = $this->vacancyService->getDetail($elementId, $elementCode, $isPost, $baseUrl);

		if ($item === null)
		{
			$this->arResult['MODE'] = '404';
			$this->arResult['ERROR'] = (string)Loc::getMessage('LEGACY_VACANCIES_ERR_NOT_FOUND');
			$APPLICATION->SetTitle((string)Loc::getMessage('LEGACY_VACANCIES_ERR_NOT_FOUND'));
			@define('ERROR_404', 'Y');
			CHTTP::SetStatus('404 Not Found');
			$this->includeComponentTemplate();

			return;
		}

		if ($this->arParams['SHOW_FORM'] === 'Y' && $isFormPost)
		{
			$request = Context::getCurrent()->getRequest();
			$name = $this->clean((string)($request->getPost('name') ?? ''), 100);
			$email = $this->clean((string)($request->getPost('email') ?? ''), 100);
			$phone = $this->clean((string)($request->getPost('phone') ?? ''), 30);
			$message = $this->cleanText((string)($request->getPost('message') ?? ''), 2000);

			$this->arResult['FORM']['VALUES'] = [
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'message' => $message,
			];

			$dto = new VacancyResponseInputDto(
				vacancyId: $item->id,
				name: $name,
				email: $email,
				phone: $phone,
				message: $message,
				ip: (string)($server->get('REMOTE_ADDR') ?? ''),
				userId: ($USER && method_exists($USER, 'IsAuthorized') && $USER->IsAuthorized())
					? (int)$USER->GetID()
					: 0,
			);

			$host = (string)($server->get('HTTP_HOST') ?? '');
			$result = $this->responseService->sendResponse(
				$dto,
				(int)$this->arParams['FORM_TIMEOUT'],
				(string)$this->arParams['FORM_EVENT_NAME'],
				(string)$this->arParams['FORM_EMAIL_TO'],
				$item->name,
				'http://' . $host . $item->url,
			);

			if ($result->isSuccess())
			{
				// как в легаси: без #respond
				LocalRedirect($item->url . '&sent=Y');
			}

			$errors = [];
			foreach ($result->getErrors() as $error)
			{
				$code = $error->getCode() !== '' ? $error->getCode() : 'error';
				$errors[$code] = $error->getMessage();
			}
			$this->arResult['FORM']['ERRORS'] = $errors;
		}

		// Баг №12
		if ($sentFlag)
		{
			$this->arResult['FORM']['SENT'] = true;
		}

		$popularCount = $this->arParams['SHOW_POPULAR'] === 'Y' ? (int)$this->arParams['POPULAR_COUNT'] : 0;
		$sidebar = $this->sidebarService->getForDetail(
			$item,
			$popularCount,
			(int)$this->arParams['RELATED_COUNT'],
			$baseUrl,
		);

		$this->arResult['ITEM'] = $this->mapVacancyToArray($item, forList: false);
		$this->arResult['RELATED'] = array_map(
			fn(VacancyDto $dto): array => $this->mapVacancyToArray($dto, forList: false, relatedLite: true),
			$sidebar->related,
		);
		$this->arResult['POPULAR'] = array_map(
			fn(VacancyDto $dto): array => $this->mapPopularToArray($dto),
			$sidebar->popular,
		);

		// Баг №17: title с зарплатой
		$title = $item->name;
		if ($item->salaryText !== '' && $item->salaryText !== 'по договорённости')
		{
			$title .= ' (' . $item->salaryText . ')';
		}
		$APPLICATION->SetTitle($title);

		$description = trim(strip_tags($item->previewText));
		if (mb_strlen($description) > 160)
		{
			$description = mb_substr($description, 0, 157) . '...';
		}
		if ($description !== '')
		{
			$APPLICATION->SetPageProperty('description', $description);
		}

		$tags = $this->prepareTags($item->tags);
		$APPLICATION->SetPageProperty('keywords', implode(', ', $tags));

		$APPLICATION->AddChainItem((string)Loc::getMessage('LEGACY_VACANCIES_TITLE_LIST'), $baseUrl);
		if ($item->sectionName !== '' && $item->sectionUrl !== '')
		{
			$APPLICATION->AddChainItem($item->sectionName, $item->sectionUrl);
		}
		$APPLICATION->AddChainItem($item->name);

		$this->includeComponentTemplate();
	}

	private function executeList(VacancyFilterDto $filterDto, string $baseUrl, mixed $APPLICATION): void
	{
		$list = $this->vacancyService->getList($filterDto, $baseUrl);

		$filterForUrl = [
			'city' => $filterDto->city,
			'section' => $filterDto->section,
			'exp' => $filterDto->exp,
			'salary' => $filterDto->salary,
			'q' => $filterDto->q,
			'hot' => $filterDto->hot ? 'Y' : '',
			'fav' => $filterDto->fav ? 'Y' : '',
			'sort' => $filterDto->sort,
		];
		if ($filterForUrl['sort'] === $this->arParams['DEFAULT_SORT'])
		{
			unset($filterForUrl['sort']);
		}

		$nav = [
			'PAGE' => $list->currentPage,
			'PAGES' => $list->totalPages,
			'TOTAL' => $list->totalCount,
			'PAGE_SIZE' => $list->pageSize,
			'PREV_URL' => '',
			'NEXT_URL' => '',
			'URLS' => [],
		];

		for ($i = 1; $i <= $list->totalPages; $i++)
		{
			$nav['URLS'][$i] = $this->buildUrl($baseUrl, $filterForUrl, ['page' => ($i > 1 ? $i : '')]);
		}
		if ($list->currentPage > 1)
		{
			$nav['PREV_URL'] = $nav['URLS'][$list->currentPage - 1];
		}
		if ($list->currentPage < $list->totalPages)
		{
			$nav['NEXT_URL'] = $nav['URLS'][$list->currentPage + 1];
		}

		$sortUrls = [];
		foreach (['date', 'salary', 'views', 'name'] as $sortCode)
		{
			$sortUrls[$sortCode] = $this->buildUrl(
				$baseUrl,
				$filterForUrl,
				[
					'sort' => ($sortCode === $this->arParams['DEFAULT_SORT'] ? '' : $sortCode),
					'page' => '',
				],
			);
		}

		$popularCount = $this->arParams['SHOW_POPULAR'] === 'Y' ? (int)$this->arParams['POPULAR_COUNT'] : 0;
		$sidebar = $this->sidebarService->getForList($filterDto->section, $popularCount, $baseUrl);

		$sections = [];
		foreach ($sidebar->sections as $section)
		{
			$sections[$section->id] = [
				'ID' => $section->id,
				'NAME' => $section->name,
				'CODE' => $section->code,
				'COUNT' => $section->count,
				'URL' => $section->url,
				'SELECTED' => $section->selected,
			];
		}

		$items = [];
		foreach ($list->items as $dto)
		{
			$items[] = $this->mapVacancyToArray($dto, forList: true);
		}

		$this->arResult['ITEMS'] = $items;
		$this->arResult['NAV'] = $nav;
		$this->arResult['SORT_URLS'] = $sortUrls;
		$this->arResult['SECTIONS'] = $sections;
		$this->arResult['POPULAR'] = array_map(
			fn(VacancyDto $dto): array => $this->mapPopularToArray($dto),
			$sidebar->popular,
		);
		$this->arResult['WEEK_SUMMARY'] = $sidebar->weekSummary;
		$this->arResult['FILTER']['page'] = $list->currentPage;
		$this->arResult['FILTER_ACTIVE'] = $this->isFilterActive($filterDto);

		$title = (string)Loc::getMessage('LEGACY_VACANCIES_TITLE_LIST');
		if ($filterDto->section > 0 && isset($sections[$filterDto->section]))
		{
			$title .= ': ' . $sections[$filterDto->section]['NAME'];
		}
		if ($filterDto->city > 0 && isset($this->arResult['CITIES'][$filterDto->city]))
		{
			$title .= ' — ' . $this->arResult['CITIES'][$filterDto->city];
		}
		if ($list->currentPage > 1)
		{
			$title .= ', страница ' . $list->currentPage;
		}
		$APPLICATION->SetTitle($title);
		$APPLICATION->AddChainItem((string)Loc::getMessage('LEGACY_VACANCIES_TITLE_LIST'), $baseUrl);

		// Баг №17: description из названий текущей страницы
		$names = array_map(static fn(VacancyDto $dto): string => $dto->name, $list->items);
		if ($names !== [])
		{
			$APPLICATION->SetPageProperty('description', 'Вакансии: ' . implode(', ', array_slice($names, 0, 5)));
		}

		$this->includeComponentTemplate();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mapVacancyToArray(VacancyDto $dto, bool $forList, bool $relatedLite = false): array
	{
		$tags = $this->prepareTags($dto->tags);

		$previewRaw = $dto->previewText;
		$previewEscaped = htmlspecialcharsEx($previewRaw);
		if ($forList && mb_strlen((string)$previewEscaped) > 220)
		{
			$previewEscaped = mb_substr((string)$previewEscaped, 0, 217) . '...';
		}

		$item = [
			'ID' => $dto->id,
			'NAME' => htmlspecialcharsEx($dto->name),
			'~NAME' => $dto->name,
			'CODE' => $dto->code,
			'IBLOCK_SECTION_ID' => $dto->sectionId,
			'URL' => $dto->url,
			'PREVIEW_TEXT' => $previewEscaped,
			'~PREVIEW_TEXT' => $previewRaw,
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
			'TAGS' => $tags,
			'SECTION_NAME' => $dto->sectionName,
			'SECTION_URL' => $dto->sectionUrl,
			'VIEWS' => $dto->views,
			'RESPONSE_COUNT' => $dto->responseCount,
			'WEEK_RESPONSE_COUNT' => $dto->weekResponseCount,
			'CONTACT_EMAIL' => $dto->contactEmail,
		];

		if ($relatedLite)
		{
			$item['NAME'] = htmlspecialcharsEx($dto->name);
		}

		return $item;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mapPopularToArray(VacancyDto $dto): array
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
	 * @param list<string> $tags
	 * @return list<string>
	 */
	private function prepareTags(array $tags): array
	{
		$tags = array_values(array_unique(array_map(
			static fn($tag): string => trim((string)$tag),
			$tags,
		)));
		$tags = array_values(array_filter($tags, static fn(string $tag): bool => $tag !== ''));
		usort($tags, static fn(string $a, string $b): int => strcasecmp($a, $b));

		return $tags;
	}

	private function isFilterActive(VacancyFilterDto $filter): bool
	{
		if ($filter->section > 0 || $filter->city > 0 || $filter->exp > 0 || $filter->salary > 0)
		{
			return true;
		}
		if ($filter->hot || $filter->fav || $filter->q !== '')
		{
			return true;
		}
		if ($filter->sort !== $this->arParams['DEFAULT_SORT'])
		{
			return true;
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $replace
	 */
	private function buildUrl(string $baseUrl, array $current, array $replace = []): string
	{
		$params = [];
		foreach ($current as $k => $v)
		{
			if ($v === '' || $v === null || $v === 0 || $v === '0')
			{
				continue;
			}
			$params[$k] = $v;
		}
		foreach ($replace as $k => $v)
		{
			if ($v === '' || $v === null || $v === 0 || $v === '0')
			{
				unset($params[$k]);
			}
			else
			{
				$params[$k] = $v;
			}
		}

		if ($params === [])
		{
			return $baseUrl;
		}

		return $baseUrl . '?' . http_build_query($params);
	}

	private function clean(string $value, int $maxLength = 255): string
	{
		$value = trim(strip_tags($value));
		$value = str_replace(["\r", "\n", "\t"], ' ', $value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		if ($maxLength > 0 && mb_strlen($value) > $maxLength)
		{
			$value = mb_substr($value, 0, $maxLength);
		}

		return $value;
	}

	private function cleanText(string $value, int $maxLength = 2000): string
	{
		$value = trim(strip_tags($value));
		if ($maxLength > 0 && mb_strlen($value) > $maxLength)
		{
			$value = mb_substr($value, 0, $maxLength);
		}

		return $value;
	}
}
