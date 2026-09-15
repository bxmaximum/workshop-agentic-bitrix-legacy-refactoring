<?php

declare(strict_types=1);

/**
 * Данные для легаси-раздела «Вакансии» (занятие 3). Не часть сайта — учебная заготовка.
 *
 * Создаёт: тип инфоблока `legacy`, инфоблок «Вакансии» (CODE=VACANCIES, API_CODE=Vacancy),
 * свойства, три раздела, 16 вакансий, таблицы legacy_vacancy_stat / legacy_vacancy_response
 * с детерминированными счётчиками и откликами.
 *
 * Запуск из корня сайта:  php docs/legacy/seed.php
 * Повторный запуск безопасен: инфоблок и элементы не дублируются, счётчики и отклики
 * сбрасываются к исходным значениям (это и нужно перед записью golden-вывода).
 */

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../www');
$documentRoot = $_SERVER['DOCUMENT_ROOT'];

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('BX_CRONTAB', true);
define('BX_WITH_ON_AFTER_EPILOG', true);

require $documentRoot . '/bitrix/modules/main/include/prolog_before.php';

// В CLI ядро прячет текст исключения за «The script encountered an error», если в .settings.php выключен debug.
// Нам нужен текст: показываем его сами.
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'ОШИБКА: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
});

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\SiteTable;

Loader::requireModule('iblock');

$connection = Application::getConnection();
$helper = $connection->getSqlHelper();

function out(string $line): void
{
    echo $line, PHP_EOL;
}

function fail(string $message): never
{
    fwrite(STDERR, 'ОШИБКА: ' . $message . PHP_EOL);
    exit(1);
}

// ---------------------------------------------------------------------------------------
// Сайт
// ---------------------------------------------------------------------------------------

$site = SiteTable::getList(['select' => ['LID'], 'filter' => ['=DEF' => 'Y'], 'limit' => 1])->fetch();
$siteId = $site['LID'] ?? 's1';

// ---------------------------------------------------------------------------------------
// Тип инфоблока
// ---------------------------------------------------------------------------------------

$typeId = 'legacy';
if (!CIBlockType::GetByID($typeId)->Fetch()) {
    $lang = [];
    $rsLang = \Bitrix\Main\Localization\LanguageTable::getList(['select' => ['LID']]);
    while ($langRow = $rsLang->fetch()) {
        $lang[$langRow['LID']] = $langRow['LID'] === 'ru'
            ? ['NAME' => 'Легаси', 'SECTION_NAME' => 'Направления', 'ELEMENT_NAME' => 'Вакансии']
            : ['NAME' => 'Legacy', 'SECTION_NAME' => 'Sections', 'ELEMENT_NAME' => 'Vacancies'];
    }
    $type = new CIBlockType();
    $ok = $type->Add([
        'ID' => $typeId,
        'SECTIONS' => 'Y',
        'IN_RSS' => 'N',
        'SORT' => 900,
        'LANG' => $lang,
    ]);
    if (!$ok) {
        fail('тип инфоблока: ' . $type->LAST_ERROR);
    }
    out("Тип инфоблока `{$typeId}` создан");
} else {
    out("Тип инфоблока `{$typeId}` уже есть");
}

// ---------------------------------------------------------------------------------------
// Инфоблок
// ---------------------------------------------------------------------------------------

$iblockCode = 'VACANCIES';
$iblock = CIBlock::GetList([], ['CODE' => $iblockCode, 'TYPE' => $typeId, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if ($iblock) {
    $iblockId = (int)$iblock['ID'];
    out("Инфоблок «Вакансии» уже есть, ID={$iblockId}");
    if (empty($iblock['API_CODE'])) {
        (new CIBlock())->Update($iblockId, ['API_CODE' => 'Vacancy']);
        out('  API_CODE=Vacancy проставлен');
    }
} else {
    $ib = new CIBlock();
    $iblockId = (int)$ib->Add([
        'ACTIVE' => 'Y',
        'NAME' => 'Вакансии',
        'CODE' => $iblockCode,
        'API_CODE' => 'Vacancy',
        'IBLOCK_TYPE_ID' => $typeId,
        'SITE_ID' => [$siteId],
        'SORT' => 100,
        'VERSION' => 2,
        'GROUP_ID' => [1 => 'X', 2 => 'R'],
        'LIST_PAGE_URL' => '/vacancies/',
        'DETAIL_PAGE_URL' => '/vacancies/?CODE=#CODE#',
        'INDEX_ELEMENT' => 'N',
        'INDEX_SECTION' => 'N',
        'DESCRIPTION' => 'Учебный инфоблок для занятия 3 воркшопа Agentic Bitrix. Данные — docs/legacy/seed.php.',
    ]);
    if ($iblockId <= 0) {
        fail('инфоблок: ' . $ib->LAST_ERROR);
    }
    out("Инфоблок «Вакансии» создан, ID={$iblockId}");
}

// ---------------------------------------------------------------------------------------
// Свойства
// ---------------------------------------------------------------------------------------

$properties = [
    'CITY' => [
        'NAME' => 'Город', 'PROPERTY_TYPE' => 'L', 'LIST_TYPE' => 'L', 'SORT' => 100,
        'VALUES' => [
            ['VALUE' => 'Москва', 'XML_ID' => 'moscow', 'SORT' => 100],
            ['VALUE' => 'Санкт-Петербург', 'XML_ID' => 'spb', 'SORT' => 200],
            ['VALUE' => 'Удалённо', 'XML_ID' => 'remote', 'SORT' => 300],
        ],
    ],
    'SALARY_FROM' => ['NAME' => 'Зарплата от', 'PROPERTY_TYPE' => 'N', 'SORT' => 200],
    'SALARY_TO' => ['NAME' => 'Зарплата до', 'PROPERTY_TYPE' => 'N', 'SORT' => 300],
    'EXPERIENCE' => [
        'NAME' => 'Опыт', 'PROPERTY_TYPE' => 'L', 'LIST_TYPE' => 'L', 'SORT' => 400,
        'VALUES' => [
            ['VALUE' => 'Без опыта', 'XML_ID' => 'none', 'SORT' => 100],
            ['VALUE' => '1–3 года', 'XML_ID' => 'middle', 'SORT' => 200],
            ['VALUE' => '3+ лет', 'XML_ID' => 'senior', 'SORT' => 300],
        ],
    ],
    'TAGS' => ['NAME' => 'Теги', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y', 'SORT' => 500],
    'HOT' => [
        'NAME' => 'Горячая', 'PROPERTY_TYPE' => 'L', 'LIST_TYPE' => 'C', 'SORT' => 600,
        'VALUES' => [['VALUE' => 'Да', 'XML_ID' => 'Y', 'SORT' => 100]],
    ],
    'CONTACT_EMAIL' => ['NAME' => 'E-mail контакта', 'PROPERTY_TYPE' => 'S', 'SORT' => 700],
];

foreach ($properties as $code => $fields) {
    $exists = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
    if ($exists) {
        continue;
    }
    $prop = new CIBlockProperty();
    $propId = $prop->Add(array_merge([
        'IBLOCK_ID' => $iblockId,
        'CODE' => $code,
        'ACTIVE' => 'Y',
        'MULTIPLE' => 'N',
        'FILTRABLE' => 'Y',
    ], $fields));
    if (!$propId) {
        fail("свойство {$code}: " . $prop->LAST_ERROR);
    }
    out("  свойство {$code} создано");
}

$enum = []; // CODE => XML_ID => ID
$rsEnum = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId]);
while ($row = $rsEnum->Fetch()) {
    $enum[$row['PROPERTY_CODE']][$row['XML_ID']] = (int)$row['ID'];
}

// ---------------------------------------------------------------------------------------
// Разделы
// ---------------------------------------------------------------------------------------

$sections = [
    'dev' => ['NAME' => 'Разработка', 'SORT' => 100],
    'sales' => ['NAME' => 'Продажи', 'SORT' => 200],
    'support' => ['NAME' => 'Поддержка', 'SORT' => 300],
];
$sectionIds = [];
foreach ($sections as $code => $fields) {
    $row = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, '=CODE' => $code], false, ['ID'])->Fetch();
    if ($row) {
        $sectionIds[$code] = (int)$row['ID'];
        continue;
    }
    $section = new CIBlockSection();
    $id = $section->Add(['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CODE' => $code] + $fields);
    if (!$id) {
        fail("раздел {$code}: " . $section->LAST_ERROR);
    }
    $sectionIds[$code] = (int)$id;
    out("  раздел {$fields['NAME']} создан");
}

// ---------------------------------------------------------------------------------------
// Вакансии. daysAgo — дата публикации относительно сегодня: порядок «по дате» детерминирован.
// ---------------------------------------------------------------------------------------

$vacancies = [
    ['code' => 'senior-php-bitrix', 'name' => 'Senior PHP-разработчик (Битрикс)', 'section' => 'dev', 'city' => 'moscow', 'exp' => 'senior',
        'from' => 280000, 'to' => 350000, 'tags' => ['php', 'bitrix', 'd7', 'mysql'], 'hot' => true, 'daysAgo' => 1,
        'preview' => 'Ведущий разработчик в команду highload-каталога: 2 млн товаров, интеграции с 1С и маркетплейсами.',
        'detail' => '<p>Ищем сильного бэкендера на Битрикс, который не боится ядра и любит D7.</p><ul><li>Проектирование модулей и сервисного слоя</li><li>Оптимизация выборок и кеширования</li><li>Ревью кода команды из 4 человек</li></ul>'],
    ['code' => 'middle-php-developer', 'name' => 'Middle PHP-разработчик', 'section' => 'dev', 'city' => 'remote', 'exp' => 'middle',
        'from' => 160000, 'to' => 220000, 'tags' => ['php', 'bitrix', 'git'], 'hot' => false, 'daysAgo' => 2,
        'preview' => 'Поддержка и развитие корпоративных порталов на Битрикс, удалённо, гибкий график.',
        'detail' => '<p>Задачи: доработка компонентов, интеграции по REST, миграция легаси на D7.</p>'],
    ['code' => 'frontend-vue', 'name' => 'Frontend-разработчик (Vue 3)', 'section' => 'dev', 'city' => 'spb', 'exp' => 'middle',
        'from' => 170000, 'to' => 240000, 'tags' => ['vue', 'typescript', 'bitrix'], 'hot' => true, 'daysAgo' => 3,
        'preview' => 'Личный кабинет клиента на Vue 3 поверх Bitrix REST. Дизайн-система уже есть.',
        'detail' => '<p>Нужен человек, который доведёт кабинет до релиза и будет развивать его дальше.</p>'],
    ['code' => 'devops-engineer', 'name' => 'DevOps-инженер', 'section' => 'dev', 'city' => 'moscow', 'exp' => 'senior',
        'from' => 250000, 'to' => 320000, 'tags' => ['docker', 'gitlab-ci', 'nginx', 'mysql'], 'hot' => false, 'daysAgo' => 5,
        'preview' => 'Пайплайны для 20 проектов на Битрикс, стенды, мониторинг, бэкапы.',
        'detail' => '<p>Единственный DevOps в компании сейчас — это техлид. Так больше нельзя.</p>'],
    ['code' => 'qa-engineer', 'name' => 'QA-инженер (ручное + автотесты)', 'section' => 'dev', 'city' => 'remote', 'exp' => 'middle',
        'from' => 120000, 'to' => 160000, 'tags' => ['qa', 'playwright', 'phpunit'], 'hot' => false, 'daysAgo' => 7,
        'preview' => 'Регресс перед релизами, автотесты на Playwright, участие в приёмке.',
        'detail' => '<p>Тестов сейчас почти нет. Будете первым, кто их напишет.</p>'],
    ['code' => 'junior-php', 'name' => 'Junior PHP-разработчик', 'section' => 'dev', 'city' => 'spb', 'exp' => 'none',
        'from' => 70000, 'to' => 100000, 'tags' => ['php', 'bitrix', 'обучение'], 'hot' => false, 'daysAgo' => 9,
        'preview' => 'Стажировка с наставником: три месяца на учебных задачах, потом в команду.',
        'detail' => '<p>Достаточно базового PHP и желания разбираться в Битриксе.</p>'],
    ['code' => 'tech-lead', 'name' => 'Технический лидер направления Bitrix', 'section' => 'dev', 'city' => 'moscow', 'exp' => 'senior',
        'from' => 350000, 'to' => 0, 'tags' => ['bitrix', 'архитектура', 'управление'], 'hot' => true, 'daysAgo' => 12,
        'preview' => 'Архитектура, найм, обучение команды, внедрение AI-инструментов в разработку.',
        'detail' => '<p>Половина времени — код и ревью, половина — люди и процессы.</p>'],
    ['code' => 'sales-manager-b2b', 'name' => 'Менеджер по продажам B2B', 'section' => 'sales', 'city' => 'moscow', 'exp' => 'middle',
        'from' => 90000, 'to' => 200000, 'tags' => ['b2b & b2c', 'crm', 'битрикс24'], 'hot' => true, 'daysAgo' => 2,
        'preview' => 'Продажа внедрений Битрикс24 среднему бизнесу. Оклад + процент.',
        'detail' => '<p>Тёплые лиды с сайта и партнёрской сети. Средний чек — 800 тысяч.</p>'],
    ['code' => 'account-manager', 'name' => 'Аккаунт-менеджер (B2B & Enterprise)', 'section' => 'sales', 'city' => 'spb', 'exp' => 'middle',
        'from' => 100000, 'to' => 140000, 'tags' => ['клиенты', 'crm', 'поддержка'], 'hot' => false, 'daysAgo' => 6,
        'preview' => 'Ведение <20 клиентов на поддержке: планирование, отчёты, апсейл. Формат "клиент → задача → отчёт".',
        'detail' => '<p>Нужен человек, который держит обещания и умеет говорить «нет» без потери клиента.</p>'],
    ['code' => 'presale-engineer', 'name' => 'Presale-инженер', 'section' => 'sales', 'city' => 'remote', 'exp' => 'senior',
        'from' => 180000, 'to' => 250000, 'tags' => ['битрикс24', 'интеграции', 'презентации'], 'hot' => false, 'daysAgo' => 14,
        'preview' => 'Демо, оценка и архитектура решения до подписания договора.',
        'detail' => '<p>Половина работы — понять, что клиенту на самом деле нужно.</p>'],
    ['code' => 'sales-intern', 'name' => 'Стажёр отдела продаж', 'section' => 'sales', 'city' => 'moscow', 'exp' => 'none',
        'from' => 50000, 'to' => 70000, 'tags' => ['стажировка', 'crm'], 'hot' => false, 'daysAgo' => 20,
        'preview' => 'Квалификация входящих заявок, ведение CRM, обучение продукту.',
        'detail' => '<p>Через полгода — менеджер с собственным портфелем.</p>'],
    ['code' => 'support-l1', 'name' => 'Специалист техподдержки (1-я линия)', 'section' => 'support', 'city' => 'remote', 'exp' => 'none',
        'from' => 60000, 'to' => 80000, 'tags' => ['поддержка', 'битрикс24', 'helpdesk'], 'hot' => false, 'daysAgo' => 4,
        'preview' => 'Ответы на обращения клиентов в helpdesk, эскалация на 2-ю линию.',
        'detail' => '<p>График 2/2, обучение две недели, наставник.</p>'],
    ['code' => 'support-l2', 'name' => 'Инженер поддержки (2-я линия, Битрикс)', 'section' => 'support', 'city' => 'spb', 'exp' => 'middle',
        'from' => 110000, 'to' => 150000, 'tags' => ['bitrix', 'php', 'mysql', 'поддержка'], 'hot' => true, 'daysAgo' => 8,
        'preview' => 'Разбор инцидентов на проектах клиентов: логи, БД, хотфиксы.',
        'detail' => '<p>Нужно уметь читать чужой код и не бояться продакшена.</p>'],
    ['code' => 'support-lead', 'name' => 'Руководитель поддержки', 'section' => 'support', 'city' => 'moscow', 'exp' => 'senior',
        'from' => 200000, 'to' => 260000, 'tags' => ['управление', 'sla', 'поддержка'], 'hot' => false, 'daysAgo' => 16,
        'preview' => 'Команда из 8 человек, SLA, процессы, ротация дежурств.',
        'detail' => '<p>Отчётность перед клиентами и руководством, найм и обучение.</p>'],
    // неактивные — в списке и на детальной их быть не должно
    ['code' => 'closed-php-2024', 'name' => 'PHP-разработчик (вакансия закрыта)', 'section' => 'dev', 'city' => 'moscow', 'exp' => 'middle',
        'from' => 150000, 'to' => 200000, 'tags' => ['php'], 'hot' => false, 'daysAgo' => 400, 'active' => false,
        'preview' => 'Если вы видите эту вакансию в списке — фильтр по активности сломан.',
        'detail' => '<p>Закрыта.</p>'],
    ['code' => 'closed-sales-2023', 'name' => 'Менеджер по продажам (вакансия закрыта)', 'section' => 'sales', 'city' => 'spb', 'exp' => 'middle',
        'from' => 90000, 'to' => 150000, 'tags' => ['crm'], 'hot' => true, 'daysAgo' => 700, 'active' => false,
        'preview' => 'Если вы видите эту вакансию в списке — фильтр по активности сломан.',
        'detail' => '<p>Закрыта.</p>'],
];

$element = new CIBlockElement();
$idByCode = [];
$created = 0;
$updated = 0;

foreach ($vacancies as $v) {
    $row = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, '=CODE' => $v['code']], false, false, ['ID'])->Fetch();

    $propertyValues = [
        'CITY' => $enum['CITY'][$v['city']] ?? false,
        'SALARY_FROM' => $v['from'] > 0 ? $v['from'] : false,
        'SALARY_TO' => $v['to'] > 0 ? $v['to'] : false,
        'EXPERIENCE' => $enum['EXPERIENCE'][$v['exp']] ?? false,
        'TAGS' => $v['tags'],
        'HOT' => $v['hot'] ? ($enum['HOT']['Y'] ?? false) : false,
        'CONTACT_EMAIL' => 'hr@example.com',
    ];

    $fields = [
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $sectionIds[$v['section']],
        'ACTIVE' => ($v['active'] ?? true) ? 'Y' : 'N',
        'ACTIVE_FROM' => ConvertTimeStamp(time() - $v['daysAgo'] * 86400, 'FULL'),
        'NAME' => $v['name'],
        'CODE' => $v['code'],
        'SORT' => 500,
        'PREVIEW_TEXT' => $v['preview'],
        'PREVIEW_TEXT_TYPE' => 'text',
        'DETAIL_TEXT' => $v['detail'],
        'DETAIL_TEXT_TYPE' => 'html',
        'PROPERTY_VALUES' => $propertyValues,
    ];

    if ($row) {
        // элемент уже есть — приводим к эталону (имя, даты, свойства), ID сохраняется
        if (!$element->Update((int)$row['ID'], $fields)) {
            fail("вакансия {$v['code']} (update): " . $element->LAST_ERROR);
        }
        $idByCode[$v['code']] = (int)$row['ID'];
        $updated++;
        continue;
    }

    $id = $element->Add($fields);
    if (!$id) {
        fail("вакансия {$v['code']}: " . $element->LAST_ERROR);
    }
    $idByCode[$v['code']] = (int)$id;
    $created++;
}
out("Вакансий: " . count($idByCode) . " (создано: {$created}, обновлено: {$updated})");

// ---------------------------------------------------------------------------------------
// Таблицы легаси-статистики. Создаются сырым SQL — так же, как их когда-то создали на проде.
// ---------------------------------------------------------------------------------------

$connection->queryExecute(
    'CREATE TABLE IF NOT EXISTS legacy_vacancy_stat (
        VACANCY_ID int NOT NULL,
        VIEWS int NOT NULL DEFAULT 0,
        LAST_VIEW datetime NULL,
        PRIMARY KEY (VACANCY_ID)
    )'
);
$connection->queryExecute(
    'CREATE TABLE IF NOT EXISTS legacy_vacancy_response (
        ID int NOT NULL AUTO_INCREMENT,
        VACANCY_ID int NOT NULL,
        USER_ID int NOT NULL DEFAULT 0,
        NAME varchar(100) NOT NULL DEFAULT \'\',
        EMAIL varchar(100) NOT NULL DEFAULT \'\',
        PHONE varchar(30) NOT NULL DEFAULT \'\',
        MESSAGE text NULL,
        IP varchar(45) NOT NULL DEFAULT \'\',
        STATUS varchar(10) NOT NULL DEFAULT \'NEW\',
        CREATED datetime NOT NULL,
        PRIMARY KEY (ID),
        KEY IX_LEGACY_RESPONSE_VACANCY (VACANCY_ID)
    )'
);
out('Таблицы legacy_vacancy_stat, legacy_vacancy_response на месте');

// Просмотры: большие разрывы между соседями, чтобы пара лишних просмотров при записи golden
// не меняла порядок «популярных».
$views = [
    'senior-php-bitrix' => 340,
    'frontend-vue' => 260,
    'devops-engineer' => 190,
    'sales-manager-b2b' => 120,
    'support-l2' => 70,
    'tech-lead' => 41,
    'middle-php-developer' => 33,
    'presale-engineer' => 27,
    'account-manager' => 22,
    'qa-engineer' => 18,
    'support-lead' => 15,
    'junior-php' => 11,
    'support-l1' => 9,
    'sales-intern' => 6,
    'closed-php-2024' => 500, // неактивная с большим счётчиком: «популярные» обязаны её отфильтровать
];

$connection->queryExecute('DELETE FROM legacy_vacancy_stat');
foreach ($views as $code => $count) {
    if (!isset($idByCode[$code])) {
        continue;
    }
    $connection->queryExecute(sprintf(
        'INSERT INTO legacy_vacancy_stat (VACANCY_ID, VIEWS, LAST_VIEW) VALUES (%d, %d, %s)',
        $idByCode[$code],
        $count,
        $helper->getCurrentDateTimeFunction()
    ));
}
out('Просмотры сброшены к исходным значениям');

// Отклики: часть за последнюю неделю (сводка в сайдбаре), одна помечена SPAM (не считается).
$responses = [
    ['senior-php-bitrix', 'Алексей', 'alexey@example.com', 2, 'NEW'],
    ['senior-php-bitrix', 'Мария', 'maria@example.com', 5, 'VIEWED'],
    ['senior-php-bitrix', 'Бот', 'spam@example.com', 1, 'SPAM'],
    ['frontend-vue', 'Илья', 'ilya@example.com', 1, 'NEW'],
    ['frontend-vue', 'Ольга', 'olga@example.com', 12, 'VIEWED'],
    ['devops-engineer', 'Сергей', 'sergey@example.com', 3, 'NEW'],
    ['sales-manager-b2b', 'Наталья', 'natalia@example.com', 4, 'NEW'],
    ['sales-manager-b2b', 'Дмитрий', 'dmitry@example.com', 20, 'VIEWED'],
    ['support-l2', 'Кирилл', 'kirill@example.com', 6, 'NEW'],
    ['junior-php', 'Анна', 'anna@example.com', 2, 'NEW'],
    ['junior-php', 'Павел', 'pavel@example.com', 9, 'VIEWED'],
    ['support-l1', 'Егор', 'egor@example.com', 30, 'VIEWED'],
];

$connection->queryExecute('DELETE FROM legacy_vacancy_response');
$connection->queryExecute('ALTER TABLE legacy_vacancy_response AUTO_INCREMENT = 1');
foreach ($responses as [$code, $name, $email, $daysAgo, $status]) {
    if (!isset($idByCode[$code])) {
        continue;
    }
    $connection->queryExecute(sprintf(
        'INSERT INTO legacy_vacancy_response (VACANCY_ID, USER_ID, NAME, EMAIL, PHONE, MESSAGE, IP, STATUS, CREATED)
         VALUES (%d, 0, \'%s\', \'%s\', \'\', \'%s\', \'127.0.0.1\', \'%s\', DATE_SUB(%s, INTERVAL %d DAY))',
        $idByCode[$code],
        $helper->forSql($name),
        $helper->forSql($email),
        $helper->forSql('Здравствуйте! Заинтересовала вакансия, готов(а) обсудить детали.'),
        $status,
        $helper->getCurrentDateTimeFunction(),
        $daysAgo
    ));
}
out('Отклики сброшены к исходным значениям (' . count($responses) . ')');

out('');
out('Готово. Откройте /vacancies/ — список должен показать 14 активных вакансий на 3 страницах.');
