<?php

declare(strict_types=1);

namespace Ws\Faq\Controller\Request;

use Bitrix\Main\Request;
use Bitrix\Main\Validation\Rule\PositiveNumber;
use Ws\Faq\Validation\Rule\NotNull;

/**
 * HTTP-вход экшена голосования (валидируется через ValidationParameter).
 */
final readonly class VoteRequest
{
	public function __construct(
		#[PositiveNumber(errorMessage: 'Некорректный идентификатор вопроса')]
		public ?int $questionId = null,

		#[NotNull(errorMessage: 'Не указана оценка полезности')]
		public ?bool $isUseful = null,
	) {
	}

	public static function createFromRequest(Request $request): self
	{
		$questionIdRaw = $request->get('questionId');
		$questionId = null;
		if ($questionIdRaw !== null && $questionIdRaw !== '')
		{
			$questionId = (int)$questionIdRaw;
		}

		$isUsefulRaw = $request->get('isUseful');
		$isUseful = null;
		if ($isUsefulRaw !== null && $isUsefulRaw !== '')
		{
			if (is_bool($isUsefulRaw))
			{
				$isUseful = $isUsefulRaw;
			}
			else
			{
				$isUseful = filter_var($isUsefulRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			}
		}

		return new self(
			questionId: $questionId,
			isUseful: $isUseful,
		);
	}
}
