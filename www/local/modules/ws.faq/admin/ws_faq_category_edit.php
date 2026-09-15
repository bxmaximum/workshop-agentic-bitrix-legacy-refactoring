<?php

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

use Bitrix\Main\Context;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;
use Ws\Faq\Service\CategoryService;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin())
{
	$APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

if (!Loader::includeModule('ws.faq'))
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
	ShowError(Loc::getMessage('WS_FAQ_MODULE_NOT_INSTALLED'));
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
	return;
}

$request = Context::getCurrent()->getRequest();
/** @var CategoryService $categoryService */
$categoryService = ServiceLocator::getInstance()->get(CategoryService::class);

$id = (int)$request->get('ID');
$errors = [];
$fields = [
	'IS_ACTIVE' => true,
	'SORT' => 500,
	'NAME' => '',
	'CODE' => '',
];

if ($id > 0)
{
	$existing = $categoryService->getById($id);
	if ($existing === null)
	{
		require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
		CAdminMessage::ShowMessage(Loc::getMessage('WS_FAQ_CATEGORY_EDIT_NOT_FOUND'));
		require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
		return;
	}

	$fields = [
		'IS_ACTIVE' => $existing->isActive,
		'SORT' => $existing->sort,
		'NAME' => $existing->name,
		'CODE' => $existing->code ?? '',
	];
}

if ($request->isPost() && check_bitrix_sessid() && ($request->getPost('save') !== null || $request->getPost('apply') !== null))
{
	$fields = [
		'IS_ACTIVE' => $request->getPost('IS_ACTIVE') === 'Y',
		'SORT' => (int)$request->getPost('SORT'),
		'NAME' => (string)$request->getPost('NAME'),
		'CODE' => (string)$request->getPost('CODE'),
	];

	$result = $categoryService->save($id > 0 ? $id : null, $fields);
	if ($result->isSuccess())
	{
		$savedId = (int)($result->getData()['id'] ?? 0);
		if ($request->getPost('save') !== null)
		{
			LocalRedirect('ws_faq_category_list.php?lang=' . LANGUAGE_ID);
		}

		LocalRedirect('ws_faq_category_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $savedId . '&mess=ok');
	}

	$errors = $result->getErrorMessages();
}

$aTabs = [
	[
		'DIV' => 'edit1',
		'TAB' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_TAB'),
		'ICON' => 'main_user_edit',
		'TITLE' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_TAB_TITLE'),
	],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$APPLICATION->SetTitle(
	$id > 0
		? Loc::getMessage('WS_FAQ_CATEGORY_EDIT_TITLE_EDIT')
		: Loc::getMessage('WS_FAQ_CATEGORY_EDIT_TITLE_ADD')
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$menu = [
	[
		'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_LIST'),
		'LINK' => 'ws_faq_category_list.php?lang=' . LANGUAGE_ID,
		'ICON' => 'btn_list',
	],
];

if ($id > 0)
{
	$menu[] = ['SEPARATOR' => true];
	$menu[] = [
		'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_ADD'),
		'LINK' => 'ws_faq_category_edit.php?lang=' . LANGUAGE_ID,
		'ICON' => 'btn_new',
	];
	$menu[] = [
		'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_DELETE'),
		'LINK' => "javascript:if(confirm('" . CUtil::JSEscape(Loc::getMessage('WS_FAQ_CATEGORY_EDIT_DELETE_CONFIRM'))
			. "')) window.location='ws_faq_category_list.php?action_button=delete&ID=" . $id
			. '&lang=' . LANGUAGE_ID . '&' . bitrix_sessid_get() . "';",
		'ICON' => 'btn_delete',
	];
}

(new CAdminContextMenu($menu))->Show();

if ($request->get('mess') === 'ok')
{
	CAdminMessage::ShowMessage([
		'MESSAGE' => Loc::getMessage('WS_FAQ_CATEGORY_EDIT_SAVED'),
		'TYPE' => 'OK',
	]);
}

if ($errors !== [])
{
	CAdminMessage::ShowMessage(implode("\n", $errors));
}
?>
<form method="POST" action="<?= HtmlFilter::encode($APPLICATION->GetCurPage()) ?>?lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>
	<input type="hidden" name="ID" value="<?= $id ?>">
	<?php
	$tabControl->Begin();
	$tabControl->BeginNextTab();
	?>
	<?php if ($id > 0): ?>
		<tr>
			<td width="40%">ID:</td>
			<td width="60%"><?= $id ?></td>
		</tr>
	<?php endif; ?>
	<tr>
		<td width="40%"><label for="IS_ACTIVE"><?= Loc::getMessage('WS_FAQ_CATEGORY_EDIT_ACTIVE') ?>:</label></td>
		<td width="60%">
			<input type="checkbox" name="IS_ACTIVE" id="IS_ACTIVE" value="Y"<?= $fields['IS_ACTIVE'] ? ' checked' : '' ?>>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('WS_FAQ_CATEGORY_EDIT_SORT') ?>:</td>
		<td>
			<input type="text" name="SORT" size="10" value="<?= (int)$fields['SORT'] ?>">
		</td>
	</tr>
	<tr class="adm-detail-required-field">
		<td><?= Loc::getMessage('WS_FAQ_CATEGORY_EDIT_NAME') ?>:</td>
		<td>
			<input type="text" name="NAME" size="50" maxlength="255" value="<?= HtmlFilter::encode($fields['NAME']) ?>">
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('WS_FAQ_CATEGORY_EDIT_CODE') ?>:</td>
		<td>
			<input type="text" name="CODE" size="50" maxlength="50" value="<?= HtmlFilter::encode($fields['CODE']) ?>">
		</td>
	</tr>
	<?php
	$tabControl->Buttons([
		'back_url' => 'ws_faq_category_list.php?lang=' . LANGUAGE_ID,
	]);
	$tabControl->End();
	?>
</form>
<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
