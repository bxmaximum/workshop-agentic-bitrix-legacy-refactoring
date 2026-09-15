<?
/**
 * Общие функции для раздела вакансий.
 * Подключается из компонента legacy:vacancies и из ajax.php.
 *
 * История: 2017 — вынес из шаблона (Д.), 2019 — добавил кеш городов (А.),
 * 2021 — legacy_log для отладки писем, потом так и осталось.
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (!defined("LEGACY_VACANCY_IBLOCK_CODE")) define("LEGACY_VACANCY_IBLOCK_CODE", "VACANCIES");
if (!defined("LEGACY_VACANCY_IBLOCK_TYPE")) define("LEGACY_VACANCY_IBLOCK_TYPE", "legacy");
if (!defined("LEGACY_STAT_TABLE")) define("LEGACY_STAT_TABLE", "legacy_vacancy_stat");
if (!defined("LEGACY_RESPONSE_TABLE")) define("LEGACY_RESPONSE_TABLE", "legacy_vacancy_response");

global $LEGACY_CACHE;
$LEGACY_CACHE = array();

/**
 * ID инфоблока вакансий. Ищем по коду каждый раз, когда спрашивают.
 */
function legacy_get_iblock_id($code = LEGACY_VACANCY_IBLOCK_CODE)
{
	global $LEGACY_CACHE;
	if (isset($LEGACY_CACHE["IBLOCK_" . $code])) {
		return $LEGACY_CACHE["IBLOCK_" . $code];
	}
	if (!CModule::IncludeModule("iblock")) {
		return 0;
	}
	$rs = CIBlock::GetList(array(), array("CODE" => $code, "TYPE" => LEGACY_VACANCY_IBLOCK_TYPE, "CHECK_PERMISSIONS" => "N"));
	if ($ar = $rs->Fetch()) {
		$LEGACY_CACHE["IBLOCK_" . $code] = intval($ar["ID"]);
		return intval($ar["ID"]);
	}
	// на всякий случай пробуем без типа (на старом сайте тип назывался content)
	$rs = CIBlock::GetList(array(), array("CODE" => $code, "CHECK_PERMISSIONS" => "N"));
	if ($ar = $rs->Fetch()) {
		$LEGACY_CACHE["IBLOCK_" . $code] = intval($ar["ID"]);
		return intval($ar["ID"]);
	}
	return 0;
}

/**
 * Список значений свойства типа "список" в виде ID => VALUE
 */
function legacy_get_enum_list($iblockId, $propertyCode)
{
	global $LEGACY_CACHE;
	$key = "ENUM_" . $iblockId . "_" . $propertyCode;
	if (isset($LEGACY_CACHE[$key])) {
		return $LEGACY_CACHE[$key];
	}
	$result = array();
	if (CModule::IncludeModule("iblock")) {
		$rs = CIBlockPropertyEnum::GetList(
			array("SORT" => "ASC", "VALUE" => "ASC"),
			array("IBLOCK_ID" => $iblockId, "CODE" => $propertyCode)
		);
		while ($ar = $rs->Fetch()) {
			$result[$ar["ID"]] = array(
				"ID" => $ar["ID"],
				"VALUE" => $ar["VALUE"],
				"XML_ID" => $ar["XML_ID"],
				"SORT" => $ar["SORT"],
			);
		}
	}
	$LEGACY_CACHE[$key] = $result;
	return $result;
}

/**
 * Города для фильтра. Оставлено для совместимости со старым шаблоном.
 */
function legacy_get_city_list($iblockId)
{
	$arCities = array();
	$arEnum = legacy_get_enum_list($iblockId, "CITY");
	foreach ($arEnum as $id => $arItem) {
		$arCities[$id] = $arItem["VALUE"];
	}
	return $arCities;
}

/**
 * Зарплата "от 100 000 до 150 000 ₽" / "от 100 000 ₽" / "по договорённости"
 */
function legacy_format_salary($from, $to, $currency = "₽")
{
	$from = intval($from);
	$to = intval($to);
	if ($from <= 0 && $to <= 0) {
		return "по договорённости";
	}
	if ($from > 0 && $to > 0) {
		if ($from == $to) {
			return legacy_format_number($from) . " " . $currency;
		}
		return "от " . legacy_format_number($from) . " до " . legacy_format_number($to) . " " . $currency;
	}
	if ($from > 0) {
		return "от " . legacy_format_number($from) . " " . $currency;
	}
	return "до " . legacy_format_number($to) . " " . $currency;
}

function legacy_format_number($n)
{
	return number_format(intval($n), 0, ".", " ");
}

/**
 * Склонение: legacy_plural(5, "отклик", "отклика", "откликов")
 */
function legacy_plural($n, $one, $two, $five)
{
	$n = abs(intval($n)) % 100;
	$n1 = $n % 10;
	if ($n > 10 && $n < 20) {
		return $five;
	}
	if ($n1 > 1 && $n1 < 5) {
		return $two;
	}
	if ($n1 == 1) {
		return $one;
	}
	return $five;
}

/**
 * "сегодня" / "вчера" / "N дней назад" / дата
 */
function legacy_days_ago($dateString)
{
	if (strlen((string)$dateString) <= 0) {
		return "";
	}
	$ts = MakeTimeStamp($dateString);
	if ($ts <= 0) {
		return $dateString;
	}
	$today = mktime(0, 0, 0);
	$day = mktime(0, 0, 0, date("n", $ts), date("j", $ts), date("Y", $ts));
	$diff = intval(($today - $day) / 86400);
	if ($diff <= 0) {
		return "сегодня";
	}
	if ($diff == 1) {
		return "вчера";
	}
	if ($diff < 30) {
		return $diff . " " . legacy_plural($diff, "день", "дня", "дней") . " назад";
	}
	return date("d.m.Y", $ts);
}

/**
 * Чистим пользовательский ввод. Раньше тут был strip_tags + mysql_real_escape_string.
 */
function legacy_clean($value, $maxLength = 255)
{
	if (is_array($value)) {
		$value = implode(", ", $value);
	}
	$value = trim(strip_tags((string)$value));
	$value = str_replace(array("\r", "\n", "\t"), " ", $value);
	$value = preg_replace("/\s+/u", " ", $value);
	if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
		$value = mb_substr($value, 0, $maxLength);
	}
	return $value;
}

function legacy_clean_text($value, $maxLength = 2000)
{
	$value = trim(strip_tags((string)$value));
	if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
		$value = mb_substr($value, 0, $maxLength);
	}
	return $value;
}

function legacy_check_email($email)
{
	return (bool)preg_match("/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i", $email);
}

function legacy_check_phone($phone)
{
	$digits = preg_replace("/[^0-9]/", "", $phone);
	return strlen($digits) >= 10 && strlen($digits) <= 15;
}

/**
 * Просмотры вакансии из статистики. Вызывается в цикле по списку — "потом оптимизируем".
 */
function legacy_get_views($vacancyId)
{
	global $DB;
	$vacancyId = intval($vacancyId);
	if ($vacancyId <= 0) {
		return 0;
	}
	$rs = $DB->Query("SELECT VIEWS FROM " . LEGACY_STAT_TABLE . " WHERE VACANCY_ID = " . $vacancyId, false, "File: " . __FILE__ . "<br>Line: " . __LINE__);
	if ($rs && ($ar = $rs->Fetch())) {
		return intval($ar["VIEWS"]);
	}
	return 0;
}

/**
 * +1 к просмотрам
 */
function legacy_register_view($vacancyId)
{
	global $DB;
	$vacancyId = intval($vacancyId);
	if ($vacancyId <= 0) {
		return false;
	}
	$sql = "INSERT INTO " . LEGACY_STAT_TABLE . " (VACANCY_ID, VIEWS, LAST_VIEW) VALUES (" . $vacancyId . ", 1, " . $DB->GetNowFunction() . ")"
		. " ON DUPLICATE KEY UPDATE VIEWS = VIEWS + 1, LAST_VIEW = " . $DB->GetNowFunction();
	$DB->Query($sql, false, "File: " . __FILE__ . "<br>Line: " . __LINE__);
	return true;
}

/**
 * Количество откликов по вакансии
 */
function legacy_get_response_count($vacancyId)
{
	global $DB;
	$vacancyId = intval($vacancyId);
	$rs = $DB->Query("SELECT COUNT(*) CNT FROM " . LEGACY_RESPONSE_TABLE . " WHERE VACANCY_ID = " . $vacancyId . " AND STATUS <> 'SPAM'", false, "File: " . __FILE__ . "<br>Line: " . __LINE__);
	if ($rs && ($ar = $rs->Fetch())) {
		return intval($ar["CNT"]);
	}
	return 0;
}

/**
 * Топ вакансий по просмотрам: VACANCY_ID => VIEWS
 */
function legacy_get_popular_ids($limit = 5)
{
	global $DB;
	$limit = intval($limit);
	if ($limit <= 0) {
		$limit = 5;
	}
	$result = array();
	$rs = $DB->Query("SELECT VACANCY_ID, VIEWS FROM " . LEGACY_STAT_TABLE . " ORDER BY VIEWS DESC, VACANCY_ID ASC LIMIT " . $limit, false, "File: " . __FILE__ . "<br>Line: " . __LINE__);
	if ($rs) {
		while ($ar = $rs->Fetch()) {
			$result[intval($ar["VACANCY_ID"])] = intval($ar["VIEWS"]);
		}
	}
	return $result;
}

/**
 * Избранное в сессии
 */
function legacy_get_favorites()
{
	if (!isset($_SESSION["LEGACY_VACANCY_FAV"]) || !is_array($_SESSION["LEGACY_VACANCY_FAV"])) {
		$_SESSION["LEGACY_VACANCY_FAV"] = array();
	}
	return $_SESSION["LEGACY_VACANCY_FAV"];
}

function legacy_toggle_favorite($vacancyId)
{
	$vacancyId = intval($vacancyId);
	$fav = legacy_get_favorites();
	if (in_array($vacancyId, $fav)) {
		$fav = array_values(array_diff($fav, array($vacancyId)));
		$_SESSION["LEGACY_VACANCY_FAV"] = $fav;
		return false;
	}
	$fav[] = $vacancyId;
	$_SESSION["LEGACY_VACANCY_FAV"] = $fav;
	return true;
}

function legacy_is_favorite($vacancyId)
{
	return in_array(intval($vacancyId), legacy_get_favorites());
}

/**
 * Лог в /bitrix/php_interface/dbconn.php → LOG_FILENAME, если задан. Иначе тихо.
 */
function legacy_log($message, $data = null)
{
	if ($data !== null) {
		$message .= " " . print_r($data, true);
	}
	AddMessage2Log("[legacy_vacancies] " . $message, "legacy");
}

/**
 * Ссылка на вакансию. Когда-то были ЧПУ, потом откатили — оставили ?ID=
 */
function legacy_vacancy_url($arItem, $baseUrl = "/vacancies/")
{
	if (isset($arItem["CODE"]) && strlen((string)$arItem["CODE"]) > 0) {
		return $baseUrl . "?CODE=" . urlencode($arItem["CODE"]);
	}
	return $baseUrl . "?ID=" . intval($arItem["ID"]);
}

/**
 * Собираем URL списка с текущими фильтрами, заменяя часть параметров
 */
function legacy_build_url($baseUrl, $arCurrent, $arReplace = array())
{
	$arParams = array();
	foreach ($arCurrent as $k => $v) {
		if ($v === "" || $v === null || $v === 0 || $v === "0") {
			continue;
		}
		$arParams[$k] = $v;
	}
	foreach ($arReplace as $k => $v) {
		if ($v === "" || $v === null || $v === 0 || $v === "0") {
			unset($arParams[$k]);
		} else {
			$arParams[$k] = $v;
		}
	}
	if (empty($arParams)) {
		return $baseUrl;
	}
	return $baseUrl . "?" . http_build_query($arParams);
}

/**
 * Старая функция, больше нигде не используется (проверял в 2022). Удалять страшно.
 */
function legacy_get_vacancy_by_id_old($id)
{
	if (!CModule::IncludeModule("iblock")) {
		return false;
	}
	$rs = CIBlockElement::GetByID(intval($id));
	if ($ob = $rs->GetNextElement()) {
		$ar = $ob->GetFields();
		$ar["PROPERTIES"] = $ob->GetProperties();
		return $ar;
	}
	return false;
}
