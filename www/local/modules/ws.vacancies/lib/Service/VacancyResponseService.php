<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use CEvent;
use Ws\Vacancies\Dto\VacancyResponseInputDto;
use Ws\Vacancies\Repository\VacancyRepository;
use Ws\Vacancies\Repository\VacancyResponseRepository;

final class VacancyResponseService
{
	private const SESSION_TIMEOUT_KEY = 'LEGACY_LAST_RESPONSE';

	public function __construct(
		private readonly VacancyResponseRepository $responses,
		private readonly VacancyRepository $vacancies,
	) {
	}

	/**
	 * Валидация, сохранение отклика и CEvent::Send. CSRF не проверяется (баг №11).
	 */
	public function sendResponse(
		VacancyResponseInputDto $dto,
		int $timeout = 60,
		string $eventName = 'LEGACY_VACANCY_RESPONSE',
		string $emailTo = '',
		string $vacancyName = '',
		string $vacancyUrl = '',
	): Result {
		$result = new Result();
		$errors = $this->validate($dto, $timeout);

		if ($errors !== [])
		{
			foreach ($errors as $code => $message)
			{
				$result->addError(new Error($message, (string)$code));
			}

			return $result;
		}

		$vacancy = $this->vacancies->getById($dto->vacancyId);
		if ($vacancy === null)
		{
			return $result->addError(new Error('Вакансия не найдена или закрыта', 'vacancy'));
		}

		$addResult = $this->responses->create($dto);
		if (!$addResult->isSuccess())
		{
			return $result->addError(new Error('Не удалось сохранить отклик, попробуйте позже', 'db'));
		}

		$_SESSION[self::SESSION_TIMEOUT_KEY] = time();

		$responseId = (int)$addResult->getId();
		$siteId = defined('SITE_ID') ? (string)SITE_ID : 's1';

		CEvent::Send($eventName, $siteId, [
			'VACANCY_ID' => $dto->vacancyId,
			'VACANCY_NAME' => $vacancyName !== '' ? $vacancyName : (string)$vacancy['NAME'],
			'VACANCY_URL' => $vacancyUrl,
			'NAME' => $dto->name,
			'EMAIL' => $dto->email,
			'PHONE' => $dto->phone,
			'MESSAGE' => $dto->message,
			'EMAIL_TO' => $emailTo,
			'RESPONSE_ID' => $responseId,
		]);

		return $result->setData([
			'responseId' => $responseId,
			'vacancyId' => $dto->vacancyId,
		]);
	}

	/**
	 * @return array<string, string>
	 */
	private function validate(VacancyResponseInputDto $dto, int $timeout): array
	{
		$errors = [];

		if (mb_strlen($dto->name) < 2)
		{
			$errors['name'] = 'Укажите имя';
		}

		if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $dto->email))
		{
			$errors['email'] = 'Некорректный e-mail';
		}

		if ($dto->phone !== '')
		{
			$digits = preg_replace('/[^0-9]/', '', $dto->phone) ?? '';
			if (strlen($digits) < 10 || strlen($digits) > 15)
			{
				$errors['phone'] = 'Некорректный телефон';
			}
		}

		// Баг №10: строго mb_strlen < 10
		if (mb_strlen($dto->message) < 10)
		{
			$errors['message'] = 'Напишите пару слов о себе';
		}

		if (
			$timeout > 0
			&& isset($_SESSION[self::SESSION_TIMEOUT_KEY])
			&& (time() - (int)$_SESSION[self::SESSION_TIMEOUT_KEY]) < $timeout
		)
		{
			$errors['timeout'] = 'Вы уже отправляли отклик, подождите минуту';
		}

		return $errors;
	}
}
