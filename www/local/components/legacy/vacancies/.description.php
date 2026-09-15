<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = array(
	"NAME" => GetMessage("LEGACY_VACANCIES_NAME"),
	"DESCRIPTION" => GetMessage("LEGACY_VACANCIES_DESCRIPTION"),
	"ICON" => "/images/icon.gif",
	"SORT" => 20,
	"CACHE_PATH" => "Y",
	"PATH" => array(
		"ID" => "legacy",
		"NAME" => GetMessage("LEGACY_VACANCIES_PATH"),
		"CHILD" => array(
			"ID" => "vacancies",
			"NAME" => GetMessage("LEGACY_VACANCIES_PATH_CHILD"),
		),
	),
);
