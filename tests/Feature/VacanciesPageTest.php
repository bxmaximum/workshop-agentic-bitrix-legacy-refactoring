<?php

declare(strict_types=1);

/**
 * Smoke: сайт отвечает, список вакансий отрисован на данных seed.php.
 */
test('GET /vacancies/ отдаёт список с 14 вакансиями', function () {
    $response = http()->get('/vacancies/');

    expect($response->getStatusCode())->toBe(200)
        ->and((string)$response->getBody())->toMatch('~<h1 class="lv-title">Вакансии\s*<small>\(14\)</small></h1>~u');
});

test('неизвестная вакансия — не 200', function () {
    $response = http()->get('/vacancies/?CODE=no-such-vacancy');

    expect($response->getStatusCode())->not->toBe(200);
});
