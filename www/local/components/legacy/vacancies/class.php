<?php

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Ws\Vacancies\Controller\Request\VacancyResponseRequest;
use Ws\Vacancies\Dto\PageMetaDto;
use Ws\Vacancies\Dto\PageSettingsDto;
use Ws\Vacancies\Dto\ResponseSettingsDto;
use Ws\Vacancies\Dto\VacancyDto;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Presenter\VacancyPresenter;
use Ws\Vacancies\Repository\VacancyStatRepository;
use Ws\Vacancies\Service\FavoriteService;
use Ws\Vacancies\Service\VacancyPageService;
use Ws\Vacancies\Service\VacancyResponseService;

Loc::loadMessages(__FILE__);

/**
 * Тонкий компонент раздела «Вакансии»: HTTP-вход → сервисы модуля ws.vacancies → arResult.
 */
final class LegacyVacanciesComponent extends CBitrixComponent
{
	private const SORT_CODES = ['date', 'salary', 'views', 'name'];

	private VacancyPageService $pages;
	private VacancyResponseService $responses;
	private VacancyPresenter $presenter;
	private FavoriteService $favorites;
	private VacancyStatRepository $stats;

	public function onPrepareComponentParams($arParams): array
	{
		$arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
		$arParams['PAGE_SIZE'] = $this->intParam($arParams, 'PAGE_SIZE', 5, 50);
		$arParams['POPULAR_COUNT'] = $this->intParam($arParams, 'POPULAR_COUNT', 5);
		$arParams['RELATED_COUNT'] = $this->intParam($arParams, 'RELATED_COUNT', 3);
		$arParams['FORM_TIMEOUT'] = max(0, (int)($arParams['FORM_TIMEOUT'] ?? 60));

		$arParams['SHOW_POPULAR'] = ($arParams['SHOW_POPULAR'] ?? 'Y') === 'N' ? 'N' : 'Y';
		$arParams['SHOW_FORM'] = ($arParams['SHOW_FORM'] ?? 'Y') === 'N' ? 'N' : 'Y';

		$arParams['BASE_URL'] = trim((string)($arParams['BASE_URL'] ?? '')) ?: '/vacancies/';
		$arParams['DEFAULT_SORT'] = trim((string)($arParams['DEFAULT_SORT'] ?? ''));
		if (!in_array($arParams['DEFAULT_SORT'], self::SORT_CODES, true))
		{
			$arParams['DEFAULT_SORT'] = 'date';
		}
		$arParams['FORM_EVENT_NAME'] = trim((string)($arParams['FORM_EVENT_NAME'] ?? '')) ?: 'LEGACY_VACANCY_RESPONSE';
		$arParams['FORM_EMAIL_TO'] = trim((string)($arParams['FORM_EMAIL_TO'] ?? ''));

		$arParams['CACHE_TIME'] = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));
		$arParams['CACHE_TYPE'] = (string)($arParams['CACHE_TYPE'] ?? 'A');

		return $arParams;
	}

	public function executeComponent(): void
	{
		if (!Loader::includeModule('iblock') || !Loader::includeModule('ws.vacancies'))
		{
			ShowError(Loc::getMessage('LEGACY_VACANCIES_ERR_NO_MODULE'));

			return;
		}

		$locator = ServiceLocator::getInstance();
		$this->pages = $locator->get(VacancyPageService::class);
		$this->responses = $locator->get(VacancyResponseService::class);
		$this->presenter = $locator->get(VacancyPresenter::class);
		$this->favorites = $locator->get(FavoriteService::class);
		$this->stats = $locator->get(VacancyStatRepository::class);

		if ($this->pages->getIblockId() <= 0)
		{
			ShowError(Loc::getMessage('LEGACY_VACANCIES_ERR_NO_IBLOCK'));

			return;
		}

		$request = Context::getCurrent()->getRequest();
		[$elementId, $elementCode] = $this->resolveElement($request);

		$this->arResult = [
			'MODE' => 'list',
			'BASE_URL' => $this->arParams['BASE_URL'],
			'ITEMS' => [],
			'ITEM' => [],
			'RELATED' => [],
			'POPULAR' => [],
			'SECTIONS' => [],
			'ERROR' => '',
			'META' => [],
		];

		$isFormPost = $request->isPost() && $request->getPost('legacy_respond') !== null;
		// fav=Y зависит от сессии — не кешируем; POST формы — тоже
		$skipCache = $isFormPost || (string)($request->get('fav') ?? '') === 'Y';

		if ($skipCache)
		{
			$this->buildResult($request, $elementId, $elementCode);
		}
		elseif ($this->startResultCache((int)$this->arParams['CACHE_TIME'], $this->buildCacheId($request, $elementId, $elementCode)))
		{
			$this->buildResult($request, $elementId, $elementCode);
			// FORM и IS_FAVORITE персональные — не пишем в кеш
			unset($this->arResult['FORM']);
			$this->stripFavoriteFlags();
			$this->registerCacheTags();
			$this->endResultCache();
		}
		else
		{
			// cache hit: инкремент просмотров (на miss его делает getDetail)
			$this->incrementViewsOnCacheHit($request);
		}

		$this->applyFavorites();
		$this->ensureForm($request);
		$this->applyHttpStatus();
		$this->applyStoredMeta();
		$this->includeComponentTemplate();
	}

	/**
	 * Баг №4: только верхнерегистровые ID/CODE. Баг №5: ID приоритетнее CODE (решает сервис).
	 *
	 * @return array{0: int, 1: string}
	 */
	private function resolveElement(HttpRequest $request): array
	{
		$id = (int)($request->get('ID') ?? 0);
		if ($id <= 0 && $request->get('vacancy') !== null)
		{
			// старые ссылки вида /vacancies/?vacancy=123
			$id = (int)$request->get('vacancy');
		}

		$code = preg_replace('/[^a-z0-9\-_]/i', '', trim((string)($request->get('CODE') ?? ''))) ?? '';

		return [$id, $code];
	}

	/**
	 * @return list<scalar>
	 */
	private function buildCacheId(HttpRequest $request, int $elementId, string $elementCode): array
	{
		return [
			$this->pages->getIblockId(),
			$elementId,
			$elementCode,
			(int)($request->get('section') ?? 0),
			(int)($request->get('city') ?? 0),
			(int)($request->get('exp') ?? 0),
			(int)($request->get('salary') ?? 0),
			(string)($request->get('hot') ?? ''),
			trim((string)($request->get('q') ?? '')),
			(string)($request->get('sort') ?? ''),
			(int)($request->get('page') ?? 1),
		];
	}

	private function buildResult(HttpRequest $request, int $elementId, string $elementCode): void
	{
		if ($elementId > 0 || $elementCode !== '')
		{
			$this->executeDetail($request, $elementId, $elementCode);
		}
		else
		{
			$this->executeList($request);
		}
	}

	private function executeList(HttpRequest $request): void
	{
		$filter = VacancyFilterDto::fromRequest(
			$request,
			(int)$this->arParams['PAGE_SIZE'],
			(string)$this->arParams['DEFAULT_SORT'],
		);
		$page = $this->pages->listPage($filter, $this->pageSettings());
		$baseUrl = $this->arParams['BASE_URL'];

		$this->arResult['ITEMS'] = array_map(
			fn(VacancyDto $dto): array => $this->presenter->item($dto, $baseUrl, forList: true),
			$page->list->items,
		);
		$this->arResult['NAV'] = $this->presenter->navigation($page->nav);
		$this->arResult['SORT_URLS'] = $page->sortUrls;
		$this->arResult['RESET_URL'] = $page->resetUrl;
		$this->arResult['FILTER'] = $this->presenter->filter($filter, $page->list->currentPage);
		$this->arResult['FILTER_ACTIVE'] = $page->filterActive;
		$this->arResult['CITIES'] = $page->cities;
		$this->arResult['EXPERIENCE'] = $page->experience;
		$this->arResult['SECTIONS'] = array_map([$this->presenter, 'section'], $page->sidebar->sections);
		$this->arResult['POPULAR'] = array_map([$this->presenter, 'popular'], $page->sidebar->popular);
		$this->arResult['WEEK_SUMMARY'] = $page->sidebar->weekSummary;

		$this->storeMeta($page->meta);
	}

	private function executeDetail(HttpRequest $request, int $elementId, string $elementCode): void
	{
		$page = $this->pages->detailPage($elementId, $elementCode, $request->isPost(), $this->pageSettings());

		if ($page === null)
		{
			$meta = $this->pages->notFoundMeta();
			$this->arResult['MODE'] = '404';
			$this->arResult['ERROR'] = $meta->title;
			$this->storeMeta($meta);

			return;
		}

		$baseUrl = $this->arParams['BASE_URL'];
		$this->arResult['MODE'] = 'detail';
		$this->arResult['FORM'] = $this->processForm($request, $page->item);
		$this->arResult['ITEM'] = $this->presenter->item($page->item, $baseUrl, forList: false);
		$this->arResult['RELATED'] = array_map(
			fn(VacancyDto $dto): array => $this->presenter->item($dto, $baseUrl, forList: false),
			$page->sidebar->related,
		);
		$this->arResult['POPULAR'] = array_map([$this->presenter, 'popular'], $page->sidebar->popular);

		$this->storeMeta($page->meta);
	}

	/**
	 * Форма отклика. При успехе — редирект на &sent=Y (без #respond, как в легаси).
	 * Баг №12: sent=Y в GET показывает успех без отправки.
	 *
	 * @return array{ERRORS: array<string, string>, VALUES: array<string, string>, SENT: bool}
	 */
	private function processForm(HttpRequest $request, VacancyDto $item): array
	{
		$form = $this->emptyForm($request);

		if ($this->arParams['SHOW_FORM'] !== 'Y')
		{
			return $form;
		}

		if (!$request->isPost() || $request->getPost('legacy_respond') === null)
		{
			$user = CurrentUser::get();
			if ((int)$user->getId() > 0)
			{
				$form['VALUES'] = [
					'name' => (string)$user->getFullName(),
					'email' => (string)$user->getEmail(),
					'phone' => '',
					'message' => '',
				];
			}

			return $form;
		}

		$input = VacancyResponseRequest::createFromRequest($request);
		$form['VALUES'] = [
			'name' => (string)$input->name,
			'email' => (string)$input->email,
			'phone' => (string)$input->phone,
			'message' => (string)$input->message,
		];

		$result = $this->responses->sendResponse(
			$input->toInputDto(
				vacancyId: $item->id,
				ip: (string)$request->getRemoteAddress(),
				userId: (int)CurrentUser::get()->getId(),
			),
			new ResponseSettingsDto(
				timeout: (int)$this->arParams['FORM_TIMEOUT'],
				eventName: (string)$this->arParams['FORM_EVENT_NAME'],
				emailTo: (string)$this->arParams['FORM_EMAIL_TO'],
				baseUrl: (string)$this->arParams['BASE_URL'],
				siteHost: (string)$request->getHttpHost(),
			),
		);

		if ($result->isSuccess())
		{
			LocalRedirect($item->url . '&sent=Y');
		}

		foreach ($result->getErrors() as $error)
		{
			$form['ERRORS'][$error->getCode() ?: 'error'] = $error->getMessage();
		}

		return $form;
	}

	/**
	 * @return array{ERRORS: array<string, string>, VALUES: array<string, string>, SENT: bool}
	 */
	private function emptyForm(HttpRequest $request): array
	{
		return [
			'ERRORS' => [],
			'VALUES' => [],
			'SENT' => (string)($request->get('sent') ?? '') === 'Y',
		];
	}

	/**
	 * После кеша: форма для GET (sent=Y и префил авторизованного).
	 */
	private function ensureForm(HttpRequest $request): void
	{
		if (($this->arResult['MODE'] ?? '') !== 'detail')
		{
			return;
		}

		if ($request->isPost() && $request->getPost('legacy_respond') !== null)
		{
			return;
		}

		$form = $this->emptyForm($request);
		if ($this->arParams['SHOW_FORM'] === 'Y')
		{
			$user = CurrentUser::get();
			if ((int)$user->getId() > 0)
			{
				$form['VALUES'] = [
					'name' => (string)$user->getFullName(),
					'email' => (string)$user->getEmail(),
					'phone' => '',
					'message' => '',
				];
			}
		}

		$this->arResult['FORM'] = $form;
	}

	private function stripFavoriteFlags(): void
	{
		foreach ($this->arResult['ITEMS'] as &$item)
		{
			$item['IS_FAVORITE'] = false;
		}
		unset($item);

		if ($this->arResult['ITEM'] !== [])
		{
			$this->arResult['ITEM']['IS_FAVORITE'] = false;
		}
	}

	private function applyFavorites(): void
	{
		$ids = $this->favorites->getFavoriteIds();
		if ($ids === [])
		{
			return;
		}

		$set = array_fill_keys($ids, true);

		foreach ($this->arResult['ITEMS'] as &$item)
		{
			$item['IS_FAVORITE'] = isset($set[(int)($item['ID'] ?? 0)]);
		}
		unset($item);

		if ($this->arResult['ITEM'] !== [])
		{
			$this->arResult['ITEM']['IS_FAVORITE'] = isset($set[(int)($this->arResult['ITEM']['ID'] ?? 0)]);
		}
	}

	private function incrementViewsOnCacheHit(HttpRequest $request): void
	{
		if (($this->arResult['MODE'] ?? '') !== 'detail' || $request->isPost())
		{
			return;
		}

		$vacancyId = (int)($this->arResult['ITEM']['ID'] ?? 0);
		if ($vacancyId <= 0)
		{
			return;
		}

		$this->stats->incrementViews($vacancyId);
		$views = $this->stats->getViews($vacancyId);
		$this->arResult['ITEM']['VIEWS'] = $views;
		$this->arResult['ITEM']['VIEWS_FORMATTED'] = number_format($views, 0, '.', ' ');
	}

	private function registerCacheTags(): void
	{
		$tagged = Application::getInstance()->getTaggedCache();
		$tagged->registerTag('ws_vacancies');

		if (($this->arResult['MODE'] ?? '') === 'detail')
		{
			$id = (int)($this->arResult['ITEM']['ID'] ?? 0);
			if ($id > 0)
			{
				$tagged->registerTag('ws_vacancies_item_' . $id);
			}
		}
	}

	private function applyHttpStatus(): void
	{
		if (($this->arResult['MODE'] ?? '') !== '404')
		{
			return;
		}

		@define('ERROR_404', 'Y');
		CHTTP::SetStatus('404 Not Found');
	}

	private function storeMeta(PageMetaDto $meta): void
	{
		$this->arResult['META'] = [
			'TITLE' => $meta->title,
			'DESCRIPTION' => $meta->description,
			'KEYWORDS' => $meta->keywords,
			'BREADCRUMBS' => $meta->breadcrumbs,
		];
	}

	private function applyStoredMeta(): void
	{
		$meta = $this->arResult['META'] ?? null;
		if (!is_array($meta) || $meta === [])
		{
			return;
		}

		global $APPLICATION;

		$APPLICATION->SetTitle((string)($meta['TITLE'] ?? ''));
		$description = (string)($meta['DESCRIPTION'] ?? '');
		if ($description !== '')
		{
			$APPLICATION->SetPageProperty('description', $description);
		}
		if (array_key_exists('KEYWORDS', $meta) && $meta['KEYWORDS'] !== null)
		{
			$APPLICATION->SetPageProperty('keywords', (string)$meta['KEYWORDS']);
		}
		foreach ($meta['BREADCRUMBS'] ?? [] as $crumb)
		{
			if (!is_array($crumb))
			{
				continue;
			}
			$APPLICATION->AddChainItem((string)($crumb['name'] ?? ''), (string)($crumb['url'] ?? ''));
		}
	}

	private function pageSettings(): PageSettingsDto
	{
		return new PageSettingsDto(
			baseUrl: (string)$this->arParams['BASE_URL'],
			defaultSort: (string)$this->arParams['DEFAULT_SORT'],
			popularCount: $this->arParams['SHOW_POPULAR'] === 'Y' ? (int)$this->arParams['POPULAR_COUNT'] : 0,
			relatedCount: (int)$this->arParams['RELATED_COUNT'],
		);
	}

	/**
	 * Целочисленный параметр: <= 0 → значение по умолчанию, при наличии max — обрезка сверху.
	 *
	 * @param array<string, mixed> $params
	 */
	private function intParam(array $params, string $key, int $default, ?int $max = null): int
	{
		$value = (int)($params[$key] ?? 0);
		if ($value <= 0)
		{
			$value = $default;
		}

		return $max !== null ? min($value, $max) : $value;
	}
}
