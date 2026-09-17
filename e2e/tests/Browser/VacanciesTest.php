<?php

declare(strict_types=1);

/**
 * Характеризационные браузерные тесты раздела /vacancies/.
 * Фиксируют текущее поведение, включая известные странности (баг №N).
 */

beforeAll(fn () => seedSite());

describe('Вакансии — список', function () {
    test('заголовок, счётчик и шаблон списка', function () {
        visit(site('/vacancies/'))
            ->assertSee('Вакансии (14)')
            ->assertPresent('.lv')
            ->assertPresent('.lv-list')
            ->assertPresent('.lv-main')
            ->assertPresent('.lv-side')
            ->assertCount('.lv-items .lv-item', 5);
    });

    test('meta description собирается из названий текущей страницы (баг №17)', function () {
        visit(site('/vacancies/'))
            ->assertSourceHas('name="description" content="Вакансии: Senior PHP-разработчик (Битрикс), Менеджер по продажам B2B, Middle PHP-разработчик, Frontend-разработчик (Vue 3), Специалист техподдержки (1-я линия)"');
    });

    test('порядок по умолчанию — по дате, страница 1', function () {
        $page = visit(site('/vacancies/'));

        $page->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Senior PHP-разработчик (Битрикс)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(2) .lv-item-name', 'Менеджер по продажам B2B')
            ->assertSeeIn('.lv-items .lv-item:nth-child(3) .lv-item-name', 'Middle PHP-разработчик')
            ->assertSeeIn('.lv-items .lv-item:nth-child(4) .lv-item-name', 'Frontend-разработчик (Vue 3)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(5) .lv-item-name', 'Специалист техподдержки (1-я линия)');
    });

    test('сортировка «по дате» активна без параметра sort', function () {
        visit(site('/vacancies/'))
            ->assertSeeIn('.lv-sort', 'по дате')
            ->assertSourceHas('<b>по дате</b>');
    });

    test('шапка и подвал раздела', function () {
        visit(site('/vacancies/'))
            ->assertSeeIn('.page-header .logo', 'Компания')
            ->assertSeeIn('.page-header nav', 'Вакансии')
            ->assertSeeIn('.page-header nav', 'Горячие')
            ->assertSeeIn('.page-header nav', 'Избранное')
            ->assertSeeIn('.page-footer', '© Компания, 2017–2026. Раздел вакансий.');
    });

    test('сайдбар: направления, популярные и сводка со SPAM (баг №2)', function () {
        visit(site('/vacancies/'))
            ->assertSeeIn('.lv-side', 'Разработка')
            ->assertSeeIn('.lv-side', '(7)')
            ->assertSeeIn('.lv-side', 'Продажи')
            ->assertSeeIn('.lv-side', '(4)')
            ->assertSeeIn('.lv-side', 'Поддержка')
            ->assertSeeIn('.lv-side', '(3)')
            ->assertSeeIn('.lv-side', 'Популярные')
            ->assertSeeIn('.lv-side', 'За неделю: 7 откликов на 6 вакансий');
    });
});

describe('Вакансии — пагинация', function () {
    test('страница 2: порядок и навигация', function () {
        visit(site('/vacancies/?page=2'))
            ->assertCount('.lv-items .lv-item', 5)
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'DevOps-инженер')
            ->assertSeeIn('.lv-items .lv-item:nth-child(5) .lv-item-name', 'Junior PHP-разработчик')
            ->assertSeeIn('.lv-nav .lv-nav-cur', '2')
            ->assertSourceHas('href="/vacancies/"')
            ->assertSourceHas('class="lv-nav-prev"')
            ->assertSourceHas('class="lv-nav-next"');
    });

    test('страница 3: 4 карточки, без «Вперёд»', function () {
        visit(site('/vacancies/?page=3'))
            ->assertCount('.lv-items .lv-item', 4)
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Технический лидер направления Bitrix')
            ->assertSeeIn('.lv-items .lv-item:nth-child(4) .lv-item-name', 'Стажёр отдела продаж')
            ->assertSeeIn('.lv-nav .lv-nav-cur', '3')
            ->assertDontSee('Вперёд');
    });

    test('ссылка на первую страницу без page=1', function () {
        visit(site('/vacancies/?page=2'))
            ->assertSourceHas('<a href="/vacancies/">1</a>')
            ->assertSourceMissing('page=1');
    });

    test('page больше максимума показывает последнюю страницу (баг №7)', function () {
        visit(site('/vacancies/?page=99'))
            ->assertCount('.lv-items .lv-item', 4)
            ->assertSeeIn('.lv-nav .lv-nav-cur', '3')
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Технический лидер направления Bitrix');
    });

    test('невалидный page сбрасывается на первую (баг №7)', function () {
        visit(site('/vacancies/?page=0'))
            ->assertSeeIn('.lv-nav .lv-nav-cur', '1')
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Senior PHP-разработчик (Битрикс)');

        visit(site('/vacancies/?page=abc'))
            ->assertSeeIn('.lv-nav .lv-nav-cur', '1');
    });

    test('пагинация сохраняет фильтры и сортировку', function () {
        visit(site('/vacancies/?section=1&sort=salary&page=2'))
            ->assertSee('Вакансии (7)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'QA-инженер (ручное + автотесты)')
            ->assertSourceHas('href="/vacancies/?section=1&amp;sort=salary"')
            ->assertSourceHas('class="lv-nav-prev"');
    });

    test('при <=5 результатах блока пагинации нет', function () {
        visit(site('/vacancies/?hot=Y'))
            ->assertSee('Вакансии (5)')
            ->assertCount('.lv-items .lv-item', 5)
            ->assertNotPresent('.lv-nav');
    });
});

describe('Вакансии — сортировка', function () {
    test('по зарплате: убывание SALARY_FROM', function () {
        visit(site('/vacancies/?sort=salary'))
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Технический лидер направления Bitrix')
            ->assertSeeIn('.lv-items .lv-item:nth-child(2) .lv-item-name', 'Senior PHP-разработчик (Битрикс)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(3) .lv-item-name', 'DevOps-инженер')
            ->assertSeeIn('.lv-items .lv-item:nth-child(4) .lv-item-name', 'Руководитель поддержки')
            ->assertSeeIn('.lv-items .lv-item:nth-child(5) .lv-item-name', 'Presale-инженер');
    });

    test('параметр order игнорируется при sort=salary', function () {
        visit(site('/vacancies/?sort=salary&order=asc'))
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Технический лидер направления Bitrix');

        visit(site('/vacancies/?sort=salary&order=desc'))
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Технический лидер направления Bitrix');
    });

    test('по названию: латиница, затем кириллица', function () {
        visit(site('/vacancies/?sort=name'))
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'DevOps-инженер')
            ->assertSeeIn('.lv-items .lv-item:nth-child(2) .lv-item-name', 'Frontend-разработчик (Vue 3)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(3) .lv-item-name', 'Junior PHP-разработчик')
            ->assertSeeIn('.lv-items .lv-item:nth-child(4) .lv-item-name', 'Middle PHP-разработчик')
            ->assertSeeIn('.lv-items .lv-item:nth-child(5) .lv-item-name', 'Presale-инженер');
    });

    test('по просмотрам сортирует глобально до пагинации', function () {
        visit(site('/vacancies/?sort=views'))
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Senior PHP-разработчик (Битрикс)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(2) .lv-item-name', 'Frontend-разработчик (Vue 3)')
            ->assertSeeIn('.lv-items .lv-item:nth-child(3) .lv-item-name', 'DevOps-инженер')
            ->assertSeeIn('.lv-items .lv-item:nth-child(4) .lv-item-name', 'Менеджер по продажам B2B')
            ->assertSeeIn('.lv-items .lv-item:nth-child(5) .lv-item-name', 'Инженер поддержки (2-я линия, Битрикс)');

        visit(site('/vacancies/?sort=views&page=2'))
            ->assertDontSeeIn('.lv-items', 'DevOps-инженер');
    });

    test('неизвестный sort откатывается к дате', function () {
        visit(site('/vacancies/?sort=foo'))
            ->assertSourceHas('<b>по дате</b>')
            ->assertSeeIn('.lv-items .lv-item:nth-child(1) .lv-item-name', 'Senior PHP-разработчик (Битрикс)');
    });
});

describe('Вакансии — фильтры', function () {
    test('направление Разработка: 7 вакансий и 2 страницы', function () {
        visit(site('/vacancies/?section=1'))
            ->assertSee('Вакансии (7)')
            ->assertCount('.lv-items .lv-item', 5)
            ->assertPresent('.lv-nav')
            ->assertSee('Сбросить фильтр');
    });

    test('направление Продажи: 4 вакансии без пагинации', function () {
        visit(site('/vacancies/?section=2'))
            ->assertSee('Вакансии (4)')
            ->assertCount('.lv-items .lv-item', 4)
            ->assertNotPresent('.lv-nav');
    });

    test('направление Поддержка: 3 вакансии без пагинации', function () {
        visit(site('/vacancies/?section=3'))
            ->assertSee('Вакансии (3)')
            ->assertCount('.lv-items .lv-item', 3)
            ->assertNotPresent('.lv-nav');
    });

    test('несуществующий section даёт пустую выдачу', function () {
        visit(site('/vacancies/?section=99'))
            ->assertSee('Вакансии (0)')
            ->assertSeeIn('.lv-empty', 'По вашему запросу вакансий нет.')
            ->assertSeeIn('.lv-empty', 'Сбросить фильтр')
            ->assertPresent('.lv-side')
            ->assertSeeIn('.lv-side', 'Направления')
            ->assertSeeIn('.lv-side', 'Популярные')
            ->assertSeeIn('.lv-side', 'За неделю:');
    });

    test('строковый section=dev игнорируется', function () {
        visit(site('/vacancies/?section=dev'))
            ->assertSee('Вакансии (14)');
    });

    test('город Москва: 6 вакансий', function () {
        visit(site('/vacancies/?city=1'))
            ->assertSee('Вакансии (6)')
            ->assertCount('.lv-items .lv-item', 5)
            ->assertPresent('.lv-nav');
    });

    test('город Санкт-Петербург: 4 вакансии', function () {
        visit(site('/vacancies/?city=2'))
            ->assertSee('Вакансии (4)')
            ->assertNotPresent('.lv-nav');
    });

    test('город Удалённо: 4 вакансии', function () {
        visit(site('/vacancies/?city=3'))
            ->assertSee('Вакансии (4)');
    });

    test('опыт: без опыта / 1–3 / 3+', function () {
        visit(site('/vacancies/?exp=4'))->assertSee('Вакансии (3)');
        visit(site('/vacancies/?exp=5'))->assertSee('Вакансии (6)');
        visit(site('/vacancies/?exp=6'))->assertSee('Вакансии (5)');
    });

    test('salary фильтрует по SALARY_FROM, игнорируя SALARY_TO (баг №8)', function () {
        visit(site('/vacancies/?salary=200000'))
            ->assertSee('Вакансии (4)')
            ->assertSeeIn('.lv-items', 'Технический лидер направления Bitrix')
            ->assertSeeIn('.lv-items', 'Senior PHP-разработчик (Битрикс)')
            ->assertSeeIn('.lv-items', 'DevOps-инженер')
            ->assertSeeIn('.lv-items', 'Руководитель поддержки')
            ->assertDontSeeIn('.lv-items', 'Менеджер по продажам B2B');

        visit(site('/vacancies/?salary=300000'))
            ->assertSee('Вакансии (1)')
            ->assertSeeIn('.lv-items', 'Технический лидер направления Bitrix');

        visit(site('/vacancies/?salary=400000'))
            ->assertSee('Вакансии (0)')
            ->assertSeeIn('.lv-empty', 'По вашему запросу вакансий нет.');
    });

    test('hot=Y: ровно 5 горячих, hot=N не фильтрует', function () {
        visit(site('/vacancies/?hot=Y'))
            ->assertSee('Вакансии (5)')
            ->assertSee('Senior PHP-разработчик (Битрикс)')
            ->assertSee('Менеджер по продажам B2B')
            ->assertSee('Frontend-разработчик (Vue 3)')
            ->assertSee('Инженер поддержки (2-я линия, Битрикс)')
            ->assertSee('Технический лидер направления Bitrix');

        visit(site('/vacancies/?hot=N'))
            ->assertSee('Вакансии (14)');
    });

    test('fav=Y без избранного — пустой список', function () {
        visit(site('/vacancies/?fav=Y'))
            ->assertSee('Вакансии (0)')
            ->assertSeeIn('.lv-empty', 'По вашему запросу вакансий нет.');
    });

    test('поиск q регистронезависим и ищет в PREVIEW_TEXT (баг №9)', function () {
        visit(site('/vacancies/?q=PHP'))
            ->assertSee('Вакансии (5)');

        visit(site('/vacancies/?q=php'))
            ->assertSee('Вакансии (5)');

        visit(site('/vacancies/?q=highload'))
            ->assertSee('Вакансии (1)')
            ->assertSee('Senior PHP-разработчик (Битрикс)');
    });

    test('поиск q=b2b+%26+b2c находит sales-manager-b2b', function () {
        visit(site('/vacancies/?q=b2b+%26+b2c'))
            ->assertSee('Вакансии (1)')
            ->assertSee('Менеджер по продажам B2B');
    });

    test('сырой & в q разрывает параметры URL (баг №14)', function () {
        visit(site('/vacancies/?q=b2b%20&%20b2c'))
            ->assertSee('Вакансии (2)')
            ->assertSee('Менеджер по продажам B2B')
            ->assertSee('Аккаунт-менеджер (B2B & Enterprise)')
            ->assertSourceHas('name="q" value="b2b"');
    });

    test('экранирование value у поля q', function () {
        visit(site('/vacancies/?q=%22quoted%22'))
            ->assertSourceHas('name="q" value="&quot;quoted&quot;"');

        visit(site('/vacancies/?q=b2b+%26+b2c'))
            ->assertSourceHas('name="q" value="b2b &amp; b2c"');
    });

    test('комбинированные фильтры', function () {
        visit(site('/vacancies/?hot=Y&section=1'))
            ->assertSee('Вакансии (3)')
            ->assertSee('Senior PHP-разработчик (Битрикс)')
            ->assertSee('Frontend-разработчик (Vue 3)')
            ->assertSee('Технический лидер направления Bitrix');

        visit(site('/vacancies/?city=1&exp=6'))
            ->assertSee('Вакансии (4)');

        visit(site('/vacancies/?city=2&section=1'))
            ->assertSee('Вакансии (2)')
            ->assertSee('Frontend-разработчик (Vue 3)')
            ->assertSee('Junior PHP-разработчик');

        visit(site('/vacancies/?salary=250000&exp=6'))
            ->assertSee('Вакансии (3)');
    });
});

describe('Вакансии — карточка списка', function () {
    test('горячая карточка: класс, бейджи, зарплата, плюрализация', function () {
        visit(site('/vacancies/'))
            ->assertPresent('#vacancy-1.lv-item-hot')
            ->assertSeeIn('#vacancy-1', 'Горячая')
            ->assertSeeIn('#vacancy-1', 'Новая')
            ->assertSeeIn('#vacancy-1 .lv-salary', 'от 280 000 до 350 000 ₽')
            ->assertSeeIn('#vacancy-1 .lv-item-foot', '2 отклика')
            ->assertSeeIn('#vacancy-12 .lv-item-foot', '1 отклик')
            ->assertSeeIn('#vacancy-2 .lv-item-foot', '0 откликов');
    });

    test('бейдж «Новая» только у вакансий <= 2 дней (баг №18)', function () {
        visit(site('/vacancies/'))
            ->assertPresent('#vacancy-1 .lv-badge-new')
            ->assertPresent('#vacancy-8 .lv-badge-new')
            ->assertPresent('#vacancy-2 .lv-badge-new')
            ->assertNotPresent('#vacancy-3 .lv-badge-new')
            ->assertNotPresent('#vacancy-12 .lv-badge-new');
    });

    test('в теге виден текст b2b & b2c (баг №14)', function () {
        visit(site('/vacancies/'))
            ->assertSeeIn('.lv-tags', 'b2b & b2c')
            ->assertSourceHas('href="/vacancies/?q=b2b+%26+b2c"');
    });

    test('избранное в списке: класс lv-fav-on, текст ★ не меняется (баг №15)', function () {
        visit(site('/vacancies/'))
            ->click('#vacancy-1 .lv-fav')
            ->wait(0.4)
            ->assertSourceHas('lv-fav-on')
            ->assertSeeIn('#vacancy-1 .lv-fav', '★');
    });
});

describe('Вакансии — детальная страница', function () {
    test('открытие по CODE', function () {
        visit(site('/vacancies/?CODE=senior-php-bitrix'))
            ->assertSee('Senior PHP-разработчик (Битрикс)')
            ->assertPresent('.lv-detail')
            ->assertPresent('.lv-lead')
            ->assertPresent('.lv-body')
            ->assertPresent('#respond')
            ->assertSeeIn('.lv-breadcrumbs', 'Все вакансии')
            ->assertSeeIn('.lv-breadcrumbs', 'Разработка');
    });

    test('title содержит зарплату в скобках (баг №17)', function () {
        visit(site('/vacancies/?CODE=senior-php-bitrix'))
            ->assertSourceHas('<title>Senior PHP-разработчик (Битрикс) (от 280 000 до 350 000 ₽)</title>');
    });

    test('открытие по ID', function () {
        visit(site('/vacancies/?ID=1'))
            ->assertSeeIn('.lv-title', 'Senior PHP-разработчик (Битрикс)');
    });

    test('строчные code/id игнорируются (баг №4)', function () {
        visit(site('/vacancies/?code=senior-php-bitrix'))
            ->assertSee('Вакансии (14)')
            ->assertPresent('.lv-list')
            ->assertNotPresent('.lv-detail');

        visit(site('/vacancies/?id=1'))
            ->assertSee('Вакансии (14)')
            ->assertPresent('.lv-list');
    });

    test('при CODE и ID приоритет у ID (баг №5)', function () {
        visit(site('/vacancies/?CODE=frontend-vue&ID=1'))
            ->assertSeeIn('.lv-title', 'Senior PHP-разработчик (Битрикс)')
            ->assertDontSeeIn('.lv-title', 'Frontend-разработчик (Vue 3)');
    });

    test('ЧПУ отдаёт пустой HTML при 200 (баг №6)', function () {
        $html = visit(site('/vacancies/senior-php-bitrix/'))->content();

        expect($html)->toBe('<html><head></head><body></body></html>');
    });

    test('несуществующая и закрытая вакансии — 404', function () {
        visit(site('/vacancies/?CODE=nonexistent'))
            ->assertSourceHas('<title>Вакансия не найдена или уже закрыта</title>')
            ->assertSeeIn('.lv-empty h1', 'Вакансия не найдена')
            ->assertSee('Вакансия не найдена или уже закрыта')
            ->assertSee('Ко всем вакансиям')
            ->assertNotPresent('.lv-side');

        visit(site('/vacancies/?CODE=closed-php-2024'))
            ->assertSeeIn('.lv-empty h1', 'Вакансия не найдена');

        visit(site('/vacancies/?ID=15'))
            ->assertSeeIn('.lv-empty h1', 'Вакансия не найдена');

        visit(site('/vacancies/?ID=999'))
            ->assertSeeIn('.lv-empty h1', 'Вакансия не найдена');
    });

    test('пустые CODE/ID откатываются к списку', function () {
        visit(site('/vacancies/?CODE='))
            ->assertSee('Вакансии (14)')
            ->assertPresent('.lv-list');

        visit(site('/vacancies/?ID='))
            ->assertSee('Вакансии (14)')
            ->assertPresent('.lv-list');
    });

    test('tech-lead без верхней границы зарплаты', function () {
        visit(site('/vacancies/?CODE=tech-lead'))
            ->assertSeeIn('.lv-meta .lv-salary', 'от 350 000 ₽')
            ->assertSourceHas('<title>Технический лидер направления Bitrix (от 350 000 ₽)</title>')
            ->assertDontSeeIn('.lv-meta .lv-salary', 'до');
    });

    test('рассинхрон счётчиков откликов у senior-php (баг №2)', function () {
        visit(site('/vacancies/?CODE=senior-php-bitrix'))
            ->assertSeeIn('.lv-stats', 'Откликов:')
            ->assertSeeIn('.lv-stats', '2')
            ->assertSeeIn('.lv-stats', 'отклика')
            ->assertSeeIn('.lv-stats-week', '3 за неделю');
    });

    test('похожие для Поддержки добираются из Разработки (баг №3)', function () {
        visit(site('/vacancies/?CODE=support-l1'))
            ->assertSourceHas('Похожие вакансии')
            ->assertSourceHas('?CODE=support-l2">Инженер поддержки (2-я линия, Битрикс)')
            ->assertSourceHas('?CODE=support-lead">Руководитель поддержки')
            ->assertSourceHas('?CODE=middle-php-developer">Middle PHP-разработчик');
    });

    test('популярные на детальной исключают текущую', function () {
        visit(site('/vacancies/?CODE=senior-php-bitrix'))
            ->assertSourceHas('<h3>Популярные</h3>')
            ->assertSourceHas('?CODE=frontend-vue">Frontend-разработчик (Vue 3)')
            ->assertSourceHas('?CODE=devops-engineer">DevOps-инженер')
            ->assertSourceHas('?CODE=sales-manager-b2b">Менеджер по продажам B2B')
            ->assertSourceHas('?CODE=support-l2">Инженер поддержки (2-я линия, Битрикс)')
            ->assertSourceMissing('?CODE=senior-php-bitrix">Senior PHP-разработчик');
    });

    test('сайдбар детальной без «Направления» и «Сводка» (баг №16)', function () {
        visit(site('/vacancies/?CODE=senior-php-bitrix'))
            ->assertDontSeeIn('.lv-side', 'Направления')
            ->assertDontSeeIn('.lv-side', 'За неделю:')
            ->assertSeeIn('.lv-side', 'Похожие вакансии')
            ->assertSeeIn('.lv-side', 'Популярные');
    });

    test('экранирование в лиде account-manager (баг №14)', function () {
        visit(site('/vacancies/?CODE=account-manager'))
            ->assertSee('<20')
            ->assertSourceHas('&lt;20')
            ->assertSourceHas('"клиент → задача → отчёт"');
    });

    test('каждый просмотр увеличивает счётчик (баг №13)', function () {
        $page = visit(site('/vacancies/?CODE=sales-intern'));
        $before = (int) $page->text('.lv-stats > span:nth-child(1) b');

        $page->navigate(site('/vacancies/?CODE=sales-intern'));
        $after = (int) $page->text('.lv-stats > span:nth-child(1) b');

        expect($after)->toBe($before + 1);
    });

    test('избранное на детальной меняет текст кнопки (баг №15)', function () {
        visit(site('/vacancies/?CODE=junior-php'))
            ->assertSeeIn('.lv-stats .lv-fav', '☆ В избранное')
            ->click('.lv-stats .lv-fav')
            ->wait(0.4)
            ->assertSeeIn('.lv-stats .lv-fav', '★ В избранном')
            ->click('.lv-stats .lv-fav')
            ->wait(0.4)
            ->assertSeeIn('.lv-stats .lv-fav', '☆ В избранное');
    });
});

describe('Вакансии — форма отклика', function () {
    test('пустая форма: тексты ошибок валидации', function () {
        visit(site('/vacancies/?CODE=middle-php-developer'))
            ->press('Отправить отклик')
            ->assertSee('Укажите имя')
            ->assertSee('Некорректный e-mail')
            ->assertSee('Напишите пару слов о себе');
    });

    test('message короче 10 символов отклоняется (баг №10)', function () {
        visit(site('/vacancies/?CODE=qa-engineer'))
            ->type('name', 'Иван')
            ->type('email', 'ivan@example.com')
            ->type('message', 'коротко')
            ->press('Отправить отклик')
            ->assertSee('Напишите пару слов о себе');
    });

    test('невалидный e-mail без точки в домене', function () {
        visit(site('/vacancies/?CODE=qa-engineer'))
            ->type('name', 'Иван')
            ->type('email', 'test@domain')
            ->type('message', 'Достаточно длинное сообщение')
            ->press('Отправить отклик')
            ->assertSee('Некорректный e-mail');
    });

    test('невалидный телефон', function () {
        visit(site('/vacancies/?CODE=qa-engineer'))
            ->type('name', 'Иван')
            ->type('email', 'ivan@example.com')
            ->type('phone', 'abc')
            ->type('message', 'Достаточно длинное сообщение')
            ->press('Отправить отклик')
            ->assertSee('Некорректный телефон');
    });

    test('значения сохраняются при ошибках', function () {
        visit(site('/vacancies/?CODE=qa-engineer'))
            ->type('name', 'Пётр')
            ->type('email', 'bad')
            ->type('message', 'Достаточно длинное сообщение')
            ->press('Отправить отклик')
            ->assertSee('Некорректный e-mail')
            ->assertSourceHas('value="Пётр"')
            ->assertSourceHas('value="bad"');
    });

    test('успешная отправка: редирект sent=Y и плашка', function () {
        visit(site('/vacancies/?CODE=presale-engineer'))
            ->type('name', 'Иван Тестов')
            ->type('email', 'ivan@example.com')
            ->type('message', 'Достаточно длинное сообщение для отклика')
            ->press('Отправить отклик')
            ->assertQueryStringHas('sent', 'Y')
            ->assertSee('Спасибо! Отклик отправлен, мы свяжемся с вами.')
            ->assertPresent('.lv-form-ok')
            ->assertNotPresent('form.lv-form-body');
    });

    test('прямой GET с sent=Y показывает успех без отправки (баг №12)', function () {
        visit(site('/vacancies/?CODE=support-lead&sent=Y'))
            ->assertSee('Спасибо! Отклик отправлен, мы свяжемся с вами.')
            ->assertPresent('.lv-form-ok')
            ->assertNotPresent('form.lv-form-body');
    });

    test('пустой sessid не мешает отправке (баг №11)', function () {
        $page = visit(site('/vacancies/?CODE=sales-intern'));
        $page->script('document.querySelector("input[name=sessid]").value = "";');
        $page->type('name', 'Без CSRF')
            ->type('email', 'nocsrf@example.com')
            ->type('message', 'Достаточно длинное сообщение для отклика')
            ->press('Отправить отклик')
            ->assertSee('Спасибо! Отклик отправлен, мы свяжемся с вами.');
    });
});

describe('Вакансии — AJAX избранное', function () {
    test('добавление и удаление избранного', function () {
        $page = visit(site('/vacancies/ajax.php?action=favorite&id=2'));
        $first = $page->content();
        expect($first)->toContain('"success":true');

        $page->navigate(site('/vacancies/ajax.php?action=favorite&id=2'));
        $second = $page->content();
        expect($second)->toContain('"success":true');

        preg_match('/"favorite":(true|false)/', $first, $m1);
        preg_match('/"favorite":(true|false)/', $second, $m2);
        expect($m1[1])->not->toBe($m2[1]);
    });

    test('ошибка без id и с нечисловым id', function () {
        visit(site('/vacancies/ajax.php?action=favorite'))
            ->assertSee('{"success":false,"error":"Не указана вакансия"}');

        visit(site('/vacancies/ajax.php?action=favorite&id=invalid'))
            ->assertSee('{"success":false,"error":"Не указана вакансия"}');
    });

    test('несуществующая и неактивная вакансия', function () {
        visit(site('/vacancies/ajax.php?action=favorite&id=999'))
            ->assertSee('{"success":false,"error":"Вакансия не найдена"}');

        visit(site('/vacancies/ajax.php?action=favorite&id=15'))
            ->assertSee('{"success":false,"error":"Вакансия не найдена"}');
    });

    test('неизвестное действие', function () {
        visit(site('/vacancies/ajax.php?action=unknown'))
            ->assertSee('{"success":false,"error":"Неизвестное действие"}');

        visit(site('/vacancies/ajax.php'))
            ->assertSee('{"success":false,"error":"Неизвестное действие"}');
    });

    test('fav=Y после клика ★ показывает избранную вакансию', function () {
        visit(site('/vacancies/'))
            ->click('#vacancy-1 .lv-fav')
            ->wait(0.4)
            ->navigate(site('/vacancies/?fav=Y'))
            ->assertSee('Вакансии (1)')
            ->assertSee('Senior PHP-разработчик (Битрикс)')
            ->assertCount('.lv-items .lv-item', 1);
    });
});
