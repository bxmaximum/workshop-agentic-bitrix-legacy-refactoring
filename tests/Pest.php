<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Тесты модуля ws.vacancies
|--------------------------------------------------------------------------
|
| Три сьюта с разной ценой:
|
|  Unit         — чистые классы модуля (Helper, Dto). Ядро Битрикса не грузится,
|                 классы подхватывает PSR-4 из composer.json (../www/local/modules/ws.vacancies/lib).
|  Integration  — ядро Битрикса из ../www поднимается один раз на процесс, как в docs/legacy/seed.php,
|                 подключается модуль ws.vacancies, данные один раз сбрасываются к эталону seed.php.
|  Feature      — сайт как HTTP-сервер: Guzzle-клиент http() на SITE_URL (по умолчанию http://workshop.bitrix).
|                 Перед сьютом тоже один раз seed.
|
| Хуки привязаны к папкам, поэтому Unit-тесты не платят ни за ядро, ни за seed.
| Запуск: из tests/ — `composer test` (Pest 5 ищет Pest.php в <root>/tests, здесь он в корне проекта,
| отсюда `--test-directory .`). PHP должен быть Omut php-8.4 с PHPRC — см. AGENTS.md.
*/

use Bitrix\Main\Loader;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

pest()->beforeAll(function (): void {
    bootBitrix();
    seedSite();
})->in('Integration');

pest()->beforeAll(fn () => seedSite())->in('Feature');

// Каждый Feature-тест начинает с чистой сессии (новый CookieJar).
pest()->beforeEach(fn () => http(fresh: true))->in('Feature');

/**
 * Ядро Битрикса в CLI — ровно так же, как docs/legacy/seed.php. Один раз на процесс.
 */
function bootBitrix(): void
{
    if (defined('B_PROLOG_INCLUDED')) {
        return;
    }

    $documentRoot = realpath(__DIR__ . '/../www');
    if ($documentRoot === false) {
        throw new RuntimeException('Не найден корень сайта ../www рядом с tests/');
    }

    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;

    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    define('BX_NO_ACCELERATOR_RESET', true);
    define('BX_CRONTAB', true);
    define('BX_WITH_ON_AFTER_EPILOG', true);

    require $documentRoot . '/bitrix/modules/main/include/prolog_before.php';

    Loader::requireModule('ws.vacancies');
}

/**
 * Сброс данных стенда к эталону: инфоблок, вакансии, счётчики, отклики.
 * Как seedSite() в e2e — отдельным процессом через exec, один раз на прогон.
 * Интерпретатор берётся тот же, что выполняет тесты (PHP_BINARY), PHPRC наследуется из окружения.
 */
function seedSite(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $seed = dirname(__DIR__) . '/docs/legacy/seed.php';
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';

    exec(escapeshellarg($php) . ' ' . escapeshellarg($seed) . ' 2>&1', $output, $code);

    $log = implode("\n", $output);

    if ($code !== 0) {
        throw new RuntimeException("seed.php завершился с ошибкой:\n" . $log);
    }

    // Не тот php (без ini Omut) молча выходит с кодом 0, ничего не сделав — ловим по финальной строке.
    if (!str_contains($log, 'Готово')) {
        throw new RuntimeException("seed.php не отработал до конца (проверьте PATH/PHPRC, см. AGENTS.md):\n" . $log);
    }

    $done = true;
}

/**
 * HTTP-клиент к стенду для Feature-тестов.
 * Один клиент на тест: cookies (сессия, избранное) живут между запросами внутри теста и сбрасываются между тестами.
 */
function http(bool $fresh = false): Client
{
    static $client = null;

    if ($fresh || $client === null) {
        $client = new Client([
            'base_uri' => siteUrl() . '/',
            'http_errors' => false,
            'cookies' => new CookieJar(),
            'allow_redirects' => false,
            'timeout' => 10,
        ]);
    }

    return $client;
}

/**
 * Базовый URL стенда без завершающего слэша: SITE_URL или http://workshop.bitrix.
 */
function siteUrl(): string
{
    return rtrim(getenv('SITE_URL') ?: 'http://workshop.bitrix', '/');
}
