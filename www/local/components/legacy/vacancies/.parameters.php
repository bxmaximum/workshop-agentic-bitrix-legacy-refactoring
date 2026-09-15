<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (!CModule::IncludeModule("iblock")) {
	return;
}

$arIBlockType = CIBlockParameters::GetIBlockTypes();

$arIBlock = array();
$rsIBlock = CIBlock::GetList(array("SORT" => "ASC"), array("TYPE" => ($arCurrentValues["IBLOCK_TYPE"] != "" ? $arCurrentValues["IBLOCK_TYPE"] : "legacy"), "ACTIVE" => "Y"));
while ($arr = $rsIBlock->Fetch()) {
	$arIBlock[$arr["ID"]] = "[" . $arr["ID"] . "] " . $arr["NAME"];
}

$arSort = array(
	"date" => GetMessage("LEGACY_VACANCIES_SORT_DATE"),
	"salary" => GetMessage("LEGACY_VACANCIES_SORT_SALARY"),
	"views" => GetMessage("LEGACY_VACANCIES_SORT_VIEWS"),
	"name" => GetMessage("LEGACY_VACANCIES_SORT_NAME"),
);

$arComponentParameters = array(
	"GROUPS" => array(
		"FORM" => array(
			"NAME" => GetMessage("LEGACY_VACANCIES_GROUP_FORM"),
		),
		"SIDEBAR" => array(
			"NAME" => GetMessage("LEGACY_VACANCIES_GROUP_SIDEBAR"),
		),
	),
	"PARAMETERS" => array(
		"IBLOCK_TYPE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LEGACY_VACANCIES_IBLOCK_TYPE"),
			"TYPE" => "LIST",
			"VALUES" => $arIBlockType,
			"DEFAULT" => "legacy",
			"REFRESH" => "Y",
		),
		"IBLOCK_ID" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LEGACY_VACANCIES_IBLOCK_ID"),
			"TYPE" => "LIST",
			"VALUES" => $arIBlock,
			"DEFAULT" => "",
			"ADDITIONAL_VALUES" => "Y",
			"REFRESH" => "Y",
		),
		"PAGE_SIZE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LEGACY_VACANCIES_PAGE_SIZE"),
			"TYPE" => "STRING",
			"DEFAULT" => "5",
		),
		"DEFAULT_SORT" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LEGACY_VACANCIES_DEFAULT_SORT"),
			"TYPE" => "LIST",
			"VALUES" => $arSort,
			"DEFAULT" => "date",
		),
		"BASE_URL" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LEGACY_VACANCIES_BASE_URL"),
			"TYPE" => "STRING",
			"DEFAULT" => "/vacancies/",
		),
		"SHOW_POPULAR" => array(
			"PARENT" => "SIDEBAR",
			"NAME" => GetMessage("LEGACY_VACANCIES_SHOW_POPULAR"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		),
		"POPULAR_COUNT" => array(
			"PARENT" => "SIDEBAR",
			"NAME" => GetMessage("LEGACY_VACANCIES_POPULAR_COUNT"),
			"TYPE" => "STRING",
			"DEFAULT" => "5",
		),
		"RELATED_COUNT" => array(
			"PARENT" => "SIDEBAR",
			"NAME" => GetMessage("LEGACY_VACANCIES_RELATED_COUNT"),
			"TYPE" => "STRING",
			"DEFAULT" => "3",
		),
		"SHOW_FORM" => array(
			"PARENT" => "FORM",
			"NAME" => GetMessage("LEGACY_VACANCIES_SHOW_FORM"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		),
		"FORM_EMAIL_TO" => array(
			"PARENT" => "FORM",
			"NAME" => GetMessage("LEGACY_VACANCIES_FORM_EMAIL_TO"),
			"TYPE" => "STRING",
			"DEFAULT" => "hr@example.com",
		),
		"FORM_EVENT_NAME" => array(
			"PARENT" => "FORM",
			"NAME" => GetMessage("LEGACY_VACANCIES_FORM_EVENT_NAME"),
			"TYPE" => "STRING",
			"DEFAULT" => "LEGACY_VACANCY_RESPONSE",
		),
		"FORM_TIMEOUT" => array(
			"PARENT" => "FORM",
			"NAME" => GetMessage("LEGACY_VACANCIES_FORM_TIMEOUT"),
			"TYPE" => "STRING",
			"DEFAULT" => "60",
		),
		"CACHE_TIME" => array("DEFAULT" => 3600),
	),
);
