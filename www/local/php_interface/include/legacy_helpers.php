<?php

declare(strict_types=1);

/**
 * Фасад обратной совместимости для раздела вакансий.
 *
 * Раньше здесь жили прямые SQL-запросы и CIBlock*-вызовы. Теперь каждая функция —
 * тонкий прокси к модулю ws.vacancies (Helper / Repository / Service).
 * Сигнатуры и возвращаемые значения сохранены для стороннего кода.
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Ws\Vacancies\Helper\Formatter;
use Ws\Vacancies\Helper\Text;
use Ws\Vacancies\Helper\UrlBuilder;
use Ws\Vacancies\Repository\VacancyRepository;
use Ws\Vacancies\Repository\VacancyResponseRepository;
use Ws\Vacancies\Repository\VacancyStatRepository;
use Ws\Vacancies\Service\FavoriteService;

if (!defined('LEGACY_VACANCY_IBLOCK_CODE')) define('LEGACY_VACANCY_IBLOCK_CODE', 'VACANCIES');
if (!defined('LEGACY_VACANCY_IBLOCK_TYPE')) define('LEGACY_VACANCY_IBLOCK_TYPE', 'legacy');
if (!defined('LEGACY_STAT_TABLE')) define('LEGACY_STAT_TABLE', 'legacy_vacancy_stat');
if (!defined('LEGACY_RESPONSE_TABLE')) define('LEGACY_RESPONSE_TABLE', 'legacy_vacancy_response');

Loader::includeModule('ws.vacancies');

/**
 * ID инфоблока вакансий. Параметр $code оставлен для совместимости: модуль знает свой инфоблок сам.
 */
function legacy_get_iblock_id($code = LEGACY_VACANCY_IBLOCK_CODE): int
{
	return ServiceLocator::getInstance()->get(VacancyRepository::class)->getIblockId();
}

/**
 * Список значений свойства типа «список» в виде ID => [ID, VALUE, XML_ID, SORT].
 */
function legacy_get_enum_list($iblockId, $propertyCode): array
{
	return ServiceLocator::getInstance()->get(VacancyRepository::class)
		->getEnumList((int)$iblockId, (string)$propertyCode);
}

/**
 * Города для фильтра: ID => VALUE.
 */
function legacy_get_city_list($iblockId): array
{
	return ServiceLocator::getInstance()->get(VacancyRepository::class)->getCities((int)$iblockId);
}

function legacy_format_salary($from, $to, $currency = '₽'): string
{
	return Formatter::salary((int)$from, (int)$to, (string)$currency);
}

function legacy_format_number($n): string
{
	return Formatter::number((int)$n);
}

function legacy_plural($n, $one, $two, $five): string
{
	return Formatter::plural((int)$n, (string)$one, (string)$two, (string)$five);
}

function legacy_days_ago($dateString): string
{
	return Formatter::daysAgo((string)$dateString);
}

function legacy_clean($value, $maxLength = 255): string
{
	return Text::clean($value, (int)$maxLength);
}

function legacy_clean_text($value, $maxLength = 2000): string
{
	return Text::cleanText($value, (int)$maxLength);
}

function legacy_check_email($email): bool
{
	return Text::isEmail((string)$email);
}

function legacy_check_phone($phone): bool
{
	return Text::isPhone((string)$phone);
}

function legacy_get_views($vacancyId): int
{
	return ServiceLocator::getInstance()->get(VacancyStatRepository::class)->getViews((int)$vacancyId);
}

/**
 * +1 к просмотрам.
 */
function legacy_register_view($vacancyId): bool
{
	$vacancyId = (int)$vacancyId;
	if ($vacancyId <= 0)
	{
		return false;
	}

	ServiceLocator::getInstance()->get(VacancyStatRepository::class)->incrementViews($vacancyId);

	return true;
}

/**
 * Количество откликов по вакансии без SPAM.
 */
function legacy_get_response_count($vacancyId): int
{
	return ServiceLocator::getInstance()->get(VacancyResponseRepository::class)
		->getValidCountByVacancyId((int)$vacancyId);
}

/**
 * Топ вакансий по просмотрам: VACANCY_ID => VIEWS.
 */
function legacy_get_popular_ids($limit = 5): array
{
	return ServiceLocator::getInstance()->get(VacancyStatRepository::class)->getTopPopularIds((int)$limit);
}

/**
 * Избранное в сессии.
 */
function legacy_get_favorites(): array
{
	return ServiceLocator::getInstance()->get(FavoriteService::class)->getFavoriteIds();
}

function legacy_toggle_favorite($vacancyId): bool
{
	return ServiceLocator::getInstance()->get(FavoriteService::class)->switchFavorite((int)$vacancyId);
}

function legacy_is_favorite($vacancyId): bool
{
	return ServiceLocator::getInstance()->get(FavoriteService::class)->isFavorite((int)$vacancyId);
}

/**
 * Лог в LOG_FILENAME, если задан. Иначе тихо.
 */
function legacy_log($message, $data = null): void
{
	if ($data !== null)
	{
		$message .= ' ' . print_r($data, true);
	}
	AddMessage2Log('[legacy_vacancies] ' . $message, 'legacy');
}

/**
 * Ссылка на вакансию: ?CODE= либо ?ID=.
 */
function legacy_vacancy_url($arItem, $baseUrl = '/vacancies/'): string
{
	return UrlBuilder::vacancy((string)($arItem['CODE'] ?? ''), (int)($arItem['ID'] ?? 0), (string)$baseUrl);
}

/**
 * URL списка с текущими фильтрами, заменяя часть параметров.
 */
function legacy_build_url($baseUrl, $arCurrent, $arReplace = []): string
{
	return UrlBuilder::list((string)$baseUrl, (array)$arCurrent, (array)$arReplace);
}
