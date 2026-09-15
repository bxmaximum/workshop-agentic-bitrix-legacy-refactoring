<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller\Request;

use Bitrix\Main\Request;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;

/**
 * HTTP-вход формы отклика на вакансию (валидируется через ValidationParameter).
 */
final readonly class VacancyResponseRequest
{
	public function __construct(
		#[PositiveNumber(errorMessage: 'Не указана вакансия')]
		public ?int $vacancyId = null,

		#[Length(min: 2, max: 100, errorMessage: 'Укажите имя')]
		public ?string $name = null,

		#[Email(errorMessage: 'Некорректный e-mail')]
		public ?string $email = null,

		public ?string $phone = null,

		#[Length(min: 10, max: 2000, errorMessage: 'Напишите пару слов о себе')]
		public ?string $message = null,
	) {
	}

	public static function createFromRequest(Request $request): self
	{
		return new self(
			vacancyId: (int)($request->get('vacancy_id') ?? 0) ?: null,
			name: ($v = $request->get('name')) !== null ? trim((string)$v) : null,
			email: ($v = $request->get('email')) !== null ? trim((string)$v) : null,
			phone: ($v = $request->get('phone')) !== null ? trim((string)$v) : null,
			message: ($v = $request->get('message')) !== null ? trim((string)$v) : null,
		);
	}
}
