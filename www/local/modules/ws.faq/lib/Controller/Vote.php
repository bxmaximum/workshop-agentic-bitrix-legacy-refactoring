<?php

declare(strict_types=1);

namespace Ws\Faq\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Csrf;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Ws\Faq\Controller\Request\VoteRequest;
use Ws\Faq\Dto\VoteInputDto;
use Ws\Faq\Service\GuestIdentifierService;
use Ws\Faq\Service\VoteService;

final class Vote extends Controller
{
	public function getAutoWiredParameters(): array
	{
		return [
			new ValidationParameter(
				VoteRequest::class,
				fn(): VoteRequest => VoteRequest::createFromRequest($this->getRequest()),
			),
		];
	}

	#[DisablePrefilters([ActionFilter\Authentication::class])]
	#[HttpMethod([ActionFilter\HttpMethod::METHOD_POST])]
	#[Csrf]
	public function voteAction(
		VoteRequest $request,
		VoteService $voteService,
		GuestIdentifierService $guestService,
	): ?array {
		$currentUser = $this->getCurrentUser();
		$userId = null;
		if ($currentUser !== null)
		{
			$id = (int)$currentUser->getId();
			$userId = $id > 0 ? $id : null;
		}

		$guestHash = $userId === null ? $guestService->getGuestHash() : null;

		$dto = new VoteInputDto(
			questionId: (int)$request->questionId,
			isUseful: (bool)$request->isUseful,
			userId: $userId,
			guestHash: $guestHash,
			ipAddress: (string)$this->getRequest()->getRemoteAddress(),
		);

		$result = $voteService->vote($dto);
		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		/** @var array<string, mixed> $data */
		$data = $result->getData();

		return $data;
	}
}
