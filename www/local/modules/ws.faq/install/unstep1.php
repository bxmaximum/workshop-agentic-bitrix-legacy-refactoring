<?php

/** @var CMain $APPLICATION */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

Loc::loadMessages(__DIR__ . '/index.php');
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
	<?= bitrix_sessid_post() ?>
	<input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
	<input type="hidden" name="id" value="ws.faq">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	<p>
		<label>
			<input type="checkbox" name="savedata" value="Y" checked>
			<?= htmlspecialcharsbx((string)Loc::getMessage('WS_FAQ_UNINSTALL_SAVE_TABLES')) ?>
		</label>
	</p>
	<input type="submit" name="inst" value="<?= htmlspecialcharsbx(GetMessage('MOD_UNINST_DEL')) ?>">
</form>
