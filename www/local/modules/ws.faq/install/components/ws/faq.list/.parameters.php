<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
	'GROUPS' => [
		'SETTINGS' => [
			'NAME' => Loc::getMessage('WS_FAQ_LIST_GROUP_SETTINGS') ?: 'Настройки',
			'SORT' => 100,
		],
	],
	'PARAMETERS' => [
		'SHOW_COUNTERS' => [
			'PARENT' => 'SETTINGS',
			'NAME' => Loc::getMessage('WS_FAQ_LIST_SHOW_COUNTERS') ?: 'Показывать счётчики голосов',
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
		],
		'CATEGORY_ID' => [
			'PARENT' => 'SETTINGS',
			'NAME' => Loc::getMessage('WS_FAQ_LIST_CATEGORY_ID') ?: 'Фильтр по категории (ID, 0 = все)',
			'TYPE' => 'STRING',
			'DEFAULT' => '0',
		],
		'CACHE_TIME' => ['DEFAULT' => 3600],
	],
];
