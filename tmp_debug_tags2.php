<?php

$_SERVER['DOCUMENT_ROOT'] = '/Users/kirk/Omut/lesson3-copy.bitrix/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Bitrix\Main\Loader::includeModule('iblock');
Bitrix\Iblock\IblockTable::compileEntity('Vacancy');

$obj = Bitrix\Iblock\Elements\ElementVacancyTable::getByPrimary(1, [
	'select' => ['ID', 'NAME', 'TAGS'],
])->fetchObject();

echo 'obj=' . ($obj ? 'yes' : 'no') . PHP_EOL;
if ($obj)
{
	$tags = $obj->getTags();
	echo 'getTags type=' . get_debug_type($tags) . PHP_EOL;
	if (is_object($tags))
	{
		echo 'class=' . $tags::class . PHP_EOL;
		echo 'methods=' . implode(',', get_class_methods($tags)) . PHP_EOL;
		if (method_exists($tags, 'getAll'))
		{
			foreach ($tags->getAll() as $t)
			{
				echo 'item class=' . $t::class . PHP_EOL;
				if (method_exists($t, 'getValue'))
				{
					echo '  val=' . $t->getValue() . PHP_EOL;
				}
			}
		}
	}
}

$conn = Bitrix\Main\Application::getConnection();
$r = $conn->query('SELECT * FROM b_iblock_element_prop_m1 WHERE IBLOCK_ELEMENT_ID=1 LIMIT 20');
while ($row = $r->fetch())
{
	print_r($row);
}
