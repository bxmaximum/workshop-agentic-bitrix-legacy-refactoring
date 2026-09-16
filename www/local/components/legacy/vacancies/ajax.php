<?php

declare(strict_types=1);

/**
 * AJAX-обработчик раздела вакансий — адаптер обратной совместимости.
 * Подключается из /vacancies/ajax.php (там prolog_before).
 *
 * Формат ответов сохранён байт-в-байт с легаси. Современный вход — контроллеры модуля:
 * ws:vacancies.Favorite.toggle / .list, ws:vacancies.Vacancy.respond.
 *
 * action=favorite&id=N   — переключить избранное (GET, без sessid — баг №11)
 * action=favorites       — список ID избранного
 * action=views&id=N      — просмотры и отклики вакансии
 * action=respond         — отклик через AJAX (POST + sessid)
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Context;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Ws\Vacancies\Controller\Request\VacancyResponseRequest;
use Ws\Vacancies\Dto\ResponseSettingsDto;
use Ws\Vacancies\Service\FavoriteService;
use Ws\Vacancies\Service\VacancyResponseService;
use Ws\Vacancies\Service\VacancyService;

global $APPLICATION;

$APPLICATION->RestartBuffer();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$request = Context::getCurrent()->getRequest();
$action = trim((string)($request->get('action') ?? ''));
$result = ['success' => false];

if (!Loader::includeModule('ws.vacancies'))
{
	$result['error'] = 'Модуль вакансий не установлен';
	echo json_encode($result, JSON_UNESCAPED_UNICODE);
	die();
}

$locator = ServiceLocator::getInstance();

switch ($action)
{
	case 'favorite':
		$id = (int)($request->get('id') ?? 0);
		if ($id <= 0)
		{
			$result['error'] = 'Не указана вакансия';
			break;
		}

		$toggle = $locator->get(FavoriteService::class)->toggle($id);
		if (!$toggle->isSuccess())
		{
			$result['error'] = $toggle->getErrorMessages()[0] ?? 'Вакансия не найдена';
			break;
		}

		$result['success'] = true;
		$result['favorite'] = (bool)$toggle->getData()['favorite'];
		$result['count'] = (int)$toggle->getData()['count'];
		break;

	case 'favorites':
		$result['success'] = true;
		$result['items'] = $locator->get(FavoriteService::class)->getFavoriteIds();
		break;

	case 'views':
		$id = (int)($request->get('id') ?? 0);
		if ($id <= 0)
		{
			$result['error'] = 'Не указана вакансия';
			break;
		}

		$stats = $locator->get(VacancyService::class)->getStats($id);
		$result['success'] = true;
		$result['views'] = $stats->views;
		$result['responses'] = $stats->responseCount;
		break;

	case 'respond':
		if (!$request->isPost())
		{
			$result['error'] = 'Только POST';
			break;
		}
		if (!check_bitrix_sessid())
		{
			$result['error'] = 'Сессия устарела, обновите страницу';
			break;
		}

		$input = VacancyResponseRequest::createFromRequest($request);
		$dto = $input->toInputDto(
			vacancyId: (int)$input->vacancyId,
			ip: (string)$request->getRemoteAddress(),
			userId: (int)CurrentUser::get()->getId(),
		);

		$send = $locator->get(VacancyResponseService::class)->sendResponse(
			$dto,
			new ResponseSettingsDto(siteHost: (string)$request->getHttpHost()),
		);

		if (!$send->isSuccess())
		{
			$errors = [];
			foreach ($send->getErrors() as $error)
			{
				$errors[$error->getCode()] = $error->getMessage();
			}
			$result['errors'] = $errors;
			$result['error'] = implode('; ', $errors);
			break;
		}

		$result['success'] = true;
		$result['message'] = 'Спасибо! Отклик отправлен.';
		break;

	default:
		$result['error'] = 'Неизвестное действие';
		break;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
die();
