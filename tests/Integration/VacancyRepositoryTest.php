<?php

declare(strict_types=1);

use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Repository\VacancyRepository;

/**
 * Smoke: ядро поднято, модуль подключён, данные из seed.php на месте.
 */
test('инфоблок «Вакансии» найден', function () {
    expect((new VacancyRepository())->getIblockId())->toBeGreaterThan(0);
});

test('активных вакансий 14, неактивные отфильтрованы', function () {
    $result = (new VacancyRepository())->findList(
        new VacancyFilterDto(pageSize: 50),
        ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
    );

    expect($result['total'])->toBe(14);
});
