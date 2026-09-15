<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 * @var CMain $APPLICATION
 * @var CUser $USER
 */

global $USER;

$baseUrl = $arResult['BASE_URL'];
?>
<div
	class="lv"
	id="legacy-vacancies"
	data-base="<?= htmlspecialcharsbx($baseUrl) ?>"
	data-fav-on="<?= htmlspecialcharsbx(GetMessage('LV_FAV_ON')) ?>"
	data-fav-off="<?= htmlspecialcharsbx(GetMessage('LV_FAV_OFF')) ?>"
	data-fav-error="<?= htmlspecialcharsbx(GetMessage('LV_FAV_ERROR')) ?>"
>

<?php if ($arResult['MODE'] === '404'): ?>

	<div class="lv-empty">
		<h1><?= GetMessage('LV_NOT_FOUND_TITLE') ?></h1>
		<p><?= htmlspecialcharsbx($arResult['ERROR']) ?></p>
		<p><a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage('LV_BACK_TO_LIST') ?></a></p>
	</div>

<?php elseif ($arResult['MODE'] === 'detail'):
	$arItem = $arResult['ITEM'];
	$responseCount = (int)$arItem['RESPONSE_COUNT'];
	$weekCount = (int)$arItem['WEEK_RESPONSE_COUNT'];
?>

	<div class="lv-detail">
		<div class="lv-main">
			<p class="lv-breadcrumbs">
				<a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage('LV_ALL_VACANCIES') ?></a>
				<?php if (strlen((string)$arItem['SECTION_NAME']) > 0): ?>
					/ <?php if (strlen((string)$arItem['SECTION_URL']) > 0): ?><a href="<?= htmlspecialcharsbx($arItem['SECTION_URL']) ?>"><?= $arItem['SECTION_NAME'] ?></a><?php else: ?><?= $arItem['SECTION_NAME'] ?><?php endif ?>
				<?php endif ?>
			</p>

			<h1 class="lv-title">
				<?= $arItem['NAME'] ?>
				<?php if ($arItem['HOT']): ?><span class="lv-badge lv-badge-hot"><?= GetMessage('LV_HOT') ?></span><?php endif ?>
				<?php if ($arItem['IS_NEW']): ?><span class="lv-badge lv-badge-new"><?= GetMessage('LV_NEW') ?></span><?php endif ?>
			</h1>

			<div class="lv-meta">
				<span class="lv-salary"><?= $arItem['SALARY_TEXT'] ?></span>
				<?php if (strlen((string)$arItem['CITY']) > 0): ?><span class="lv-city"><?= $arItem['CITY'] ?></span><?php endif ?>
				<?php if (strlen((string)$arItem['EXPERIENCE']) > 0): ?><span class="lv-exp"><?= $arItem['EXPERIENCE'] ?></span><?php endif ?>
				<span class="lv-date" title="<?= $arItem['DATE_FORMATTED'] ?>"><?= $arItem['DATE_TEXT'] ?></span>
			</div>

			<?php if (!empty($arItem['TAGS'])): ?>
				<div class="lv-tags">
					<?php foreach ($arItem['TAGS'] as $tag): ?>
						<a class="lv-tag" href="<?= htmlspecialcharsbx(legacy_build_url($baseUrl, [], ['q' => $tag])) ?>"><?= htmlspecialcharsbx($tag) ?></a>
					<?php endforeach ?>
				</div>
			<?php endif ?>

			<div class="lv-text">
				<?php if (strlen((string)$arItem['~PREVIEW_TEXT']) > 0): ?>
					<p class="lv-lead"><?= $arItem['PREVIEW_TEXT_TYPE'] === 'html' ? $arItem['~PREVIEW_TEXT'] : nl2br($arItem['PREVIEW_TEXT']) ?></p>
				<?php endif ?>
				<?php if (strlen((string)$arItem['~DETAIL_TEXT']) > 0): ?>
					<div class="lv-body"><?= $arItem['DETAIL_TEXT_TYPE'] === 'html' ? $arItem['~DETAIL_TEXT'] : nl2br($arItem['DETAIL_TEXT']) ?></div>
				<?php endif ?>
			</div>

			<div class="lv-stats">
				<span><?= GetMessage('LV_VIEWS') ?>: <b><?= (int)$arItem['VIEWS'] ?></b></span>
				<span><?= GetMessage('LV_RESPONSES') ?>: <b><?= $responseCount ?></b> <?= legacy_plural($responseCount, GetMessage('LV_RESP_1'), GetMessage('LV_RESP_2'), GetMessage('LV_RESP_5')) ?></span>
				<?php if ($weekCount > 0): ?><span class="lv-stats-week"><?= str_replace('#N#', (string)$weekCount, GetMessage('LV_RESPONSES_WEEK')) ?></span><?php endif ?>
				<a href="#" class="lv-fav <?= $arItem['IS_FAVORITE'] ? 'lv-fav-on' : '' ?>" data-id="<?= (int)$arItem['ID'] ?>">
					<?= $arItem['IS_FAVORITE'] ? GetMessage('LV_FAV_ON') : GetMessage('LV_FAV_OFF') ?>
				</a>
			</div>

			<?php if ($arParams['SHOW_FORM'] === 'Y'): ?>
				<div class="lv-form" id="respond">
					<h2><?= GetMessage('LV_FORM_TITLE') ?></h2>

					<?php if ($arResult['FORM']['SENT']): ?>
						<div class="lv-form-ok"><?= GetMessage('LEGACY_VACANCIES_FORM_OK') ?></div>
					<?php else: ?>
						<?php if (!empty($arResult['FORM']['ERRORS'])): ?>
							<div class="lv-form-errors">
								<?php foreach ($arResult['FORM']['ERRORS'] as $err): ?>
									<div><?= htmlspecialcharsbx($err) ?></div>
								<?php endforeach ?>
							</div>
						<?php endif ?>
						<?php
						$v = $arResult['FORM']['VALUES'];
						if (empty($v) && $USER->IsAuthorized())
						{
							$v = [
								'name' => $USER->GetFullName(),
								'email' => $USER->GetEmail(),
								'phone' => '',
								'message' => '',
							];
						}
						?>
						<form method="post" action="<?= htmlspecialcharsbx($arItem['URL']) ?>#respond" class="lv-form-body">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="legacy_respond" value="Y">
							<input type="hidden" name="vacancy_id" value="<?= (int)$arItem['ID'] ?>">
							<label>
								<span><?= GetMessage('LV_FORM_NAME') ?> *</span>
								<input type="text" name="name" value="<?= htmlspecialcharsbx(isset($v['name']) ? $v['name'] : '') ?>" maxlength="100">
							</label>
							<label>
								<span><?= GetMessage('LV_FORM_EMAIL') ?> *</span>
								<input type="text" name="email" value="<?= htmlspecialcharsbx(isset($v['email']) ? $v['email'] : '') ?>" maxlength="100">
							</label>
							<label>
								<span><?= GetMessage('LV_FORM_PHONE') ?></span>
								<input type="text" name="phone" value="<?= htmlspecialcharsbx(isset($v['phone']) ? $v['phone'] : '') ?>" maxlength="30">
							</label>
							<label>
								<span><?= GetMessage('LV_FORM_MESSAGE') ?> *</span>
								<textarea name="message" rows="5"><?= htmlspecialcharsbx(isset($v['message']) ? $v['message'] : '') ?></textarea>
							</label>
							<button type="submit" class="lv-btn"><?= GetMessage('LV_FORM_SUBMIT') ?></button>
						</form>
					<?php endif ?>
				</div>
			<?php endif ?>
		</div>

		<aside class="lv-side">
			<?php if (!empty($arResult['RELATED'])): ?>
				<div class="lv-box">
					<h3><?= GetMessage('LV_RELATED') ?></h3>
					<ul>
						<?php foreach ($arResult['RELATED'] as $arRelated): ?>
							<li>
								<a href="<?= htmlspecialcharsbx($arRelated['URL']) ?>"><?= $arRelated['NAME'] ?></a>
								<small><?= $arRelated['SALARY_TEXT'] ?><?php if (strlen((string)$arRelated['CITY']) > 0): ?> · <?= $arRelated['CITY'] ?><?php endif ?></small>
							</li>
						<?php endforeach ?>
					</ul>
				</div>
			<?php endif ?>

			<?php if (!empty($arResult['POPULAR'])): ?>
				<div class="lv-box">
					<h3><?= GetMessage('LV_POPULAR') ?></h3>
					<ol>
						<?php foreach ($arResult['POPULAR'] as $arPopular): ?>
							<li><a href="<?= htmlspecialcharsbx($arPopular['URL']) ?>"><?= $arPopular['NAME'] ?></a> <small>(<?= (int)$arPopular['VIEWS'] ?>)</small></li>
						<?php endforeach ?>
					</ol>
				</div>
			<?php endif ?>
		</aside>
	</div>

<?php else: ?>

	<div class="lv-list">
		<div class="lv-main">
			<h1 class="lv-title"><?= GetMessage('LV_LIST_TITLE') ?> <small>(<?= (int)$arResult['NAV']['TOTAL'] ?>)</small></h1>

			<form method="get" action="<?= htmlspecialcharsbx($baseUrl) ?>" class="lv-filter">
				<input type="text" name="q" value="<?= htmlspecialcharsbx($arResult['FILTER']['q']) ?>" placeholder="<?= GetMessage('LV_FILTER_Q') ?>">

				<select name="section">
					<option value=""><?= GetMessage('LV_FILTER_SECTION') ?></option>
					<?php foreach ($arResult['SECTIONS'] as $arSection): ?>
						<option value="<?= (int)$arSection['ID'] ?>" <?= $arSection['SELECTED'] ? 'selected' : '' ?>><?= $arSection['NAME'] ?> (<?= (int)$arSection['COUNT'] ?>)</option>
					<?php endforeach ?>
				</select>

				<select name="city">
					<option value=""><?= GetMessage('LV_FILTER_CITY') ?></option>
					<?php foreach ($arResult['CITIES'] as $id => $name): ?>
						<option value="<?= (int)$id ?>" <?= $arResult['FILTER']['city'] == $id ? 'selected' : '' ?>><?= htmlspecialcharsbx($name) ?></option>
					<?php endforeach ?>
				</select>

				<select name="exp">
					<option value=""><?= GetMessage('LV_FILTER_EXP') ?></option>
					<?php foreach ($arResult['EXPERIENCE'] as $id => $name): ?>
						<option value="<?= (int)$id ?>" <?= $arResult['FILTER']['exp'] == $id ? 'selected' : '' ?>><?= htmlspecialcharsbx($name) ?></option>
					<?php endforeach ?>
				</select>

				<input type="number" name="salary" value="<?= $arResult['FILTER']['salary'] > 0 ? (int)$arResult['FILTER']['salary'] : '' ?>" placeholder="<?= GetMessage('LV_FILTER_SALARY') ?>" step="10000" min="0">

				<label class="lv-check"><input type="checkbox" name="hot" value="Y" <?= $arResult['FILTER']['hot'] === 'Y' ? 'checked' : '' ?>> <?= GetMessage('LV_FILTER_HOT') ?></label>
				<label class="lv-check"><input type="checkbox" name="fav" value="Y" <?= $arResult['FILTER']['fav'] === 'Y' ? 'checked' : '' ?>> <?= GetMessage('LV_FILTER_FAV') ?></label>

				<?php if ($arResult['FILTER']['sort'] !== $arParams['DEFAULT_SORT']): ?>
					<input type="hidden" name="sort" value="<?= htmlspecialcharsbx($arResult['FILTER']['sort']) ?>">
				<?php endif ?>

				<button type="submit" class="lv-btn"><?= GetMessage('LV_FILTER_SUBMIT') ?></button>
				<?php if ($arResult['FILTER_ACTIVE']): ?>
					<a href="<?= htmlspecialcharsbx($arResult['RESET_URL']) ?>" class="lv-reset"><?= GetMessage('LV_FILTER_RESET') ?></a>
				<?php endif ?>
			</form>

			<div class="lv-sort">
				<?= GetMessage('LV_SORT') ?>:
				<?php foreach ($arResult['SORT_URLS'] as $sortCode => $sortUrl): ?>
					<?php if ($sortCode === $arResult['FILTER']['sort']): ?>
						<b><?= GetMessage('LV_SORT_' . strtoupper($sortCode)) ?></b>
					<?php else: ?>
						<a href="<?= htmlspecialcharsbx($sortUrl) ?>"><?= GetMessage('LV_SORT_' . strtoupper($sortCode)) ?></a>
					<?php endif ?>
				<?php endforeach ?>
			</div>

			<?php if (empty($arResult['ITEMS'])): ?>
				<div class="lv-empty">
					<p><?= GetMessage('LV_LIST_EMPTY') ?></p>
					<p><a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage('LV_FILTER_RESET') ?></a></p>
				</div>
			<?php else: ?>
				<div class="lv-items">
					<?php foreach ($arResult['ITEMS'] as $arItem):
						$cnt = (int)$arItem['RESPONSE_COUNT'];
					?>
						<div class="lv-item <?= $arItem['HOT'] ? 'lv-item-hot' : '' ?>" id="vacancy-<?= (int)$arItem['ID'] ?>">
							<div class="lv-item-head">
								<a class="lv-item-name" href="<?= htmlspecialcharsbx($arItem['URL']) ?>"><?= $arItem['NAME'] ?></a>
								<?php if ($arItem['HOT']): ?><span class="lv-badge lv-badge-hot"><?= GetMessage('LV_HOT') ?></span><?php endif ?>
								<?php if ($arItem['IS_NEW']): ?><span class="lv-badge lv-badge-new"><?= GetMessage('LV_NEW') ?></span><?php endif ?>
								<a href="#" class="lv-fav <?= $arItem['IS_FAVORITE'] ? 'lv-fav-on' : '' ?>" data-id="<?= (int)$arItem['ID'] ?>" title="<?= GetMessage('LV_FAV_TITLE') ?>">★</a>
							</div>
							<div class="lv-meta">
								<span class="lv-salary"><?= $arItem['SALARY_TEXT'] ?></span>
								<?php if (strlen((string)$arItem['CITY']) > 0): ?><span class="lv-city"><?= $arItem['CITY'] ?></span><?php endif ?>
								<?php if (strlen((string)$arItem['EXPERIENCE']) > 0): ?><span class="lv-exp"><?= $arItem['EXPERIENCE'] ?></span><?php endif ?>
								<?php if (strlen((string)$arItem['SECTION_NAME']) > 0): ?><a class="lv-section" href="<?= htmlspecialcharsbx($arItem['SECTION_URL']) ?>"><?= $arItem['SECTION_NAME'] ?></a><?php endif ?>
							</div>
							<?php if (strlen((string)$arItem['PREVIEW_TEXT']) > 0): ?>
								<p class="lv-item-text"><?= $arItem['PREVIEW_TEXT'] ?></p>
							<?php endif ?>
							<?php if (!empty($arItem['TAGS'])): ?>
								<div class="lv-tags">
									<?php foreach ($arItem['TAGS'] as $tag): ?>
										<a class="lv-tag" href="<?= htmlspecialcharsbx(legacy_build_url($baseUrl, [], ['q' => $tag])) ?>"><?= htmlspecialcharsbx($tag) ?></a>
									<?php endforeach ?>
								</div>
							<?php endif ?>
							<div class="lv-item-foot">
								<span title="<?= $arItem['DATE_FORMATTED'] ?>"><?= $arItem['DATE_TEXT'] ?></span>
								<span><?= GetMessage('LV_VIEWS') ?>: <?= (int)$arItem['VIEWS'] ?></span>
								<span><?= $cnt ?> <?= legacy_plural($cnt, GetMessage('LV_RESP_1'), GetMessage('LV_RESP_2'), GetMessage('LV_RESP_5')) ?></span>
							</div>
						</div>
					<?php endforeach ?>
				</div>

				<?php if ($arResult['NAV']['PAGES'] > 1): ?>
					<div class="lv-nav">
						<?php if (strlen((string)$arResult['NAV']['PREV_URL']) > 0): ?>
							<a href="<?= htmlspecialcharsbx($arResult['NAV']['PREV_URL']) ?>" class="lv-nav-prev">&larr; <?= GetMessage('LV_NAV_PREV') ?></a>
						<?php endif ?>
						<?php foreach ($arResult['NAV']['URLS'] as $pageNum => $pageUrl): ?>
							<?php if ($pageNum == $arResult['NAV']['PAGE']): ?>
								<b class="lv-nav-cur"><?= $pageNum ?></b>
							<?php else: ?>
								<a href="<?= htmlspecialcharsbx($pageUrl) ?>"><?= $pageNum ?></a>
							<?php endif ?>
						<?php endforeach ?>
						<?php if (strlen((string)$arResult['NAV']['NEXT_URL']) > 0): ?>
							<a href="<?= htmlspecialcharsbx($arResult['NAV']['NEXT_URL']) ?>" class="lv-nav-next"><?= GetMessage('LV_NAV_NEXT') ?> &rarr;</a>
						<?php endif ?>
					</div>
				<?php endif ?>
			<?php endif ?>
		</div>

		<aside class="lv-side">
			<div class="lv-box">
				<h3><?= GetMessage('LV_SECTIONS') ?></h3>
				<ul>
					<?php foreach ($arResult['SECTIONS'] as $arSection): ?>
						<li <?= $arSection['SELECTED'] ? 'class="lv-sel"' : '' ?>><a href="<?= htmlspecialcharsbx($arSection['URL']) ?>"><?= $arSection['NAME'] ?></a> <small>(<?= (int)$arSection['COUNT'] ?>)</small></li>
					<?php endforeach ?>
				</ul>
			</div>

			<?php if (!empty($arResult['POPULAR'])): ?>
				<div class="lv-box">
					<h3><?= GetMessage('LV_POPULAR') ?></h3>
					<ol>
						<?php foreach ($arResult['POPULAR'] as $arPopular): ?>
							<li><a href="<?= htmlspecialcharsbx($arPopular['URL']) ?>"><?= $arPopular['NAME'] ?></a> <small>(<?= (int)$arPopular['VIEWS'] ?>)</small></li>
						<?php endforeach ?>
					</ol>
				</div>
			<?php endif ?>

			<?php if (!empty($arResult['WEEK_SUMMARY'])): ?>
				<div class="lv-box lv-box-muted">
					<?= str_replace(
						['#N#', '#V#'],
						[(string)(int)$arResult['WEEK_SUMMARY']['total'], (string)(int)$arResult['WEEK_SUMMARY']['vacancies']],
						GetMessage('LV_WEEK_SUMMARY')
					) ?>
				</div>
			<?php endif ?>
		</aside>
	</div>

<?php endif ?>

</div>
