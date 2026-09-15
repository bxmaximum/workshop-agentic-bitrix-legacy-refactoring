<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Компонент раздела "Вакансии".
 *
 * Список с фильтром и постраничкой, детальная страница, форма отклика,
 * популярные вакансии по просмотрам, избранное в сессии.
 *
 * 2017 — первая версия (Д.)
 * 2018 — отключён кеш: не сбрасывался после отклика, HR видели старые счётчики
 * 2019 — форма отклика переехала из шаблона сюда, sessid отключён (мобильное приложение)
 * 2020 — "популярные" и статистика просмотров в своей таблице
 * 2022 — сортировка по просмотрам, избранное
 *
 * @var CBitrixComponent $this
 * @var array $arParams
 * @var array $arResult
 * @var CMain $APPLICATION
 * @var CUser $USER
 * @var CDatabase $DB
 */

global $APPLICATION, $USER, $DB;

require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/legacy_helpers.php");

if (!CModule::IncludeModule("iblock")) {
	ShowError(GetMessage("LEGACY_VACANCIES_ERR_NO_MODULE"));
	return;
}

// ------------------------------------------------------------------
// Параметры
// ------------------------------------------------------------------

$arParams["IBLOCK_ID"] = isset($arParams["IBLOCK_ID"]) ? intval($arParams["IBLOCK_ID"]) : 0;
if ($arParams["IBLOCK_ID"] <= 0) {
	$arParams["IBLOCK_ID"] = legacy_get_iblock_id();
}
if ($arParams["IBLOCK_ID"] <= 0) {
	ShowError(GetMessage("LEGACY_VACANCIES_ERR_NO_IBLOCK"));
	return;
}

$arParams["PAGE_SIZE"] = isset($arParams["PAGE_SIZE"]) ? intval($arParams["PAGE_SIZE"]) : 0;
if ($arParams["PAGE_SIZE"] <= 0) {
	$arParams["PAGE_SIZE"] = 5;
}
if ($arParams["PAGE_SIZE"] > 50) {
	$arParams["PAGE_SIZE"] = 50;
}

$arParams["POPULAR_COUNT"] = isset($arParams["POPULAR_COUNT"]) ? intval($arParams["POPULAR_COUNT"]) : 0;
if ($arParams["POPULAR_COUNT"] <= 0) {
	$arParams["POPULAR_COUNT"] = 5;
}

$arParams["RELATED_COUNT"] = isset($arParams["RELATED_COUNT"]) ? intval($arParams["RELATED_COUNT"]) : 0;
if ($arParams["RELATED_COUNT"] <= 0) {
	$arParams["RELATED_COUNT"] = 3;
}

$arParams["FORM_TIMEOUT"] = isset($arParams["FORM_TIMEOUT"]) ? intval($arParams["FORM_TIMEOUT"]) : 60;
if ($arParams["FORM_TIMEOUT"] < 0) {
	$arParams["FORM_TIMEOUT"] = 0;
}

$arParams["SHOW_POPULAR"] = (isset($arParams["SHOW_POPULAR"]) && $arParams["SHOW_POPULAR"] == "N") ? "N" : "Y";
$arParams["SHOW_FORM"] = (isset($arParams["SHOW_FORM"]) && $arParams["SHOW_FORM"] == "N") ? "N" : "Y";

$arParams["BASE_URL"] = isset($arParams["BASE_URL"]) ? trim($arParams["BASE_URL"]) : "";
if (strlen($arParams["BASE_URL"]) <= 0) {
	$arParams["BASE_URL"] = "/vacancies/";
}

$arParams["DEFAULT_SORT"] = isset($arParams["DEFAULT_SORT"]) ? trim($arParams["DEFAULT_SORT"]) : "date";
if (!in_array($arParams["DEFAULT_SORT"], array("date", "salary", "views", "name"))) {
	$arParams["DEFAULT_SORT"] = "date";
}

$arParams["FORM_EVENT_NAME"] = isset($arParams["FORM_EVENT_NAME"]) ? trim($arParams["FORM_EVENT_NAME"]) : "";
if (strlen($arParams["FORM_EVENT_NAME"]) <= 0) {
	$arParams["FORM_EVENT_NAME"] = "LEGACY_VACANCY_RESPONSE";
}
$arParams["FORM_EMAIL_TO"] = isset($arParams["FORM_EMAIL_TO"]) ? trim($arParams["FORM_EMAIL_TO"]) : "";

$IBLOCK_ID = $arParams["IBLOCK_ID"];
$BASE_URL = $arParams["BASE_URL"];

// Кеш компонента выключен с 2018 года: после отклика счётчики не обновлялись.
// if ($this->StartResultCache($arParams["CACHE_TIME"], array($USER->GetGroups(), $_REQUEST)))
// {
//     ...
//     $this->IncludeComponentTemplate();
// }

$arResult = array(
	"MODE" => "list",
	"IBLOCK_ID" => $IBLOCK_ID,
	"BASE_URL" => $BASE_URL,
	"ITEMS" => array(),
	"ITEM" => array(),
	"RELATED" => array(),
	"POPULAR" => array(),
	"SECTIONS" => array(),
	"CITIES" => array(),
	"EXPERIENCE" => array(),
	"FILTER" => array(),
	"NAV" => array(),
	"FORM" => array("ERRORS" => array(), "VALUES" => array(), "SENT" => false),
	"FAVORITES" => legacy_get_favorites(),
	"ERROR" => "",
);

$arSelect = array(
	"ID", "IBLOCK_ID", "NAME", "CODE", "IBLOCK_SECTION_ID", "PREVIEW_TEXT", "PREVIEW_TEXT_TYPE",
	"DETAIL_TEXT", "DETAIL_TEXT_TYPE", "ACTIVE_FROM", "DATE_CREATE", "TIMESTAMP_X", "PREVIEW_PICTURE", "DETAIL_PICTURE", "SORT",
);

// ------------------------------------------------------------------
// Разбираем запрос
// ------------------------------------------------------------------

$ELEMENT_ID = isset($_REQUEST["ID"]) ? intval($_REQUEST["ID"]) : 0;
$ELEMENT_CODE = isset($_REQUEST["CODE"]) ? trim((string)$_REQUEST["CODE"]) : "";
$ELEMENT_CODE = preg_replace("/[^a-z0-9\-_]/i", "", $ELEMENT_CODE);

// старые ссылки вида /vacancies/?vacancy=123 из рассылок 2018 года
if ($ELEMENT_ID <= 0 && isset($_REQUEST["vacancy"])) {
	$ELEMENT_ID = intval($_REQUEST["vacancy"]);
}

$arFilterCurrent = array(
	"city" => isset($_REQUEST["city"]) ? intval($_REQUEST["city"]) : 0,
	"section" => isset($_REQUEST["section"]) ? intval($_REQUEST["section"]) : 0,
	"exp" => isset($_REQUEST["exp"]) ? intval($_REQUEST["exp"]) : 0,
	"salary" => isset($_REQUEST["salary"]) ? intval($_REQUEST["salary"]) : 0,
	"q" => isset($_REQUEST["q"]) ? legacy_clean($_REQUEST["q"], 100) : "",
	"hot" => (isset($_REQUEST["hot"]) && $_REQUEST["hot"] == "Y") ? "Y" : "",
	"fav" => (isset($_REQUEST["fav"]) && $_REQUEST["fav"] == "Y") ? "Y" : "",
	"sort" => isset($_REQUEST["sort"]) ? trim((string)$_REQUEST["sort"]) : "",
	"page" => isset($_REQUEST["page"]) ? intval($_REQUEST["page"]) : 1,
);
if (!in_array($arFilterCurrent["sort"], array("date", "salary", "views", "name"))) {
	$arFilterCurrent["sort"] = $arParams["DEFAULT_SORT"];
}
if ($arFilterCurrent["page"] <= 0) {
	$arFilterCurrent["page"] = 1;
}
$arResult["FILTER"] = $arFilterCurrent;

// ------------------------------------------------------------------
// Справочники для фильтра (нужны и в списке, и на детальной — там для "похожих" и хлебных крошек)
// ------------------------------------------------------------------

$arResult["CITIES"] = legacy_get_city_list($IBLOCK_ID);
$arResult["EXPERIENCE"] = array();
$arExpEnum = legacy_get_enum_list($IBLOCK_ID, "EXPERIENCE");
foreach ($arExpEnum as $id => $arEnum) {
	$arResult["EXPERIENCE"][$id] = $arEnum["VALUE"];
}

$rsSections = CIBlockSection::GetList(
	array("SORT" => "ASC", "NAME" => "ASC"),
	array("IBLOCK_ID" => $IBLOCK_ID, "ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y", "CNT_ACTIVE" => "Y"),
	true,
	array("ID", "NAME", "CODE", "SORT", "DEPTH_LEVEL")
);
while ($arSection = $rsSections->GetNext()) {
	$arResult["SECTIONS"][$arSection["ID"]] = array(
		"ID" => $arSection["ID"],
		"NAME" => $arSection["NAME"],
		"CODE" => $arSection["CODE"],
		"COUNT" => intval($arSection["ELEMENT_CNT"]),
		"URL" => legacy_build_url($BASE_URL, array(), array("section" => $arSection["ID"])),
		"SELECTED" => ($arFilterCurrent["section"] == $arSection["ID"]),
	);
}

// ==================================================================
// ДЕТАЛЬНАЯ СТРАНИЦА
// ==================================================================

if ($ELEMENT_ID > 0 || strlen($ELEMENT_CODE) > 0) {

	$arResult["MODE"] = "detail";

	$arFilter = array(
		"IBLOCK_ID" => $IBLOCK_ID,
		"ACTIVE" => "Y",
		"ACTIVE_DATE" => "Y",
	);
	if ($ELEMENT_ID > 0) {
		$arFilter["ID"] = $ELEMENT_ID;
	} else {
		$arFilter["=CODE"] = $ELEMENT_CODE;
	}

	$rsElement = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
	$obElement = $rsElement->GetNextElement();

	if (!$obElement) {
		$arResult["MODE"] = "404";
		$arResult["ERROR"] = GetMessage("LEGACY_VACANCIES_ERR_NOT_FOUND");
		$APPLICATION->SetTitle(GetMessage("LEGACY_VACANCIES_ERR_NOT_FOUND"));
		@define("ERROR_404", "Y");
		CHTTP::SetStatus("404 Not Found");
		$this->IncludeComponentTemplate();
		return;
	}

	$arItem = $obElement->GetFields();
	$arItem["PROPERTIES"] = $obElement->GetProperties();

	// --- разбираем свойства так же, как в списке (копия кода из списка, см. ниже; не трогать — синхронно правится руками) ---

	$arItem["URL"] = legacy_vacancy_url($arItem, $BASE_URL);
	$arItem["DATE_TEXT"] = legacy_days_ago($arItem["ACTIVE_FROM"]);
	$arItem["DATE_FORMATTED"] = strlen((string)$arItem["ACTIVE_FROM"]) > 0 ? FormatDate("d.m.Y", MakeTimeStamp($arItem["ACTIVE_FROM"])) : "";
	$arItem["CITY"] = isset($arItem["PROPERTIES"]["CITY"]["VALUE"]) ? $arItem["PROPERTIES"]["CITY"]["VALUE"] : "";
	$arItem["CITY_ID"] = isset($arItem["PROPERTIES"]["CITY"]["VALUE_ENUM_ID"]) ? intval($arItem["PROPERTIES"]["CITY"]["VALUE_ENUM_ID"]) : 0;
	$arItem["EXPERIENCE"] = isset($arItem["PROPERTIES"]["EXPERIENCE"]["VALUE"]) ? $arItem["PROPERTIES"]["EXPERIENCE"]["VALUE"] : "";
	$arItem["SALARY_FROM"] = isset($arItem["PROPERTIES"]["SALARY_FROM"]["VALUE"]) ? intval($arItem["PROPERTIES"]["SALARY_FROM"]["VALUE"]) : 0;
	$arItem["SALARY_TO"] = isset($arItem["PROPERTIES"]["SALARY_TO"]["VALUE"]) ? intval($arItem["PROPERTIES"]["SALARY_TO"]["VALUE"]) : 0;
	$arItem["SALARY_TEXT"] = legacy_format_salary($arItem["SALARY_FROM"], $arItem["SALARY_TO"]);
	$arItem["HOT"] = (isset($arItem["PROPERTIES"]["HOT"]["VALUE"]) && strlen($arItem["PROPERTIES"]["HOT"]["VALUE"]) > 0);
	$arItem["CONTACT_EMAIL"] = isset($arItem["PROPERTIES"]["CONTACT_EMAIL"]["VALUE"]) ? $arItem["PROPERTIES"]["CONTACT_EMAIL"]["VALUE"] : "";
	$arItem["TAGS"] = array();
	if (isset($arItem["PROPERTIES"]["TAGS"]["VALUE"]) && is_array($arItem["PROPERTIES"]["TAGS"]["VALUE"])) {
		foreach ($arItem["PROPERTIES"]["TAGS"]["VALUE"] as $tag) {
			$tag = trim($tag);
			if (strlen($tag) > 0) {
				$arItem["TAGS"][] = $tag;
			}
		}
	}
	$arItem["SECTION_NAME"] = "";
	$arItem["SECTION_URL"] = "";
	if ($arItem["IBLOCK_SECTION_ID"] > 0 && isset($arResult["SECTIONS"][$arItem["IBLOCK_SECTION_ID"]])) {
		$arItem["SECTION_NAME"] = $arResult["SECTIONS"][$arItem["IBLOCK_SECTION_ID"]]["NAME"];
		$arItem["SECTION_URL"] = $arResult["SECTIONS"][$arItem["IBLOCK_SECTION_ID"]]["URL"];
	} elseif ($arItem["IBLOCK_SECTION_ID"] > 0) {
		// раздел неактивный — всё равно покажем название
		$rsSec = CIBlockSection::GetByID($arItem["IBLOCK_SECTION_ID"]);
		if ($arSec = $rsSec->GetNext()) {
			$arItem["SECTION_NAME"] = $arSec["NAME"];
		}
	}
	$arItem["IS_FAVORITE"] = legacy_is_favorite($arItem["ID"]);

	// --- форма отклика ---

	if ($arParams["SHOW_FORM"] == "Y" && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["legacy_respond"])) {

		// TODO: вернуть check_bitrix_sessid() — отключено в 2019, ломало отклики из мобильного приложения

		$name = isset($_POST["name"]) ? legacy_clean($_POST["name"], 100) : "";
		$email = isset($_POST["email"]) ? legacy_clean($_POST["email"], 100) : "";
		$phone = isset($_POST["phone"]) ? legacy_clean($_POST["phone"], 30) : "";
		$message = isset($_POST["message"]) ? legacy_clean_text($_POST["message"], 2000) : "";

		$arResult["FORM"]["VALUES"] = array(
			"name" => $name,
			"email" => $email,
			"phone" => $phone,
			"message" => $message,
		);

		$arErrors = array();
		if (strlen($name) < 2) {
			$arErrors["name"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_NAME");
		}
		if (!legacy_check_email($email)) {
			$arErrors["email"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_EMAIL");
		}
		if (strlen($phone) > 0 && !legacy_check_phone($phone)) {
			$arErrors["phone"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_PHONE");
		}
		if (mb_strlen($message) < 10) {
			$arErrors["message"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_MESSAGE");
		}
		if (
			$arParams["FORM_TIMEOUT"] > 0
			&& isset($_SESSION["LEGACY_LAST_RESPONSE"])
			&& (time() - intval($_SESSION["LEGACY_LAST_RESPONSE"])) < $arParams["FORM_TIMEOUT"]
		) {
			$arErrors["timeout"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_TIMEOUT");
		}

		if (empty($arErrors)) {
			$ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
			$userId = $USER->IsAuthorized() ? intval($USER->GetID()) : 0;

			$sql = "INSERT INTO " . LEGACY_RESPONSE_TABLE . " (VACANCY_ID, USER_ID, NAME, EMAIL, PHONE, MESSAGE, IP, STATUS, CREATED) VALUES ("
				. intval($arItem["ID"]) . ", "
				. $userId . ", "
				. "'" . $DB->ForSql($name) . "', "
				. "'" . $DB->ForSql($email) . "', "
				. "'" . $DB->ForSql($phone) . "', "
				. "'" . $DB->ForSql($message) . "', "
				. "'" . $DB->ForSql($ip, 45) . "', "
				. "'NEW', "
				. $DB->GetNowFunction()
				. ")";

			$rsInsert = $DB->Query($sql, true, "File: " . __FILE__ . "<br>Line: " . __LINE__);
			if (!$rsInsert) {
				$arErrors["db"] = GetMessage("LEGACY_VACANCIES_FORM_ERR_DB");
				legacy_log("response insert failed", array("vacancy" => $arItem["ID"], "email" => $email));
			} else {
				$responseId = intval($DB->LastID());
				$_SESSION["LEGACY_LAST_RESPONSE"] = time();

				// письмо HR. Событие может быть не заведено на сайте — тогда просто пишем в лог.
				$arEventFields = array(
					"VACANCY_ID" => $arItem["ID"],
					"VACANCY_NAME" => $arItem["~NAME"],
					"VACANCY_URL" => "http://" . $_SERVER["HTTP_HOST"] . $arItem["URL"],
					"NAME" => $name,
					"EMAIL" => $email,
					"PHONE" => $phone,
					"MESSAGE" => $message,
					"EMAIL_TO" => $arParams["FORM_EMAIL_TO"],
					"RESPONSE_ID" => $responseId,
				);
				$sendResult = CEvent::Send($arParams["FORM_EVENT_NAME"], SITE_ID, $arEventFields);
				if (!$sendResult) {
					legacy_log("CEvent::Send returned false for " . $arParams["FORM_EVENT_NAME"], $arEventFields);
				}

				LocalRedirect($arItem["URL"] . "&sent=Y");
			}
		}

		$arResult["FORM"]["ERRORS"] = $arErrors;
	}

	if (isset($_REQUEST["sent"]) && $_REQUEST["sent"] == "Y") {
		$arResult["FORM"]["SENT"] = true;
	}

	// --- просмотры: считаем только GET, чтобы F5 после отправки не накручивал ---
	if ($_SERVER["REQUEST_METHOD"] != "POST") {
		legacy_register_view($arItem["ID"]);
	}
	$arItem["VIEWS"] = legacy_get_views($arItem["ID"]);

	// --- похожие: тот же раздел, кроме текущей ---
	if ($arItem["IBLOCK_SECTION_ID"] > 0) {
		$rsRelated = CIBlockElement::GetList(
			array("ACTIVE_FROM" => "DESC", "ID" => "DESC"),
			array(
				"IBLOCK_ID" => $IBLOCK_ID,
				"ACTIVE" => "Y",
				"ACTIVE_DATE" => "Y",
				"SECTION_ID" => $arItem["IBLOCK_SECTION_ID"],
				"!ID" => $arItem["ID"],
			),
			false,
			array("nTopCount" => $arParams["RELATED_COUNT"]),
			$arSelect
		);
		while ($obRelated = $rsRelated->GetNextElement()) {
			$arRelated = $obRelated->GetFields();
			$arRelated["PROPERTIES"] = $obRelated->GetProperties();
			$arRelated["URL"] = legacy_vacancy_url($arRelated, $BASE_URL);
			$arRelated["CITY"] = isset($arRelated["PROPERTIES"]["CITY"]["VALUE"]) ? $arRelated["PROPERTIES"]["CITY"]["VALUE"] : "";
			$arRelated["SALARY_TEXT"] = legacy_format_salary(
				isset($arRelated["PROPERTIES"]["SALARY_FROM"]["VALUE"]) ? $arRelated["PROPERTIES"]["SALARY_FROM"]["VALUE"] : 0,
				isset($arRelated["PROPERTIES"]["SALARY_TO"]["VALUE"]) ? $arRelated["PROPERTIES"]["SALARY_TO"]["VALUE"] : 0
			);
			$arRelated["VIEWS"] = legacy_get_views($arRelated["ID"]);
			$arResult["RELATED"][] = $arRelated;
		}
	}

	// если похожих в разделе мало — добираем из того же города
	if (count($arResult["RELATED"]) < $arParams["RELATED_COUNT"] && $arItem["CITY_ID"] > 0) {
		$arExclude = array($arItem["ID"]);
		foreach ($arResult["RELATED"] as $arRelated) {
			$arExclude[] = $arRelated["ID"];
		}
		$rsRelated = CIBlockElement::GetList(
			array("ACTIVE_FROM" => "DESC", "ID" => "DESC"),
			array(
				"IBLOCK_ID" => $IBLOCK_ID,
				"ACTIVE" => "Y",
				"ACTIVE_DATE" => "Y",
				"PROPERTY_CITY" => $arItem["CITY_ID"],
				"!ID" => $arExclude,
			),
			false,
			array("nTopCount" => $arParams["RELATED_COUNT"] - count($arResult["RELATED"])),
			$arSelect
		);
		while ($obRelated = $rsRelated->GetNextElement()) {
			$arRelated = $obRelated->GetFields();
			$arRelated["PROPERTIES"] = $obRelated->GetProperties();
			$arRelated["URL"] = legacy_vacancy_url($arRelated, $BASE_URL);
			$arRelated["CITY"] = isset($arRelated["PROPERTIES"]["CITY"]["VALUE"]) ? $arRelated["PROPERTIES"]["CITY"]["VALUE"] : "";
			$arRelated["SALARY_TEXT"] = legacy_format_salary(
				isset($arRelated["PROPERTIES"]["SALARY_FROM"]["VALUE"]) ? $arRelated["PROPERTIES"]["SALARY_FROM"]["VALUE"] : 0,
				isset($arRelated["PROPERTIES"]["SALARY_TO"]["VALUE"]) ? $arRelated["PROPERTIES"]["SALARY_TO"]["VALUE"] : 0
			);
			$arRelated["VIEWS"] = legacy_get_views($arRelated["ID"]);
			$arResult["RELATED"][] = $arRelated;
		}
	}

	$arResult["ITEM"] = $arItem;

	$APPLICATION->SetTitle(str_replace("#NAME#", $arItem["NAME"], GetMessage("LEGACY_VACANCIES_TITLE_DETAIL")));
	$APPLICATION->AddChainItem(GetMessage("LEGACY_VACANCIES_TITLE_LIST"), $BASE_URL);
	if (strlen((string)$arItem["SECTION_NAME"]) > 0 && strlen((string)$arItem["SECTION_URL"]) > 0) {
		$APPLICATION->AddChainItem($arItem["SECTION_NAME"], $arItem["SECTION_URL"]);
	}
	$APPLICATION->AddChainItem($arItem["NAME"]);

	// популярные показываем и на детальной
	if ($arParams["SHOW_POPULAR"] == "Y") {
		$arPopularIds = legacy_get_popular_ids($arParams["POPULAR_COUNT"] + 1);
		foreach ($arPopularIds as $popularId => $views) {
			if ($popularId == $arItem["ID"]) {
				continue;
			}
			if (count($arResult["POPULAR"]) >= $arParams["POPULAR_COUNT"]) {
				break;
			}
			$rsPopular = CIBlockElement::GetByID($popularId);
			if ($arPopular = $rsPopular->GetNext()) {
				if ($arPopular["ACTIVE"] != "Y" || $arPopular["IBLOCK_ID"] != $IBLOCK_ID) {
					continue;
				}
				$arPopular["URL"] = legacy_vacancy_url($arPopular, $BASE_URL);
				$arPopular["VIEWS"] = $views;
				$arResult["POPULAR"][] = $arPopular;
			}
		}
	}

	$this->IncludeComponentTemplate();
	return;
}

// ==================================================================
// СПИСОК
// ==================================================================

$arFilter = array(
	"IBLOCK_ID" => $IBLOCK_ID,
	"ACTIVE" => "Y",
	"ACTIVE_DATE" => "Y",
);

if ($arFilterCurrent["city"] > 0) {
	$arFilter["PROPERTY_CITY"] = $arFilterCurrent["city"];
}
if ($arFilterCurrent["section"] > 0) {
	$arFilter["SECTION_ID"] = $arFilterCurrent["section"];
	$arFilter["INCLUDE_SUBSECTIONS"] = "Y";
}
if ($arFilterCurrent["exp"] > 0) {
	$arFilter["PROPERTY_EXPERIENCE"] = $arFilterCurrent["exp"];
}
if ($arFilterCurrent["salary"] > 0) {
	$arFilter[">=PROPERTY_SALARY_FROM"] = $arFilterCurrent["salary"];
}
if ($arFilterCurrent["hot"] == "Y") {
	$arFilter["!PROPERTY_HOT"] = false;
}
if ($arFilterCurrent["fav"] == "Y") {
	$arFav = legacy_get_favorites();
	if (empty($arFav)) {
		$arFav = array(0);
	}
	$arFilter["ID"] = $arFav;
}
if (strlen($arFilterCurrent["q"]) > 0) {
	$q = $arFilterCurrent["q"];
	$arFilter[] = array(
		"LOGIC" => "OR",
		array("%NAME" => $q),
		array("%PREVIEW_TEXT" => $q),
		array("%PROPERTY_TAGS" => $q),
	);
}

$bSortByViews = false;
switch ($arFilterCurrent["sort"]) {
	case "salary":
		$arSort = array("PROPERTY_SALARY_FROM" => "DESC,NULLS", "ID" => "DESC");
		break;
	case "name":
		$arSort = array("NAME" => "ASC", "ID" => "DESC");
		break;
	case "views":
		// сортировка по просмотрам делается в PHP после выборки (таблица статистики не в инфоблоке)
		$arSort = array("ACTIVE_FROM" => "DESC", "ID" => "DESC");
		$bSortByViews = true;
		break;
	case "date":
	default:
		$arSort = array("ACTIVE_FROM" => "DESC", "ID" => "DESC");
		break;
}

// общее количество для постранички
$iTotal = intval(CIBlockElement::GetList(array(), $arFilter, array()));
$iPages = $iTotal > 0 ? intval(ceil($iTotal / $arParams["PAGE_SIZE"])) : 1;
$iPage = $arFilterCurrent["page"];
if ($iPage > $iPages) {
	$iPage = $iPages;
}

$rsItems = CIBlockElement::GetList(
	$arSort,
	$arFilter,
	false,
	array("nPageSize" => $arParams["PAGE_SIZE"], "iNumPage" => $iPage, "bShowAll" => false),
	$arSelect
);

$arSectionNamesCache = array();

while ($obItem = $rsItems->GetNextElement()) {
	$arItem = $obItem->GetFields();
	$arItem["PROPERTIES"] = $obItem->GetProperties();

	$arItem["URL"] = legacy_vacancy_url($arItem, $BASE_URL);
	$arItem["DATE_TEXT"] = legacy_days_ago($arItem["ACTIVE_FROM"]);
	$arItem["DATE_FORMATTED"] = strlen((string)$arItem["ACTIVE_FROM"]) > 0 ? FormatDate("d.m.Y", MakeTimeStamp($arItem["ACTIVE_FROM"])) : "";
	$arItem["CITY"] = isset($arItem["PROPERTIES"]["CITY"]["VALUE"]) ? $arItem["PROPERTIES"]["CITY"]["VALUE"] : "";
	$arItem["CITY_ID"] = isset($arItem["PROPERTIES"]["CITY"]["VALUE_ENUM_ID"]) ? intval($arItem["PROPERTIES"]["CITY"]["VALUE_ENUM_ID"]) : 0;
	$arItem["EXPERIENCE"] = isset($arItem["PROPERTIES"]["EXPERIENCE"]["VALUE"]) ? $arItem["PROPERTIES"]["EXPERIENCE"]["VALUE"] : "";
	$arItem["SALARY_FROM"] = isset($arItem["PROPERTIES"]["SALARY_FROM"]["VALUE"]) ? intval($arItem["PROPERTIES"]["SALARY_FROM"]["VALUE"]) : 0;
	$arItem["SALARY_TO"] = isset($arItem["PROPERTIES"]["SALARY_TO"]["VALUE"]) ? intval($arItem["PROPERTIES"]["SALARY_TO"]["VALUE"]) : 0;
	$arItem["SALARY_TEXT"] = legacy_format_salary($arItem["SALARY_FROM"], $arItem["SALARY_TO"]);
	$arItem["HOT"] = (isset($arItem["PROPERTIES"]["HOT"]["VALUE"]) && strlen($arItem["PROPERTIES"]["HOT"]["VALUE"]) > 0);
	$arItem["IS_FAVORITE"] = legacy_is_favorite($arItem["ID"]);

	// теги отдельным запросом — так было до того, как GetProperties начали вызывать, и так осталось
	$arItem["TAGS"] = array();
	$rsTags = CIBlockElement::GetProperty($IBLOCK_ID, $arItem["ID"], "sort", "asc", array("CODE" => "TAGS"));
	while ($arTag = $rsTags->Fetch()) {
		$tag = trim((string)$arTag["VALUE"]);
		if (strlen($tag) > 0) {
			$arItem["TAGS"][] = htmlspecialcharsEx($tag);
		}
	}

	// название раздела — по одному запросу на элемент, с "кешем" на время запроса
	$arItem["SECTION_NAME"] = "";
	$arItem["SECTION_URL"] = "";
	if ($arItem["IBLOCK_SECTION_ID"] > 0) {
		if (!isset($arSectionNamesCache[$arItem["IBLOCK_SECTION_ID"]])) {
			$rsSec = CIBlockSection::GetByID($arItem["IBLOCK_SECTION_ID"]);
			if ($arSec = $rsSec->GetNext()) {
				$arSectionNamesCache[$arItem["IBLOCK_SECTION_ID"]] = $arSec["NAME"];
			} else {
				$arSectionNamesCache[$arItem["IBLOCK_SECTION_ID"]] = "";
			}
		}
		$arItem["SECTION_NAME"] = $arSectionNamesCache[$arItem["IBLOCK_SECTION_ID"]];
		$arItem["SECTION_URL"] = legacy_build_url($BASE_URL, array(), array("section" => $arItem["IBLOCK_SECTION_ID"]));
	}

	// просмотры — из своей таблицы, по запросу на строку
	$arItem["VIEWS"] = legacy_get_views($arItem["ID"]);

	$arResult["ITEMS"][] = $arItem;
}

if ($bSortByViews && count($arResult["ITEMS"]) > 1) {
	usort($arResult["ITEMS"], function ($a, $b) {
		if ($a["VIEWS"] == $b["VIEWS"]) {
			return intval($b["ID"]) - intval($a["ID"]);
		}
		return $b["VIEWS"] - $a["VIEWS"];
	});
}

// --- постраничка ---

$arNav = array(
	"PAGE" => $iPage,
	"PAGES" => $iPages,
	"TOTAL" => $iTotal,
	"PAGE_SIZE" => $arParams["PAGE_SIZE"],
	"PREV_URL" => "",
	"NEXT_URL" => "",
	"URLS" => array(),
);
$arUrlParams = $arFilterCurrent;
unset($arUrlParams["page"]);
if ($arUrlParams["sort"] == $arParams["DEFAULT_SORT"]) {
	unset($arUrlParams["sort"]);
}
for ($i = 1; $i <= $iPages; $i++) {
	$arNav["URLS"][$i] = legacy_build_url($BASE_URL, $arUrlParams, array("page" => ($i > 1 ? $i : "")));
}
if ($iPage > 1) {
	$arNav["PREV_URL"] = $arNav["URLS"][$iPage - 1];
}
if ($iPage < $iPages) {
	$arNav["NEXT_URL"] = $arNav["URLS"][$iPage + 1];
}
$arResult["NAV"] = $arNav;

// ссылки для переключения сортировки
$arResult["SORT_URLS"] = array();
foreach (array("date", "salary", "views", "name") as $sortCode) {
	$arResult["SORT_URLS"][$sortCode] = legacy_build_url($BASE_URL, $arUrlParams, array("sort" => ($sortCode == $arParams["DEFAULT_SORT"] ? "" : $sortCode), "page" => ""));
}
$arResult["RESET_URL"] = $BASE_URL;

// --- популярные ---

if ($arParams["SHOW_POPULAR"] == "Y") {
	$arPopularIds = legacy_get_popular_ids($arParams["POPULAR_COUNT"]);
	foreach ($arPopularIds as $popularId => $views) {
		$rsPopular = CIBlockElement::GetByID($popularId);
		if ($arPopular = $rsPopular->GetNext()) {
			if ($arPopular["ACTIVE"] != "Y" || $arPopular["IBLOCK_ID"] != $IBLOCK_ID) {
				continue;
			}
			$arPopular["URL"] = legacy_vacancy_url($arPopular, $BASE_URL);
			$arPopular["VIEWS"] = $views;
			$arResult["POPULAR"][] = $arPopular;
		}
	}
}

// --- заголовок ---

$title = GetMessage("LEGACY_VACANCIES_TITLE_LIST");
if ($arFilterCurrent["section"] > 0 && isset($arResult["SECTIONS"][$arFilterCurrent["section"]])) {
	$title .= ": " . $arResult["SECTIONS"][$arFilterCurrent["section"]]["NAME"];
}
if ($arFilterCurrent["city"] > 0 && isset($arResult["CITIES"][$arFilterCurrent["city"]])) {
	$title .= " — " . $arResult["CITIES"][$arFilterCurrent["city"]];
}
if ($iPage > 1) {
	$title .= ", страница " . $iPage;
}
$APPLICATION->SetTitle($title);
$APPLICATION->AddChainItem(GetMessage("LEGACY_VACANCIES_TITLE_LIST"), $BASE_URL);

$this->IncludeComponentTemplate();

// ------------------------------------------------------------------
// Старый фильтр по зарплате "вилкой". Не используется с 2020, оставлен "на всякий случай".
// ------------------------------------------------------------------
if (!function_exists("__legacy_vacancies_salary_range_filter")) {
	function __legacy_vacancies_salary_range_filter($arFilter, $from, $to)
	{
		$from = intval($from);
		$to = intval($to);
		if ($from > 0) {
			$arFilter[">=PROPERTY_SALARY_TO"] = $from;
		}
		if ($to > 0) {
			$arFilter["<=PROPERTY_SALARY_FROM"] = $to;
		}
		return $arFilter;
	}
}
