<?php

$_SERVER['DOCUMENT_ROOT'] = '/Users/kirk/Omut/lesson3-copy.bitrix/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Bitrix\Main\Loader::includeModule('ws.vacancies');
$svc = Bitrix\Main\DI\ServiceLocator::getInstance()->get(Ws\Vacancies\Service\VacancyService::class);
$d = $svc->getDetail(0, 'senior-php-bitrix', true);
echo 'tags=' . implode(',', $d->tags) . PHP_EOL;

$list = $svc->getList(Ws\Vacancies\Dto\VacancyFilterDto::fromRequest(
	Bitrix\Main\Context::getCurrent()->getRequest(),
	5,
	'date'
));
foreach ($list->items as $item)
{
	if ($item->tags !== [])
	{
		echo $item->code . ': ' . implode('|', $item->tags) . PHP_EOL;
	}
}

$filter = new Ws\Vacancies\Dto\VacancyFilterDto(q: 'highload', pageSize: 5);
$list2 = $svc->getList($filter);
echo 'q=highload count=' . $list2->totalCount . PHP_EOL;

$filter3 = new Ws\Vacancies\Dto\VacancyFilterDto(q: 'b2b & b2c', pageSize: 5);
$list3 = $svc->getList($filter3);
echo 'q=b2b&b2c count=' . $list3->totalCount . PHP_EOL;
foreach ($list3->items as $item)
{
	echo '  ' . $item->code . PHP_EOL;
}
