<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

/** @var array $arResult */
/** @var array $arParams */
/** @var CBitrixComponentTemplate $this */
/** @var CBitrixComponent $component */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;

Loc::loadMessages(__FILE__);

$this->setFrameMode(true);

Extension::load(['main.core', 'main.ajax']);

$categories = $arResult['CATEGORIES'] ?? [];
$questions = $arResult['QUESTIONS'] ?? [];
$userVotes = $arResult['USER_VOTES'] ?? [];
$showCounters = !empty($arResult['SHOW_COUNTERS']);
$uid = 'ws-faq-' . $this->randString();
?>
<div
	class="ws-faq"
	id="<?= htmlspecialcharsbx($uid) ?>"
	data-show-counters="<?= $showCounters ? 'Y' : 'N' ?>"
>
	<div class="ws-faq__toolbar">
		<label class="ws-faq__search">
			<span class="ws-faq__search-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
					<circle cx="11" cy="11" r="7"></circle>
					<path d="M20 20l-3.5-3.5"></path>
				</svg>
			</span>
			<input
				type="search"
				class="ws-faq__search-input"
				placeholder="<?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_SEARCH_PLACEHOLDER')) ?>"
				autocomplete="off"
				aria-label="<?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_SEARCH_PLACEHOLDER')) ?>"
			>
		</label>
	</div>

	<?php if ($categories !== []): ?>
		<div class="ws-faq__tabs" role="tablist" aria-label="Категории FAQ">
			<button
				type="button"
				class="ws-faq__tab is-active"
				role="tab"
				aria-selected="true"
				data-category="all"
			><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_TAB_ALL')) ?></button>
			<?php foreach ($categories as $category): ?>
				<button
					type="button"
					class="ws-faq__tab"
					role="tab"
					aria-selected="false"
					data-category="<?= (int)$category['ID'] ?>"
				><?= htmlspecialcharsbx((string)$category['NAME']) ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($questions === []): ?>
		<p class="ws-faq__empty"><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_EMPTY')) ?></p>
	<?php else: ?>
		<div class="ws-faq__list" role="list">
			<?php foreach ($questions as $question):
				$questionId = (int)$question['ID'];
				$categoryId = $question['CATEGORY_ID'] !== null ? (int)$question['CATEGORY_ID'] : 0;
				$userVote = array_key_exists($questionId, $userVotes) ? $userVotes[$questionId] : null;
				$votedUseful = $userVote === true;
				$votedNotUseful = $userVote === false;
				$hasVote = $userVote !== null;
				$searchBlob = mb_strtolower(
					(string)$question['QUESTION'] . ' ' . strip_tags((string)$question['ANSWER'])
				);
				?>
				<details
					class="ws-faq__item"
					role="listitem"
					data-id="<?= $questionId ?>"
					data-category="<?= $categoryId ?>"
					data-search="<?= htmlspecialcharsbx($searchBlob) ?>"
				>
					<summary class="ws-faq__question">
						<span class="ws-faq__question-text"><?= htmlspecialcharsbx((string)$question['QUESTION']) ?></span>
						<span class="ws-faq__chevron" aria-hidden="true"></span>
					</summary>

					<div class="ws-faq__body">
						<div class="ws-faq__answer">
							<?= $question['ANSWER'] ?>
						</div>

						<div
							class="ws-faq__vote<?= $hasVote ? ' is-voted' : '' ?>"
							data-question-id="<?= $questionId ?>"
						>
							<p class="ws-faq__vote-label"><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_USEFUL_QUESTION')) ?></p>
							<div class="ws-faq__vote-actions">
								<button
									type="button"
									class="ws-faq__vote-btn<?= $votedUseful ? ' is-active' : '' ?>"
									data-useful="Y"
								>
									<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M7 11v10H4a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1h3Zm0 0h7.2a2 2 0 0 0 1.94-1.51l1.3-5.2A1.5 1.5 0 0 0 16 2h-1.2a2 2 0 0 0-1.79 1.11L11 7H7v4Z"></path>
									</svg>
									<span><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_YES')) ?></span>
									<?php if ($showCounters): ?>
										<span class="ws-faq__counter" data-counter="useful"><?= (int)$question['USEFUL_COUNT'] ?></span>
									<?php endif; ?>
								</button>
								<button
									type="button"
									class="ws-faq__vote-btn<?= $votedNotUseful ? ' is-active' : '' ?>"
									data-useful="N"
								>
									<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M17 13V3h3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-3Zm0 0H9.8a2 2 0 0 0-1.94 1.51l-1.3 5.2A1.5 1.5 0 0 0 8 22h1.2a2 2 0 0 0 1.79-1.11L13 17h4v-4Z"></path>
									</svg>
									<span><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_NO')) ?></span>
									<?php if ($showCounters): ?>
										<span class="ws-faq__counter" data-counter="not-useful"><?= (int)$question['NOT_USEFUL_COUNT'] ?></span>
									<?php endif; ?>
								</button>
							</div>
							<p class="ws-faq__vote-message"<?= $hasVote ? '' : ' hidden' ?>><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_THANKS')) ?></p>
							<p class="ws-faq__vote-error" hidden><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_VOTE_ERROR')) ?></p>
						</div>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<p class="ws-faq__empty ws-faq__empty--filter" hidden><?= htmlspecialcharsbx(Loc::getMessage('WS_FAQ_LIST_EMPTY_FILTER')) ?></p>
	<?php endif; ?>
</div>

<script>
	BX.message(<?= Json::encode([
		'WS_FAQ_LIST_THANKS' => (string)Loc::getMessage('WS_FAQ_LIST_THANKS'),
		'WS_FAQ_LIST_VOTE_ERROR' => (string)Loc::getMessage('WS_FAQ_LIST_VOTE_ERROR'),
	]) ?>);
	BX.ready(function () {
		const root = BX('<?= CUtil::JSEscape($uid) ?>');
		if (root && BX.Ws && BX.Ws.Faq && BX.Ws.Faq.List)
		{
			new BX.Ws.Faq.List(root);
		}
	});
</script>
