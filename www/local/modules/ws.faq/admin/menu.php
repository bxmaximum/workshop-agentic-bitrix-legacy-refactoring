<?php

use Bitrix\Main\Localization\Loc;

/** @global CUser $USER */

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin())
{
	return false;
}

return [
	'parent_menu' => 'global_menu_services',
	'section' => 'ws_faq',
	'sort' => 500,
	'text' => Loc::getMessage('WS_FAQ_MENU_TITLE'),
	'title' => Loc::getMessage('WS_FAQ_MENU_TITLE'),
	'icon' => 'sys_menu_icon',
	'page_icon' => 'sys_page_icon',
	'items_id' => 'menu_ws_faq',
	'items' => [
		[
			'text' => Loc::getMessage('WS_FAQ_MENU_QUESTIONS'),
			'title' => Loc::getMessage('WS_FAQ_MENU_QUESTIONS'),
			'url' => 'ws_faq_question_list.php?lang=' . LANGUAGE_ID,
			'more_url' => [
				'ws_faq_question_list.php',
				'ws_faq_question_edit.php',
			],
		],
		[
			'text' => Loc::getMessage('WS_FAQ_MENU_CATEGORIES'),
			'title' => Loc::getMessage('WS_FAQ_MENU_CATEGORIES'),
			'url' => 'ws_faq_category_list.php?lang=' . LANGUAGE_ID,
			'more_url' => [
				'ws_faq_category_list.php',
				'ws_faq_category_edit.php',
			],
		],
	],
];
