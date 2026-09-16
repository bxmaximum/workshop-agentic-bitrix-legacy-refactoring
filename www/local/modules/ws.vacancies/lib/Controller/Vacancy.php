<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Csrf;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Ws\Vacancies\Controller\Request\VacancyResponseRequest;
use Ws\Vacancies\Dto\ResponseSettingsDto;
use Ws\Vacancies\Service\VacancyResponseService;

/**
 * Отклик на вакансию: ws:vacancies.Vacancy.respond
 */
final class Vacancy extends Controller
{
	public function getAutoWiredParameters(): array
	{
		return [
			new ValidationParameter(
				VacancyResponseRequest::class,
				fn(): VacancyResponseRequest => VacancyResponseRequest::createFromRequest($this->getRequest()),
			),
		];
	}

	/**
	 * @return array{responseId: int, vacancyId: int}|null
	 */
	#[DisablePrefilters([ActionFilter\Authentication::class])]
	#[HttpMethod([ActionFilter\HttpMethod::METHOD_POST])]
	#[Csrf]
	public function respondAction(
		VacancyResponseRequest $request,
		VacancyResponseService $responseService,
	): ?array {
		$currentUser = $this->getCurrentUser();
		$userId = $currentUser !== null ? (int)$currentUser->getId() : 0;

		$dto = $request->toInputDto(
			vacancyId: (int)$request->vacancyId,
			ip: (string)$this->getRequest()->getRemoteAddress(),
			userId: $userId,
		);

		$result = $responseService->sendResponse(
			$dto,
			new ResponseSettingsDto(siteHost: (string)$this->getRequest()->getHttpHost()),
		);
		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		/** @var array{responseId: int, vacancyId: int} $data */
		$data = $result->getData();

		return $data;
	}
}
