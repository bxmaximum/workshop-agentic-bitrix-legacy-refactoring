<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use CEvent;
use Ws\Vacancies\Dto\ResponseSettingsDto;
use Ws\Vacancies\Dto\VacancyResponseInputDto;
use Ws\Vacancies\Helper\Text;
use Ws\Vacancies\Helper\UrlBuilder;
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
	 * Ошибки в Result: код = имя поля (name, email, phone, message, timeout, vacancy, db).
	 */
	public function sendResponse(VacancyResponseInputDto $dto, ResponseSettingsDto $settings = new ResponseSettingsDto()): Result
	{
		$result = new Result();

		$vacancy = $this->vacancies->getById($dto->vacancyId);
		$errors = $this->validate($dto, $settings->timeout);
		if ($vacancy === null)
		{
			$errors['vacancy'] = 'Вакансия не найдена или закрыта';
		}

		if ($errors !== [])
		{
			foreach ($errors as $code => $message)
			{
				$result->addError(new Error($message, $code));
			}

			return $result;
		}

		$addResult = $this->responses->create($dto);
		if (!$addResult->isSuccess())
		{
			return $result->addError(new Error('Не удалось сохранить отклик, попробуйте позже', 'db'));
		}

		Application::getInstance()->getSession()->set(self::SESSION_TIMEOUT_KEY, time());

		$responseId = (int)$addResult->getId();
		$siteId = defined('SITE_ID') ? (string)SITE_ID : 's1';
		$vacancyUrl = UrlBuilder::vacancy((string)$vacancy['CODE'], $dto->vacancyId, $settings->baseUrl);

		CEvent::Send($settings->eventName, $siteId, [
			'VACANCY_ID' => $dto->vacancyId,
			'VACANCY_NAME' => (string)$vacancy['NAME'],
			'VACANCY_URL' => 'http://' . $settings->siteHost . $vacancyUrl,
			'NAME' => $dto->name,
			'EMAIL' => $dto->email,
			'PHONE' => $dto->phone,
			'MESSAGE' => $dto->message,
			'EMAIL_TO' => $settings->emailTo,
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

		// как в легаси: strlen в байтах, а не mb_strlen
		if (strlen($dto->name) < 2)
		{
			$errors['name'] = 'Укажите имя';
		}

		if (!Text::isEmail($dto->email))
		{
			$errors['email'] = 'Некорректный e-mail';
		}

		if ($dto->phone !== '' && !Text::isPhone($dto->phone))
		{
			$errors['phone'] = 'Некорректный телефон';
		}

		// Баг №10: строго mb_strlen < 10
		if (mb_strlen($dto->message) < 10)
		{
			$errors['message'] = 'Напишите пару слов о себе';
		}

		$lastResponse = (int)(Application::getInstance()->getSession()->get(self::SESSION_TIMEOUT_KEY) ?? 0);
		if ($timeout > 0 && $lastResponse > 0 && (time() - $lastResponse) < $timeout)
		{
			$errors['timeout'] = 'Вы уже отправляли отклик, подождите минуту';
		}

		return $errors;
	}
}
