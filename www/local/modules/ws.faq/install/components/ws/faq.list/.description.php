<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = [
	'NAME' => Loc::getMessage('WS_FAQ_LIST_NAME') ?: 'Список FAQ',
	'DESCRIPTION' => Loc::getMessage('WS_FAQ_LIST_DESC') ?: 'Публичный список вопросов и ответов с голосованием',
	'CACHE_PATH' => 'Y',
	'COMPLEX' => 'N',
	'PATH' => [
		'ID' => 'content',
		'CHILD' => [
			'ID' => 'ws_faq',
			'NAME' => 'FAQ',
		],
	],
];
