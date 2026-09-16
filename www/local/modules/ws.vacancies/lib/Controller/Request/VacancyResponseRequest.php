<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller\Request;

use Bitrix\Main\Request;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;
use Ws\Vacancies\Dto\VacancyResponseInputDto;
use Ws\Vacancies\Helper\Text;

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

	/**
	 * Поля очищаются по легаси-правилам (без тегов, обрезка по длине) ещё на входе.
	 */
	public static function createFromRequest(Request $request): self
	{
		return new self(
			vacancyId: (int)($request->get('vacancy_id') ?? 0) ?: null,
			name: ($v = $request->get('name')) !== null ? Text::clean($v, 100) : null,
			email: ($v = $request->get('email')) !== null ? Text::clean($v, 100) : null,
			phone: ($v = $request->get('phone')) !== null ? Text::clean($v, 30) : null,
			message: ($v = $request->get('message')) !== null ? Text::cleanText($v, 2000) : null,
		);
	}

	/**
	 * Проверенный HTTP-вход → внутренний DTO сервиса.
	 */
	public function toInputDto(int $vacancyId, string $ip, int $userId): VacancyResponseInputDto
	{
		return new VacancyResponseInputDto(
			vacancyId: $vacancyId,
			name: (string)$this->name,
			email: (string)$this->email,
			phone: (string)$this->phone,
			message: (string)$this->message,
			ip: $ip,
			userId: $userId,
		);
	}
}
