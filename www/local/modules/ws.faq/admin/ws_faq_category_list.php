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
use Bitrix\Main\UI\AdminPageNavigation;
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

$tableId = 'tbl_ws_faq_category';
$sorting = new CAdminSorting($tableId, 'SORT', 'asc');
$adminList = new CAdminList($tableId, $sorting);

$filterFields = [
	'find',
	'find_is_active',
];
$filterValues = $adminList->InitFilter($filterFields);

$adminFilter = [];
$search = trim((string)($filterValues['find'] ?? ''));
if ($search !== '')
{
	$adminFilter['search'] = $search;
}

$findIsActive = (string)($filterValues['find_is_active'] ?? '');
if ($findIsActive === 'Y')
{
	$adminFilter['isActive'] = true;
}
elseif ($findIsActive === 'N')
{
	$adminFilter['isActive'] = false;
}

if (($ids = $adminList->GroupAction()) !== false)
{
	if ($request->get('action_target') === 'selected')
	{
		$ids = $categoryService->listIds($adminFilter);
	}

	$action = (string)($request->get('action_button') ?: $request->get('action'));

	foreach ($ids as $id)
	{
		$id = (int)$id;
		if ($id <= 0)
		{
			continue;
		}

		$result = match ($action)
		{
			'delete' => $categoryService->delete($id),
			'activate' => $categoryService->setActive($id, true),
			'deactivate' => $categoryService->setActive($id, false),
			default => null,
		};

		if ($result !== null && !$result->isSuccess())
		{
			$adminList->AddGroupError(implode('<br>', $result->getErrorMessages()), $id);
		}
	}
}

$sortBy = strtoupper((string)$sorting->getField());
$sortOrder = strtoupper((string)$sorting->getOrder()) === 'DESC' ? 'DESC' : 'ASC';
$order = [$sortBy => $sortOrder];
if ($sortBy !== 'ID')
{
	$order['ID'] = 'ASC';
}

$nav = new AdminPageNavigation('nav-ws-faq-category');
$listData = $categoryService->listAdmin(
	$adminFilter,
	$order,
	(int)$nav->getLimit(),
	(int)$nav->getOffset()
);
$nav->setRecordCount($listData['total']);
$adminList->setNavigation($nav, Loc::getMessage('WS_FAQ_CATEGORY_LIST_PAGES'));

$adminList->AddHeaders([
	['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true, 'align' => 'right'],
	['id' => 'IS_ACTIVE', 'content' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_ACTIVE'), 'sort' => 'IS_ACTIVE', 'default' => true],
	['id' => 'SORT', 'content' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_SORT'), 'sort' => 'SORT', 'default' => true],
	['id' => 'NAME', 'content' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_NAME'), 'sort' => 'NAME', 'default' => true],
	['id' => 'CODE', 'content' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_CODE'), 'sort' => 'CODE', 'default' => true],
]);

foreach ($listData['items'] as $item)
{
	$editUrl = 'ws_faq_category_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $item->id;
	$rowData = [
		'ID' => $item->id,
		'IS_ACTIVE' => $item->isActive ? 'Y' : 'N',
		'SORT' => $item->sort,
		'NAME' => $item->name,
		'CODE' => $item->code ?? '',
	];

	$row =& $adminList->AddRow($item->id, $rowData, $editUrl, Loc::getMessage('WS_FAQ_CATEGORY_LIST_EDIT'));
	$row->AddViewField('ID', (string)$item->id);
	$row->AddCheckField('IS_ACTIVE', false);
	$row->AddViewField('SORT', (string)$item->sort);
	$row->AddViewField(
		'NAME',
		'<a href="' . HtmlFilter::encode($editUrl) . '">' . HtmlFilter::encode($item->name) . '</a>'
	);
	$row->AddViewField('CODE', HtmlFilter::encode((string)$item->code));

	$row->AddActions([
		[
			'ICON' => 'edit',
			'DEFAULT' => true,
			'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_EDIT'),
			'ACTION' => $adminList->ActionRedirect($editUrl),
		],
		[
			'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_ACTIVATE'),
			'ACTION' => $adminList->ActionDoGroup($item->id, 'activate'),
		],
		[
			'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_DEACTIVATE'),
			'ACTION' => $adminList->ActionDoGroup($item->id, 'deactivate'),
		],
		['SEPARATOR' => true],
		[
			'ICON' => 'delete',
			'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_DELETE'),
			'ACTION' => "if(confirm('" . CUtil::JSEscape(Loc::getMessage('WS_FAQ_CATEGORY_LIST_DELETE_CONFIRM')) . "')) "
				. $adminList->ActionDoGroup($item->id, 'delete'),
		],
	]);
}
unset($row);

$adminList->AddGroupActionTable([
	'delete' => true,
	'activate' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_ACTIVATE'),
	'deactivate' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_DEACTIVATE'),
]);

$adminList->AddAdminContextMenu([
	[
		'TEXT' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_ADD'),
		'LINK' => 'ws_faq_category_edit.php?lang=' . LANGUAGE_ID,
		'TITLE' => Loc::getMessage('WS_FAQ_CATEGORY_LIST_ADD'),
		'ICON' => 'btn_new',
	],
]);

$adminList->CheckListMode();

$APPLICATION->SetTitle(Loc::getMessage('WS_FAQ_CATEGORY_LIST_TITLE'));

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<form name="find_form" method="GET" action="<?= HtmlFilter::encode($APPLICATION->GetCurPage()) ?>">
	<?php
	$filter = new CAdminFilter(
		$tableId . '_filter',
		[
			Loc::getMessage('WS_FAQ_CATEGORY_LIST_FILTER_ACTIVE'),
		]
	);
	$filter->Begin();
	?>
	<tr>
		<td><b><?= Loc::getMessage('WS_FAQ_CATEGORY_LIST_FILTER_FIND') ?>:</b></td>
		<td>
			<input type="text" name="find" size="40" value="<?= HtmlFilter::encode($search) ?>">
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('WS_FAQ_CATEGORY_LIST_FILTER_ACTIVE') ?>:</td>
		<td>
			<select name="find_is_active">
				<option value=""><?= Loc::getMessage('WS_FAQ_CATEGORY_LIST_FILTER_ANY') ?></option>
				<option value="Y"<?= $findIsActive === 'Y' ? ' selected' : '' ?>><?= Loc::getMessage('MAIN_YES') ?></option>
				<option value="N"<?= $findIsActive === 'N' ? ' selected' : '' ?>><?= Loc::getMessage('MAIN_NO') ?></option>
			</select>
		</td>
	</tr>
	<?php
	$filter->Buttons([
		'table_id' => $tableId,
		'url' => $APPLICATION->GetCurPage(),
		'form' => 'find_form',
	]);
	$filter->End();
	?>
</form>
<?php

$adminList->DisplayList();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
