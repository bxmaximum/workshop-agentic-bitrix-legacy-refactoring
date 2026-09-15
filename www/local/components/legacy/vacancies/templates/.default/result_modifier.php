<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Доработки под шаблон. Часть логики дублирует component.php — так исторически сложилось:
 * шаблон переделывали отдельно от компонента, и "новинки" считать удобнее было здесь.
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 * @var CMain $APPLICATION
 */

global $APPLICATION;

$NEW_DAYS = 3;

// ------------------------------------------------------------------
// Пометка "новая" и порядок тегов — и в списке, и на детальной
// ------------------------------------------------------------------

if (!function_exists("__lv_is_new")) {
	function __lv_is_new($activeFrom, $days)
	{
		if (strlen((string)$activeFrom) <= 0) {
			return false;
		}
		$ts = MakeTimeStamp($activeFrom);
		if ($ts <= 0) {
			return false;
		}
		return (time() - $ts) < $days * 86400;
	}
}

if (!function_exists("__lv_prepare_tags")) {
	function __lv_prepare_tags($arTags)
	{
		if (!is_array($arTags)) {
			return array();
		}
		$arTags = array_unique(array_map("trim", $arTags));
		usort($arTags, function ($a, $b) {
			return strcasecmp($a, $b);
		});
		return array_values($arTags);
	}
}

if ($arResult["MODE"] == "detail") {
	$arResult["ITEM"]["IS_NEW"] = __lv_is_new($arResult["ITEM"]["ACTIVE_FROM"], $NEW_DAYS);
	$arResult["ITEM"]["TAGS"] = __lv_prepare_tags($arResult["ITEM"]["TAGS"]);

	// хочется красивую зарплату в title — переопределяем то, что поставил компонент
	$title = $arResult["ITEM"]["NAME"];
	if (strlen($arResult["ITEM"]["SALARY_TEXT"]) > 0 && $arResult["ITEM"]["SALARY_TEXT"] != "по договорённости") {
		$title .= " (" . $arResult["ITEM"]["SALARY_TEXT"] . ")";
	}
	$APPLICATION->SetTitle($title);

	$description = trim(strip_tags($arResult["ITEM"]["~PREVIEW_TEXT"]));
	if (mb_strlen($description) > 160) {
		$description = mb_substr($description, 0, 157) . "...";
	}
	if (strlen($description) > 0) {
		$APPLICATION->SetPageProperty("description", $description);
	}
	$APPLICATION->SetPageProperty("keywords", implode(", ", $arResult["ITEM"]["TAGS"]));
} else {
	foreach ($arResult["ITEMS"] as $i => $arItem) {
		$arResult["ITEMS"][$i]["IS_NEW"] = __lv_is_new($arItem["ACTIVE_FROM"], $NEW_DAYS);
		$arResult["ITEMS"][$i]["TAGS"] = __lv_prepare_tags($arItem["TAGS"]);

		// обрезаем анонс в списке; в компоненте это не делается, потому что "детальная тоже через него"
		if (mb_strlen($arItem["PREVIEW_TEXT"]) > 220) {
			$arResult["ITEMS"][$i]["PREVIEW_TEXT"] = mb_substr($arItem["PREVIEW_TEXT"], 0, 217) . "...";
		}
	}

	// активен ли фильтр (для кнопки "сбросить")
	$arResult["FILTER_ACTIVE"] = false;
	foreach ($arResult["FILTER"] as $k => $v) {
		if ($k == "page" || $k == "sort") {
			continue;
		}
		if ($v !== "" && $v !== 0 && $v !== null) {
			$arResult["FILTER_ACTIVE"] = true;
			break;
		}
	}
	if ($arResult["FILTER"]["sort"] != $arParams["DEFAULT_SORT"]) {
		$arResult["FILTER_ACTIVE"] = true;
	}

	// description для списка
	$arDescr = array();
	foreach ($arResult["ITEMS"] as $arItem) {
		$arDescr[] = $arItem["~NAME"];
	}
	if (!empty($arDescr)) {
		$APPLICATION->SetPageProperty("description", "Вакансии: " . implode(", ", array_slice($arDescr, 0, 5)));
	}
}

// ------------------------------------------------------------------
// Формат счётчиков популярных — "1 234" вместо "1234"
// ------------------------------------------------------------------
foreach ($arResult["POPULAR"] as $i => $arPopular) {
	$arResult["POPULAR"][$i]["VIEWS_FORMATTED"] = legacy_format_number($arPopular["VIEWS"]);
}
