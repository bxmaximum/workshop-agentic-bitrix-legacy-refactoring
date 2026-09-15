<?php

/** @var CMain $APPLICATION */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
	<?= bitrix_sessid_post() ?>
	<input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
	<input type="hidden" name="id" value="ws.vacancies">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	<p>
		<label>
			<input type="checkbox" name="savedata" value="Y" checked>
			<?= Loc::getMessage('WS_VACANCIES_UNINSTALL_SAVE_TABLES') ?>
		</label>
	</p>
	<input type="submit" name="inst" value="<?= Loc::getMessage('MOD_UNINST_DEL') ?>">
</form>
