<?php

declare(strict_types=1);

use Ws\Vacancies\Helper\Formatter;

describe('Formatter::salary', function () {
    test('вилка', fn () => expect(Formatter::salary(280000, 350000))->toBe('от 280 000 до 350 000 ₽'));
    test('только «от»', fn () => expect(Formatter::salary(350000, 0))->toBe('от 350 000 ₽'));
    test('только «до»', fn () => expect(Formatter::salary(0, 100000))->toBe('до 100 000 ₽'));
    test('без цифр — по договорённости', fn () => expect(Formatter::salary(0, 0))->toBe('по договорённости'));
});

describe('Formatter::plural', function () {
    test('склонение', function (int $n, string $expected) {
        expect(Formatter::plural($n, 'отклик', 'отклика', 'откликов'))->toBe($expected);
    })->with([
        [1, 'отклик'],
        [2, 'отклика'],
        [5, 'откликов'],
        [11, 'откликов'],
        [21, 'отклик'],
    ]);
});
