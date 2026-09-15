<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 * @var string $templateFolder
 * @var CMain $APPLICATION
 * @var CDatabase $DB
 */

global $DB, $USER;

$baseUrl = $arResult["BASE_URL"];
?>
<div class="lv" id="legacy-vacancies" data-base="<?= htmlspecialcharsbx($baseUrl) ?>">

<? if ($arResult["MODE"] == "404"): ?>

	<div class="lv-empty">
		<h1><?= GetMessage("LV_NOT_FOUND_TITLE") ?></h1>
		<p><?= htmlspecialcharsbx($arResult["ERROR"]) ?></p>
		<p><a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage("LV_BACK_TO_LIST") ?></a></p>
	</div>

<? elseif ($arResult["MODE"] == "detail"):
	$arItem = $arResult["ITEM"];

	// сколько откликов — считаем прямо здесь, в компоненте этого нет
	$responseCount = legacy_get_response_count($arItem["ID"]);

	// сколько откликов за последнюю неделю — HR просили показывать "горячесть"
	$rsWeek = $DB->Query(
		"SELECT COUNT(*) CNT FROM " . LEGACY_RESPONSE_TABLE
		. " WHERE VACANCY_ID = " . intval($arItem["ID"])
		. " AND CREATED >= DATE_SUB(" . $DB->GetNowFunction() . ", INTERVAL 7 DAY)",
		false,
		"File: " . __FILE__ . "<br>Line: " . __LINE__
	);
	$weekCount = 0;
	if ($rsWeek && ($arWeek = $rsWeek->Fetch())) {
		$weekCount = intval($arWeek["CNT"]);
	}
?>

	<div class="lv-detail">
		<div class="lv-main">
			<p class="lv-breadcrumbs">
				<a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage("LV_ALL_VACANCIES") ?></a>
				<? if (strlen($arItem["SECTION_NAME"]) > 0): ?>
					/ <? if (strlen($arItem["SECTION_URL"]) > 0): ?><a href="<?= htmlspecialcharsbx($arItem["SECTION_URL"]) ?>"><?= $arItem["SECTION_NAME"] ?></a><? else: ?><?= $arItem["SECTION_NAME"] ?><? endif ?>
				<? endif ?>
			</p>

			<h1 class="lv-title">
				<?= $arItem["NAME"] ?>
				<? if ($arItem["HOT"]): ?><span class="lv-badge lv-badge-hot"><?= GetMessage("LV_HOT") ?></span><? endif ?>
				<? if ($arItem["IS_NEW"]): ?><span class="lv-badge lv-badge-new"><?= GetMessage("LV_NEW") ?></span><? endif ?>
			</h1>

			<div class="lv-meta">
				<span class="lv-salary"><?= $arItem["SALARY_TEXT"] ?></span>
				<? if (strlen($arItem["CITY"]) > 0): ?><span class="lv-city"><?= $arItem["CITY"] ?></span><? endif ?>
				<? if (strlen($arItem["EXPERIENCE"]) > 0): ?><span class="lv-exp"><?= $arItem["EXPERIENCE"] ?></span><? endif ?>
				<span class="lv-date" title="<?= $arItem["DATE_FORMATTED"] ?>"><?= $arItem["DATE_TEXT"] ?></span>
			</div>

			<? if (!empty($arItem["TAGS"])): ?>
				<div class="lv-tags">
					<? foreach ($arItem["TAGS"] as $tag): ?>
						<a class="lv-tag" href="<?= htmlspecialcharsbx(legacy_build_url($baseUrl, array(), array("q" => $tag))) ?>"><?= htmlspecialcharsbx($tag) ?></a>
					<? endforeach ?>
				</div>
			<? endif ?>

			<div class="lv-text">
				<? if (strlen((string)$arItem["~PREVIEW_TEXT"]) > 0): ?>
					<p class="lv-lead"><?= $arItem["PREVIEW_TEXT_TYPE"] == "html" ? $arItem["~PREVIEW_TEXT"] : nl2br($arItem["PREVIEW_TEXT"]) ?></p>
				<? endif ?>
				<? if (strlen((string)$arItem["~DETAIL_TEXT"]) > 0): ?>
					<div class="lv-body"><?= $arItem["DETAIL_TEXT_TYPE"] == "html" ? $arItem["~DETAIL_TEXT"] : nl2br($arItem["DETAIL_TEXT"]) ?></div>
				<? endif ?>
			</div>

			<div class="lv-stats">
				<span><?= GetMessage("LV_VIEWS") ?>: <b><?= intval($arItem["VIEWS"]) ?></b></span>
				<span><?= GetMessage("LV_RESPONSES") ?>: <b><?= $responseCount ?></b> <?= legacy_plural($responseCount, GetMessage("LV_RESP_1"), GetMessage("LV_RESP_2"), GetMessage("LV_RESP_5")) ?></span>
				<? if ($weekCount > 0): ?><span class="lv-stats-week"><?= str_replace("#N#", $weekCount, GetMessage("LV_RESPONSES_WEEK")) ?></span><? endif ?>
				<a href="#" class="lv-fav <?= $arItem["IS_FAVORITE"] ? "lv-fav-on" : "" ?>" data-id="<?= intval($arItem["ID"]) ?>">
					<?= $arItem["IS_FAVORITE"] ? GetMessage("LV_FAV_ON") : GetMessage("LV_FAV_OFF") ?>
				</a>
			</div>

			<? if ($arParams["SHOW_FORM"] == "Y"): ?>
				<div class="lv-form" id="respond">
					<h2><?= GetMessage("LV_FORM_TITLE") ?></h2>

					<? if ($arResult["FORM"]["SENT"]): ?>
						<div class="lv-form-ok"><?= GetMessage("LEGACY_VACANCIES_FORM_OK") ?></div>
					<? else: ?>
						<? if (!empty($arResult["FORM"]["ERRORS"])): ?>
							<div class="lv-form-errors">
								<? foreach ($arResult["FORM"]["ERRORS"] as $err): ?>
									<div><?= htmlspecialcharsbx($err) ?></div>
								<? endforeach ?>
							</div>
						<? endif ?>
						<?
						$v = $arResult["FORM"]["VALUES"];
						if (empty($v) && $USER->IsAuthorized()) {
							$v = array("name" => $USER->GetFullName(), "email" => $USER->GetEmail(), "phone" => "", "message" => "");
						}
						?>
						<form method="post" action="<?= htmlspecialcharsbx($arItem["URL"]) ?>#respond" class="lv-form-body">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="legacy_respond" value="Y">
							<input type="hidden" name="vacancy_id" value="<?= intval($arItem["ID"]) ?>">
							<label>
								<span><?= GetMessage("LV_FORM_NAME") ?> *</span>
								<input type="text" name="name" value="<?= htmlspecialcharsbx(isset($v["name"]) ? $v["name"] : "") ?>" maxlength="100">
							</label>
							<label>
								<span><?= GetMessage("LV_FORM_EMAIL") ?> *</span>
								<input type="text" name="email" value="<?= htmlspecialcharsbx(isset($v["email"]) ? $v["email"] : "") ?>" maxlength="100">
							</label>
							<label>
								<span><?= GetMessage("LV_FORM_PHONE") ?></span>
								<input type="text" name="phone" value="<?= htmlspecialcharsbx(isset($v["phone"]) ? $v["phone"] : "") ?>" maxlength="30">
							</label>
							<label>
								<span><?= GetMessage("LV_FORM_MESSAGE") ?> *</span>
								<textarea name="message" rows="5"><?= htmlspecialcharsbx(isset($v["message"]) ? $v["message"] : "") ?></textarea>
							</label>
							<button type="submit" class="lv-btn"><?= GetMessage("LV_FORM_SUBMIT") ?></button>
						</form>
					<? endif ?>
				</div>
			<? endif ?>
		</div>

		<aside class="lv-side">
			<? if (!empty($arResult["RELATED"])): ?>
				<div class="lv-box">
					<h3><?= GetMessage("LV_RELATED") ?></h3>
					<ul>
						<? foreach ($arResult["RELATED"] as $arRelated): ?>
							<li>
								<a href="<?= htmlspecialcharsbx($arRelated["URL"]) ?>"><?= $arRelated["NAME"] ?></a>
								<small><?= $arRelated["SALARY_TEXT"] ?><? if (strlen($arRelated["CITY"]) > 0): ?> · <?= $arRelated["CITY"] ?><? endif ?></small>
							</li>
						<? endforeach ?>
					</ul>
				</div>
			<? endif ?>

			<? if (!empty($arResult["POPULAR"])): ?>
				<div class="lv-box">
					<h3><?= GetMessage("LV_POPULAR") ?></h3>
					<ol>
						<? foreach ($arResult["POPULAR"] as $arPopular): ?>
							<li><a href="<?= htmlspecialcharsbx($arPopular["URL"]) ?>"><?= $arPopular["NAME"] ?></a> <small>(<?= intval($arPopular["VIEWS"]) ?>)</small></li>
						<? endforeach ?>
					</ol>
				</div>
			<? endif ?>
		</aside>
	</div>

<? else: // список ?>

	<div class="lv-list">
		<div class="lv-main">
			<h1 class="lv-title"><?= GetMessage("LV_LIST_TITLE") ?> <small>(<?= intval($arResult["NAV"]["TOTAL"]) ?>)</small></h1>

			<form method="get" action="<?= htmlspecialcharsbx($baseUrl) ?>" class="lv-filter">
				<input type="text" name="q" value="<?= htmlspecialcharsbx($arResult["FILTER"]["q"]) ?>" placeholder="<?= GetMessage("LV_FILTER_Q") ?>">

				<select name="section">
					<option value=""><?= GetMessage("LV_FILTER_SECTION") ?></option>
					<? foreach ($arResult["SECTIONS"] as $arSection): ?>
						<option value="<?= intval($arSection["ID"]) ?>" <?= $arSection["SELECTED"] ? "selected" : "" ?>><?= $arSection["NAME"] ?> (<?= intval($arSection["COUNT"]) ?>)</option>
					<? endforeach ?>
				</select>

				<select name="city">
					<option value=""><?= GetMessage("LV_FILTER_CITY") ?></option>
					<? foreach ($arResult["CITIES"] as $id => $name): ?>
						<option value="<?= intval($id) ?>" <?= $arResult["FILTER"]["city"] == $id ? "selected" : "" ?>><?= htmlspecialcharsbx($name) ?></option>
					<? endforeach ?>
				</select>

				<select name="exp">
					<option value=""><?= GetMessage("LV_FILTER_EXP") ?></option>
					<? foreach ($arResult["EXPERIENCE"] as $id => $name): ?>
						<option value="<?= intval($id) ?>" <?= $arResult["FILTER"]["exp"] == $id ? "selected" : "" ?>><?= htmlspecialcharsbx($name) ?></option>
					<? endforeach ?>
				</select>

				<input type="number" name="salary" value="<?= $arResult["FILTER"]["salary"] > 0 ? intval($arResult["FILTER"]["salary"]) : "" ?>" placeholder="<?= GetMessage("LV_FILTER_SALARY") ?>" step="10000" min="0">

				<label class="lv-check"><input type="checkbox" name="hot" value="Y" <?= $arResult["FILTER"]["hot"] == "Y" ? "checked" : "" ?>> <?= GetMessage("LV_FILTER_HOT") ?></label>
				<label class="lv-check"><input type="checkbox" name="fav" value="Y" <?= $arResult["FILTER"]["fav"] == "Y" ? "checked" : "" ?>> <?= GetMessage("LV_FILTER_FAV") ?></label>

				<? if ($arResult["FILTER"]["sort"] != $arParams["DEFAULT_SORT"]): ?>
					<input type="hidden" name="sort" value="<?= htmlspecialcharsbx($arResult["FILTER"]["sort"]) ?>">
				<? endif ?>

				<button type="submit" class="lv-btn"><?= GetMessage("LV_FILTER_SUBMIT") ?></button>
				<? if ($arResult["FILTER_ACTIVE"]): ?>
					<a href="<?= htmlspecialcharsbx($arResult["RESET_URL"]) ?>" class="lv-reset"><?= GetMessage("LV_FILTER_RESET") ?></a>
				<? endif ?>
			</form>

			<div class="lv-sort">
				<?= GetMessage("LV_SORT") ?>:
				<? foreach ($arResult["SORT_URLS"] as $sortCode => $sortUrl): ?>
					<? if ($sortCode == $arResult["FILTER"]["sort"]): ?>
						<b><?= GetMessage("LV_SORT_" . strtoupper($sortCode)) ?></b>
					<? else: ?>
						<a href="<?= htmlspecialcharsbx($sortUrl) ?>"><?= GetMessage("LV_SORT_" . strtoupper($sortCode)) ?></a>
					<? endif ?>
				<? endforeach ?>
			</div>

			<? if (empty($arResult["ITEMS"])): ?>
				<div class="lv-empty">
					<p><?= GetMessage("LV_LIST_EMPTY") ?></p>
					<p><a href="<?= htmlspecialcharsbx($baseUrl) ?>"><?= GetMessage("LV_FILTER_RESET") ?></a></p>
				</div>
			<? else: ?>
				<div class="lv-items">
					<? foreach ($arResult["ITEMS"] as $arItem):
						// откликов по каждой — ещё по запросу на строку
						$cnt = legacy_get_response_count($arItem["ID"]);
					?>
						<div class="lv-item <?= $arItem["HOT"] ? "lv-item-hot" : "" ?>" id="vacancy-<?= intval($arItem["ID"]) ?>">
							<div class="lv-item-head">
								<a class="lv-item-name" href="<?= htmlspecialcharsbx($arItem["URL"]) ?>"><?= $arItem["NAME"] ?></a>
								<? if ($arItem["HOT"]): ?><span class="lv-badge lv-badge-hot"><?= GetMessage("LV_HOT") ?></span><? endif ?>
								<? if ($arItem["IS_NEW"]): ?><span class="lv-badge lv-badge-new"><?= GetMessage("LV_NEW") ?></span><? endif ?>
								<a href="#" class="lv-fav <?= $arItem["IS_FAVORITE"] ? "lv-fav-on" : "" ?>" data-id="<?= intval($arItem["ID"]) ?>" title="<?= GetMessage("LV_FAV_TITLE") ?>">★</a>
							</div>
							<div class="lv-meta">
								<span class="lv-salary"><?= $arItem["SALARY_TEXT"] ?></span>
								<? if (strlen($arItem["CITY"]) > 0): ?><span class="lv-city"><?= $arItem["CITY"] ?></span><? endif ?>
								<? if (strlen($arItem["EXPERIENCE"]) > 0): ?><span class="lv-exp"><?= $arItem["EXPERIENCE"] ?></span><? endif ?>
								<? if (strlen($arItem["SECTION_NAME"]) > 0): ?><a class="lv-section" href="<?= htmlspecialcharsbx($arItem["SECTION_URL"]) ?>"><?= $arItem["SECTION_NAME"] ?></a><? endif ?>
							</div>
							<? if (strlen((string)$arItem["PREVIEW_TEXT"]) > 0): ?>
								<p class="lv-item-text"><?= $arItem["PREVIEW_TEXT"] ?></p>
							<? endif ?>
							<? if (!empty($arItem["TAGS"])): ?>
								<div class="lv-tags">
									<? foreach ($arItem["TAGS"] as $tag): ?>
										<a class="lv-tag" href="<?= htmlspecialcharsbx(legacy_build_url($baseUrl, array(), array("q" => $tag))) ?>"><?= $tag ?></a>
									<? endforeach ?>
								</div>
							<? endif ?>
							<div class="lv-item-foot">
								<span title="<?= $arItem["DATE_FORMATTED"] ?>"><?= $arItem["DATE_TEXT"] ?></span>
								<span><?= GetMessage("LV_VIEWS") ?>: <?= intval($arItem["VIEWS"]) ?></span>
								<span><?= $cnt ?> <?= legacy_plural($cnt, GetMessage("LV_RESP_1"), GetMessage("LV_RESP_2"), GetMessage("LV_RESP_5")) ?></span>
							</div>
						</div>
					<? endforeach ?>
				</div>

				<? if ($arResult["NAV"]["PAGES"] > 1): ?>
					<div class="lv-nav">
						<? if (strlen($arResult["NAV"]["PREV_URL"]) > 0): ?>
							<a href="<?= htmlspecialcharsbx($arResult["NAV"]["PREV_URL"]) ?>" class="lv-nav-prev">&larr; <?= GetMessage("LV_NAV_PREV") ?></a>
						<? endif ?>
						<? foreach ($arResult["NAV"]["URLS"] as $pageNum => $pageUrl): ?>
							<? if ($pageNum == $arResult["NAV"]["PAGE"]): ?>
								<b class="lv-nav-cur"><?= $pageNum ?></b>
							<? else: ?>
								<a href="<?= htmlspecialcharsbx($pageUrl) ?>"><?= $pageNum ?></a>
							<? endif ?>
						<? endforeach ?>
						<? if (strlen($arResult["NAV"]["NEXT_URL"]) > 0): ?>
							<a href="<?= htmlspecialcharsbx($arResult["NAV"]["NEXT_URL"]) ?>" class="lv-nav-next"><?= GetMessage("LV_NAV_NEXT") ?> &rarr;</a>
						<? endif ?>
					</div>
				<? endif ?>
			<? endif ?>
		</div>

		<aside class="lv-side">
			<div class="lv-box">
				<h3><?= GetMessage("LV_SECTIONS") ?></h3>
				<ul>
					<? foreach ($arResult["SECTIONS"] as $arSection): ?>
						<li <?= $arSection["SELECTED"] ? 'class="lv-sel"' : "" ?>><a href="<?= htmlspecialcharsbx($arSection["URL"]) ?>"><?= $arSection["NAME"] ?></a> <small>(<?= intval($arSection["COUNT"]) ?>)</small></li>
					<? endforeach ?>
				</ul>
			</div>

			<? if (!empty($arResult["POPULAR"])): ?>
				<div class="lv-box">
					<h3><?= GetMessage("LV_POPULAR") ?></h3>
					<ol>
						<? foreach ($arResult["POPULAR"] as $arPopular): ?>
							<li><a href="<?= htmlspecialcharsbx($arPopular["URL"]) ?>"><?= $arPopular["NAME"] ?></a> <small>(<?= intval($arPopular["VIEWS"]) ?>)</small></li>
						<? endforeach ?>
					</ol>
				</div>
			<? endif ?>

			<?
			// сводка по откликам за неделю — просили HR в 2021, живёт только здесь
			$rsTotal = $DB->Query(
				"SELECT COUNT(*) CNT, COUNT(DISTINCT VACANCY_ID) VAC FROM " . LEGACY_RESPONSE_TABLE
				. " WHERE CREATED >= DATE_SUB(" . $DB->GetNowFunction() . ", INTERVAL 7 DAY) AND STATUS <> 'SPAM'",
				false,
				"File: " . __FILE__ . "<br>Line: " . __LINE__
			);
			if ($rsTotal && ($arTotal = $rsTotal->Fetch()) && intval($arTotal["CNT"]) > 0):
			?>
				<div class="lv-box lv-box-muted">
					<?= str_replace(array("#N#", "#V#"), array(intval($arTotal["CNT"]), intval($arTotal["VAC"])), GetMessage("LV_WEEK_SUMMARY")) ?>
				</div>
			<? endif ?>
		</aside>
	</div>

<? endif ?>

</div>

<script>
(function () {
	var root = document.getElementById("legacy-vacancies");
	if (!root) return;
	var base = root.getAttribute("data-base") || "/vacancies/";
	root.addEventListener("click", function (e) {
		var link = e.target.closest(".lv-fav");
		if (!link) return;
		e.preventDefault();
		var id = link.getAttribute("data-id");
		fetch(base + "ajax.php?action=favorite&id=" + encodeURIComponent(id), {credentials: "same-origin"})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.success) { alert(data && data.error ? data.error : "<?= GetMessage("LV_FAV_ERROR") ?>"); return; }
				link.classList.toggle("lv-fav-on", !!data.favorite);
				if (link.textContent.trim() !== "★") {
					link.textContent = data.favorite ? "<?= GetMessage("LV_FAV_ON") ?>" : "<?= GetMessage("LV_FAV_OFF") ?>";
				}
			})
			.catch(function () { alert("<?= GetMessage("LV_FAV_ERROR") ?>"); });
	});
})();
</script>
