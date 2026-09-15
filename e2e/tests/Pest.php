<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Браузерные тесты раздела «Вакансии»
|--------------------------------------------------------------------------
|
| Проект не знает, что сайт на Битриксе. Он открывает страницы в Chromium через
| Playwright и смотрит на них как пользователь. Поэтому URL — всегда абсолютные:
| относительный путь Pest пытается отдать своему серверу (Laravel), которого здесь нет.
|
| Сайт: SITE_URL или http://lesson3.bitrix:8765 (участники — http://workshop.bitrix).
*/

pest()->browser()->timeout(10_000);

/**
 * Абсолютный URL страницы стенда.
 */
function site(string $path = '/'): string
{
    $base = rtrim(getenv('SITE_URL') ?: 'http://lesson3-copy.bitrix:8765', '/');

    return $base . $path;
}

/**
 * Сброс данных стенда к эталону: счётчики просмотров, отклики, даты публикации.
 * Единственное место, где тесты трогают сайт не через браузер, — и оно про фикстуры, а не про CMS.
 */
function seedSite(): void
{
    $seed = dirname(__DIR__, 2) . '/docs/legacy/seed.php';

    exec('php ' . escapeshellarg($seed) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        throw new RuntimeException("seed.php завершился с ошибкой:\n" . implode("\n", $output));
    }
}
