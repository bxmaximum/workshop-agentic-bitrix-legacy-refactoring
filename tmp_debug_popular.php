<?php

$_SERVER['DOCUMENT_ROOT'] = '/Users/kirk/Omut/lesson3-copy.bitrix/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legacy_helpers.php';
Bitrix\Main\Loader::includeModule('ws.vacancies');

echo "legacy popular:\n";
foreach (legacy_get_popular_ids(5) as $id => $v)
{
	echo "  $id => $v\n";
}

$stats = Bitrix\Main\DI\ServiceLocator::getInstance()->get(Ws\Vacancies\Repository\VacancyStatRepository::class);
echo "repo popular:\n";
foreach ($stats->getTopPopularIds(5) as $id => $v)
{
	echo "  $id => $v\n";
	$row = Bitrix\Main\DI\ServiceLocator::getInstance()
		->get(Ws\Vacancies\Repository\VacancyRepository::class)
		->getRawById($id);
	echo '    active=' . ($row['ACTIVE'] ?? '?') . ' name=' . ($row['NAME'] ?? 'null') . PHP_EOL;
}
