<?php

$_SERVER['DOCUMENT_ROOT'] = '/Users/kirk/Omut/lesson3-copy.bitrix/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Bitrix\Main\Loader::includeModule('iblock');
Bitrix\Main\Loader::includeModule('ws.vacancies');

$repo = new Ws\Vacancies\Repository\VacancyRepository();
echo 'iblock=' . $repo->getIblockId() . PHP_EOL;

$ref = new ReflectionClass($repo);
$m = $ref->getMethod('getTagsPropertyId');
$m->setAccessible(true);
echo 'tagsPropId=' . $m->invoke($repo) . PHP_EOL;

$props = Bitrix\Iblock\PropertyTable::query()
	->setSelect(['ID', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE'])
	->where('IBLOCK_ID', $repo->getIblockId())
	->fetchAll();
foreach ($props as $p)
{
	echo $p['ID'] . ' ' . $p['CODE'] . ' ' . $p['PROPERTY_TYPE'] . ' ' . $p['MULTIPLE'] . PHP_EOL;
}

$rows = Bitrix\Iblock\ElementPropertyTable::query()
	->setSelect(['IBLOCK_PROPERTY_ID', 'VALUE', 'IBLOCK_ELEMENT_ID'])
	->where('IBLOCK_ELEMENT_ID', 1)
	->fetchAll();
echo 'props for el 1: ' . count($rows) . PHP_EOL;
foreach ($rows as $r)
{
	echo '  prop=' . $r['IBLOCK_PROPERTY_ID'] . ' val=' . $r['VALUE'] . PHP_EOL;
}

// Check iblock version / property storage
$ib = Bitrix\Iblock\IblockTable::getById($repo->getIblockId())->fetch();
echo 'VERSION=' . ($ib['VERSION'] ?? '?') . ' API=' . ($ib['API_CODE'] ?? '') . PHP_EOL;
