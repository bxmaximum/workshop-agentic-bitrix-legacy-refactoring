<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller\Request;

use Bitrix\Main\Request;
use Bitrix\Main\Validation\Rule\PositiveNumber;

/**
 * HTTP-вход переключения избранного (валидируется через ValidationParameter).
 */
final readonly class ToggleFavoriteRequest
{
	public function __construct(
		#[PositiveNumber(errorMessage: 'Не указана вакансия')]
		public ?int $id = null,
	) {
	}

	public static function createFromRequest(Request $request): self
	{
		$raw = $request->get('id');

		return new self(
			id: ($raw !== null && $raw !== '' && is_numeric($raw)) ? (int)$raw : null,
		);
	}
}
