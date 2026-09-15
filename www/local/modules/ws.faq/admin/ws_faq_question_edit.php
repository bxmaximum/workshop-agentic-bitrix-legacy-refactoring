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
use Ws\Faq\Service\QuestionService;

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

Loader::includeModule('fileman');

$request = Context::getCurrent()->getRequest();
$locator = ServiceLocator::getInstance();
/** @var QuestionService $questionService */
$questionService = $locator->get(QuestionService::class);
/** @var CategoryService $categoryService */
$categoryService = $locator->get(CategoryService::class);

$id = (int)$request->get('ID');
$errors = [];
$fields = [
	'IS_ACTIVE' => true,
	'SORT' => 500,
	'CATEGORY_ID' => null,
	'QUESTION' => '',
	'ANSWER' => '',
];

if ($id > 0)
{
	$existing = $questionService->getById($id);
	if ($existing === null)
	{
		require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
		CAdminMessage::ShowMessage(Loc::getMessage('WS_FAQ_QUESTION_EDIT_NOT_FOUND'));
		require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
		return;
	}

	$fields = [
		'IS_ACTIVE' => $existing->isActive,
		'SORT' => $existing->sort,
		'CATEGORY_ID' => $existing->categoryId,
		'QUESTION' => $existing->question,
		'ANSWER' => $existing->answer,
	];
}

if ($request->isPost() && check_bitrix_sessid() && ($request->getPost('save') !== null || $request->getPost('apply') !== null))
{
	$categoryIdRaw = $request->getPost('CATEGORY_ID');
	$categoryId = ($categoryIdRaw === '' || $categoryIdRaw === null) ? null : (int)$categoryIdRaw;

	$fields = [
		'IS_ACTIVE' => $request->getPost('IS_ACTIVE') === 'Y',
		'SORT' => (int)$request->getPost('SORT'),
		'CATEGORY_ID' => $categoryId,
		'QUESTION' => (string)$request->getPost('QUESTION'),
		'ANSWER' => (string)$request->getPost('ANSWER'),
	];

	$result = $questionService->save($id > 0 ? $id : null, $fields);
	if ($result->isSuccess())
	{
		$savedId = (int)($result->getData()['id'] ?? 0);
		if ($request->getPost('save') !== null)
		{
			LocalRedirect('ws_faq_question_list.php?lang=' . LANGUAGE_ID);
		}

		LocalRedirect('ws_faq_question_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $savedId . '&mess=ok');
	}

	$errors = $result->getErrorMessages();
}

$aTabs = [
	[
		'DIV' => 'edit1',
		'TAB' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_TAB_QUESTION'),
		'ICON' => 'main_user_edit',
		'TITLE' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_TAB_QUESTION_TITLE'),
	],
];

if ($id > 0)
{
	$aTabs[] = [
		'DIV' => 'edit2',
		'TAB' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_TAB_STATS'),
		'ICON' => 'main_user_edit',
		'TITLE' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_TAB_STATS_TITLE'),
	];
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$APPLICATION->SetTitle(
	$id > 0
		? Loc::getMessage('WS_FAQ_QUESTION_EDIT_TITLE_EDIT')
		: Loc::getMessage('WS_FAQ_QUESTION_EDIT_TITLE_ADD')
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$menu = [
	[
		'TEXT' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_LIST'),
		'LINK' => 'ws_faq_question_list.php?lang=' . LANGUAGE_ID,
		'ICON' => 'btn_list',
	],
];

if ($id > 0)
{
	$menu[] = ['SEPARATOR' => true];
	$menu[] = [
		'TEXT' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_ADD'),
		'LINK' => 'ws_faq_question_edit.php?lang=' . LANGUAGE_ID,
		'ICON' => 'btn_new',
	];
	$menu[] = [
		'TEXT' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_DELETE'),
		'LINK' => "javascript:if(confirm('" . CUtil::JSEscape(Loc::getMessage('WS_FAQ_QUESTION_EDIT_DELETE_CONFIRM'))
			. "')) window.location='ws_faq_question_list.php?action_button=delete&ID=" . $id
			. '&lang=' . LANGUAGE_ID . '&' . bitrix_sessid_get() . "';",
		'ICON' => 'btn_delete',
	];
}

(new CAdminContextMenu($menu))->Show();

if ($request->get('mess') === 'ok')
{
	CAdminMessage::ShowMessage([
		'MESSAGE' => Loc::getMessage('WS_FAQ_QUESTION_EDIT_SAVED'),
		'TYPE' => 'OK',
	]);
}

if ($errors !== [])
{
	CAdminMessage::ShowMessage(implode("\n", $errors));
}

$categories = $categoryService->listAll();
$stats = $id > 0 ? $questionService->getVoteStats($id) : null;
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
		<td width="40%"><label for="IS_ACTIVE"><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_ACTIVE') ?>:</label></td>
		<td width="60%">
			<input type="checkbox" name="IS_ACTIVE" id="IS_ACTIVE" value="Y"<?= $fields['IS_ACTIVE'] ? ' checked' : '' ?>>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_SORT') ?>:</td>
		<td>
			<input type="text" name="SORT" size="10" value="<?= (int)$fields['SORT'] ?>">
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_CATEGORY') ?>:</td>
		<td>
			<select name="CATEGORY_ID">
				<option value=""><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_CATEGORY_NONE') ?></option>
				<?php foreach ($categories as $category): ?>
					<option value="<?= (int)$category->id ?>"<?= (int)$fields['CATEGORY_ID'] === $category->id ? ' selected' : '' ?>>
						<?= HtmlFilter::encode($category->name) ?><?= !$category->isActive ? ' [' . Loc::getMessage('WS_FAQ_QUESTION_EDIT_INACTIVE') . ']' : '' ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr class="adm-detail-required-field">
		<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_QUESTION') ?>:</td>
		<td>
			<input type="text" name="QUESTION" size="80" maxlength="500" value="<?= HtmlFilter::encode($fields['QUESTION']) ?>">
		</td>
	</tr>
	<tr class="adm-detail-required-field heading">
		<td colspan="2"><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_ANSWER') ?></td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<?php
			CFileMan::AddHTMLEditorFrame(
				'ANSWER',
				$fields['ANSWER'],
				'ANSWER_TYPE',
				'html',
				[
					'height' => 400,
					'width' => '100%',
				]
			);
			?>
		</td>
	</tr>
	<?php
	if ($id > 0 && $stats !== null)
	{
		$tabControl->BeginNextTab();
		?>
		<tr>
			<td width="40%"><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_TOTAL') ?>:</td>
			<td width="60%"><?= (int)$stats->totalVotes ?></td>
		</tr>
		<tr>
			<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_USEFUL') ?>:</td>
			<td><?= (int)$stats->usefulCount ?></td>
		</tr>
		<tr>
			<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_NOT_USEFUL') ?>:</td>
			<td><?= (int)$stats->notUsefulCount ?></td>
		</tr>
		<tr>
			<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_CONVERSION') ?>:</td>
			<td><?= HtmlFilter::encode((string)$stats->usefulPercent) ?>%</td>
		</tr>
		<tr>
			<td><?= Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_LAST_VOTE') ?>:</td>
			<td>
				<?= $stats->lastVoteAt !== null
					? HtmlFilter::encode($stats->lastVoteAt)
					: Loc::getMessage('WS_FAQ_QUESTION_EDIT_STATS_NO_VOTES') ?>
			</td>
		</tr>
		<?php
	}

	$tabControl->Buttons([
		'back_url' => 'ws_faq_question_list.php?lang=' . LANGUAGE_ID,
	]);
	$tabControl->End();
	?>
</form>
<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
