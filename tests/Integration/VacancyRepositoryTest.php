<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Ws\Vacancies\Dto\VacancyFilterDto;
use Ws\Vacancies\Model\VacancyStatTable;
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

describe('sort=views: tie-break и LEFT JOIN', function () {
    /**
     * @return array<string, mixed>|null
     */
    $fetchStat = static function (int $vacancyId): ?array {
        $row = VacancyStatTable::query()
            ->setSelect(['VACANCY_ID', 'VIEWS'])
            ->where('VACANCY_ID', $vacancyId)
            ->setLimit(1)
            ->fetch();

        return $row === false ? null : $row;
    };

    $upsertViews = static function (int $vacancyId, int $views) use ($fetchStat): void {
        $fields = [
            'VIEWS' => $views,
            'LAST_VIEW' => new DateTime(),
        ];
        if ($fetchStat($vacancyId) !== null) {
            VacancyStatTable::update($vacancyId, $fields);

            return;
        }

        VacancyStatTable::add(['VACANCY_ID' => $vacancyId] + $fields);
    };

    /**
     * @return array{repo: VacancyRepository, lowId: int, highId: int, lowCode: string, highCode: string, originalViews: array<int, int>}
     */
    $prepareEqualViewsPair = function () use ($fetchStat, $upsertViews): array {
        $repo = new VacancyRepository();
        $low = $repo->getByCode('junior-php');
        $high = $repo->getByCode('support-l1');

        expect($low)->not->toBeNull()
            ->and($high)->not->toBeNull();

        $lowId = (int)$low['ID'];
        $highId = (int)$high['ID'];
        expect($highId)->toBeGreaterThan($lowId);

        $originalViews = [];
        foreach ([$lowId, $highId] as $id) {
            $row = $fetchStat($id);
            $originalViews[$id] = $row !== null ? (int)$row['VIEWS'] : 0;
            $upsertViews($id, 42);
        }

        return [
            'repo' => $repo,
            'lowId' => $lowId,
            'highId' => $highId,
            'lowCode' => 'junior-php',
            'highCode' => 'support-l1',
            'originalViews' => $originalViews,
        ];
    };

    $restoreViews = function (array $originalViews) use ($upsertViews): void {
        foreach ($originalViews as $id => $views) {
            $upsertViews((int)$id, (int)$views);
        }
    };

    test('при равных VIEWS порядок ID DESC', function () use ($prepareEqualViewsPair, $restoreViews) {
        $ctx = $prepareEqualViewsPair();

        try {
            $result = $ctx['repo']->findList(
                new VacancyFilterDto(pageSize: 50),
                ['STAT.VIEWS' => 'DESC', 'ID' => 'DESC'],
            );

            $codes = array_map(static fn(array $row): string => (string)$row['CODE'], $result['rows']);
            $highPos = array_search($ctx['highCode'], $codes, true);
            $lowPos = array_search($ctx['lowCode'], $codes, true);

            expect($highPos)->not->toBeFalse()
                ->and($lowPos)->not->toBeFalse()
                ->and($highPos)->toBeLessThan($lowPos);
        } finally {
            $restoreViews($ctx['originalViews']);
        }
    });

    test('вакансия без строки STAT остаётся в выдаче и сортируется как 0 просмотров', function () use ($fetchStat, $upsertViews) {
        $repo = new VacancyRepository();
        $withZero = $repo->getByCode('junior-php');
        $withoutStat = $repo->getByCode('support-l1');

        expect($withZero)->not->toBeNull()
            ->and($withoutStat)->not->toBeNull();

        $zeroId = (int)$withZero['ID'];
        $missingStatId = (int)$withoutStat['ID'];
        expect($missingStatId)->toBeGreaterThan($zeroId);

        $zeroStatBefore = $fetchStat($zeroId);
        $missingStatBefore = $fetchStat($missingStatId);
        expect($zeroStatBefore)->not->toBeNull()
            ->and($missingStatBefore)->not->toBeNull();

        $savedZeroViews = (int)$zeroStatBefore['VIEWS'];
        $savedMissingViews = (int)$missingStatBefore['VIEWS'];
        $connection = Application::getConnection();

        try {
            $upsertViews($zeroId, 0);
            $connection->queryExecute(
                'DELETE FROM ' . VacancyStatTable::getTableName()
                . ' WHERE VACANCY_ID = ' . $missingStatId
            );

            $result = $repo->findList(
                new VacancyFilterDto(pageSize: 50),
                ['STAT.VIEWS' => 'DESC', 'ID' => 'DESC'],
            );

            $ids = array_map(static fn(array $row): int => (int)$row['ID'], $result['rows']);
            $missingPos = array_search($missingStatId, $ids, true);
            $zeroPos = array_search($zeroId, $ids, true);

            expect($result['total'])->toBe(14)
                ->and(count($result['rows']))->toBe(14)
                ->and($missingPos)->not->toBeFalse()
                ->and($zeroPos)->not->toBeFalse()
                // NULL ≡ 0, среди нулей — ID DESC: без строки STAT (больший ID) раньше явного нуля
                ->and($missingPos)->toBeLessThan($zeroPos);
        } finally {
            $upsertViews($zeroId, $savedZeroViews);
            $upsertViews($missingStatId, $savedMissingViews);
        }
    });
});
