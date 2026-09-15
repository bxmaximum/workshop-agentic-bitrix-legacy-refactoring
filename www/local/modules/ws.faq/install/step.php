<?php

/** @var CMain $APPLICATION */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

if (!check_bitrix_sessid())
{
	return;
}

echo CAdminMessage::ShowNote(GetMessage('WS_FAQ_INSTALL_TITLE'));
?>
