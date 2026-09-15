<?php

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Ws\Faq\Dto\CategoryDto;
use Ws\Faq\Dto\QuestionDto;
use Ws\Faq\Service\CategoryService;
use Ws\Faq\Service\GuestIdentifierService;
use Ws\Faq\Service\QuestionService;
use Ws\Faq\Service\VoteService;

Loc::loadMessages(__FILE__);

final class WsFaqListComponent extends CBitrixComponent
{
	public function onPrepareComponentParams($arParams): array
	{
		$arParams['SHOW_COUNTERS'] = (($arParams['SHOW_COUNTERS'] ?? 'Y') === 'Y') ? 'Y' : 'N';
		$arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);
		$arParams['CACHE_TYPE'] = (string)($arParams['CACHE_TYPE'] ?? 'A');
		$categoryId = (int)($arParams['CATEGORY_ID'] ?? 0);
		$arParams['CATEGORY_ID'] = $categoryId > 0 ? $categoryId : 0;

		return $arParams;
	}

	public function executeComponent(): void
	{
		if (!Loader::includeModule('ws.faq'))
		{
			ShowError(Loc::getMessage('WS_FAQ_LIST_MODULE_NOT_INSTALLED') ?: 'Module ws.faq is not installed');

			return;
		}

		$cacheId = [
			$this->arParams['CATEGORY_ID'],
			$this->arParams['SHOW_COUNTERS'],
		];

		if ($this->startResultCache($this->arParams['CACHE_TIME'], $cacheId))
		{
			$locator = ServiceLocator::getInstance();
			/** @var CategoryService $categoryService */
			$categoryService = $locator->get(CategoryService::class);
			/** @var QuestionService $questionService */
			$questionService = $locator->get(QuestionService::class);

			$categoryFilter = $this->arParams['CATEGORY_ID'] > 0
				? $this->arParams['CATEGORY_ID']
				: null;

			$categories = $categoryService->listActive();
			$questions = $questionService->listActive($categoryFilter);

			$this->arResult['CATEGORIES'] = array_map(
				static fn(CategoryDto $dto): array => [
					'ID' => $dto->id,
					'CODE' => $dto->code,
					'NAME' => $dto->name,
					'SORT' => $dto->sort,
				],
				$categories
			);

			$this->arResult['QUESTIONS'] = array_map(
				static fn(QuestionDto $dto): array => [
					'ID' => $dto->id,
					'CATEGORY_ID' => $dto->categoryId,
					'QUESTION' => $dto->question,
					'ANSWER' => $dto->answer,
					'SORT' => $dto->sort,
					'USEFUL_COUNT' => $dto->usefulCount,
					'NOT_USEFUL_COUNT' => $dto->notUsefulCount,
					'CATEGORY_NAME' => $dto->categoryName,
				],
				$questions
			);

			$this->arResult['QUESTIONS_BY_CATEGORY'] = $this->groupByCategory(
				$this->arResult['QUESTIONS']
			);

			Application::getInstance()->getTaggedCache()->registerTag('ws_faq_list');
			foreach ($questions as $question)
			{
				Application::getInstance()->getTaggedCache()->registerTag('ws_faq_item_' . $question->id);
			}

			$this->endResultCache();
		}

		$this->arResult['SHOW_COUNTERS'] = $this->arParams['SHOW_COUNTERS'] === 'Y';
		$this->arResult['USER_VOTES'] = $this->loadVisitorVotes(
			array_column($this->arResult['QUESTIONS'] ?? [], 'ID')
		);

		$this->includeComponentTemplate();
	}

	/**
	 * @param list<array<string, mixed>> $questions
	 * @return array<string, list<array<string, mixed>>>
	 */
	private function groupByCategory(array $questions): array
	{
		$grouped = ['all' => $questions];

		foreach ($questions as $question)
		{
			$key = $question['CATEGORY_ID'] !== null
				? (string)(int)$question['CATEGORY_ID']
				: 'none';
			$grouped[$key][] = $question;
		}

		return $grouped;
	}

	/**
	 * @param list<int|string> $questionIds
	 * @return array<int, bool>
	 */
	private function loadVisitorVotes(array $questionIds): array
	{
		$ids = array_values(array_filter(array_map('intval', $questionIds)));
		if ($ids === [])
		{
			return [];
		}

		$locator = ServiceLocator::getInstance();
		/** @var VoteService $voteService */
		$voteService = $locator->get(VoteService::class);
		/** @var GuestIdentifierService $guestService */
		$guestService = $locator->get(GuestIdentifierService::class);

		$currentUser = CurrentUser::get();
		$userId = null;
		$id = (int)$currentUser->getId();
		if ($id > 0)
		{
			$userId = $id;
		}

		$guestHash = $userId === null ? $guestService->getGuestHash() : null;

		return $voteService->getVisitorVotesMap($userId, $guestHash, $ids);
	}
}
