<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Ws\Vacancies\Controller\Request\ToggleFavoriteRequest;
use Ws\Vacancies\Service\FavoriteService;

/**
 * Избранное: ws:vacancies.Favorite.toggle / ws:vacancies.Favorite.list
 */
final class Favorite extends Controller
{
	public function getAutoWiredParameters(): array
	{
		return [
			new ValidationParameter(
				ToggleFavoriteRequest::class,
				fn(): ToggleFavoriteRequest => ToggleFavoriteRequest::createFromRequest($this->getRequest()),
			),
		];
	}

	/**
	 * CSRF отключён намеренно: легаси-клиенты дёргали избранное GET-ом без sessid (баг №11).
	 *
	 * @return array{favorite: bool, count: int}|null
	 */
	#[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])]
	public function toggleAction(ToggleFavoriteRequest $request, FavoriteService $favoriteService): ?array
	{
		$result = $favoriteService->toggle((int)$request->id);
		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		/** @var array{favorite: bool, count: int} $data */
		$data = $result->getData();

		return $data;
	}

	/**
	 * @return array{items: list<int>}
	 */
	#[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])]
	public function listAction(FavoriteService $favoriteService): array
	{
		return ['items' => $favoriteService->getFavoriteIds()];
	}
}
