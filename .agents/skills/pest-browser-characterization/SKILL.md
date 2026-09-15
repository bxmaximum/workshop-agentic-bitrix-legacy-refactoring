---
name: pest-browser-characterization
description: "Характеризационные браузерные тесты на Pest 5 + Playwright для существующего (легаси) раздела сайта: фиксируют текущее поведение до рефакторинга, включая известные баги. Используй, когда нужно написать, дополнить или прогнать тесты в e2e/, проверить страницу через pest --agent, или убедиться после правок, что поведение не изменилось. Не зависит от CMS: сайт — чёрный ящик по HTTP."
---

# Pest browser tests · характеризация легаси

## Что это и чего это не делает

Тесты в `e2e/` — **отдельный composer-проект**. Он не подключает ядро Битрикса, не знает про инфоблоки и `$arResult`.
Он открывает страницы в Chromium (Playwright) и проверяет то, что видит пользователь. Поэтому:

- URL всегда абсолютный: `visit(site('/vacancies/'))`. Хелпер `site()` в `tests/Pest.php` берёт базу из `SITE_URL` (по умолчанию `http://workshop.bitrix`).
  Относительный `visit('/x')` здесь **не работает** — Pest ищет Laravel-сервер.
- Тесты **характеризационные**: фиксируют, как раздел ведёт себя сейчас, а не как должен. Известный баг — тоже поведение; тест на него пишется с пометкой `(баг №N)` и проходит, пока баг на месте.
- Тест, который упал после рефакторинга, означает «поведение изменилось». Правим код. Тест меняется только по решению человека, с отдельным коммитом.

## Структура

```
e2e/
  composer.json            pestphp/pest ^5, pest-plugin-browser ^5, pest-plugin-agent ^5
  package.json             playwright
  phpunit.xml              testsuite Browser → tests/Browser
  tests/Pest.php           site(), seedSite(), таймаут браузера
  tests/Browser/*.php      тесты; один файл на раздел сайта
  tests/Browser/Screenshots/   скриншоты (в .gitignore)
```

Запуск — **из папки `e2e/`**: `./vendor/bin/pest`. Сайт должен быть поднят (Omut). Данные стенда сбрасываются `seedSite()` в `beforeAll` файла.

## Установка (один раз, из корня сайта)

```
cd e2e
composer install
npm install
npx playwright install chromium
./vendor/bin/pest                      # 0 tests — окружение живое
./vendor/bin/pest --agent='visit("http://workshop.bitrix/vacancies/")->assertSee("Вакансии");'
```

Требования: PHP 8.4+, `ext-sockets` (`php -m | grep sockets`), Node в PATH (терминал Omut).

## Как исследовать страницу, не читая код сайта

`--agent` запускает один сниппет как тест и удаляет его. Это «глаза» агента:

```
./vendor/bin/pest --agent='visit("http://workshop.bitrix/vacancies/")->screenshot(filename: "list");'
./vendor/bin/pest --agent='echo visit("http://workshop.bitrix/vacancies/")->content();'
./vendor/bin/pest --agent='echo visit("http://workshop.bitrix/vacancies/?sort=views")->text(".lv-items");'
```

Скриншоты — в `tests/Browser/Screenshots/`; их можно открыть и посмотреть. `content()` — HTML целиком, `text(selector)` — текст узла.
Сначала пройди страницы руками через `--agent`, потом пиши тесты. Не угадывай селекторы — возьми их из `content()`.

## Что фиксировать (чек-лист для раздела)

1. Каждый вход: URL и параметры (список, фильтры, сортировки, постраничка, детальная по каждому способу адресации, 404-ветка, AJAX-эндпоинты).
2. Порядок элементов там, где он есть (первый, второй, последний на странице) — через `assertSeeIn('.list .item:nth-child(N)', ...)`.
3. Количества: `assertCount(selector, N)`, тексты счётчиков.
4. Формы: ошибки валидации дословно, успешный сценарий (редирект, сообщение, побочный эффект на странице), повторная отправка.
5. Состояние сессии: избранное, троттлинг — в одной цепочке `$page->...->navigate(...)` (контекст браузера сохраняется), в новой `visit()` — чистая сессия.
6. Побочные эффекты, видимые на странице: счётчик просмотров до/после (`text()` + `expect()`), число откликов.
7. Экранирование: `assertSee('<20')` для текста, `assertSourceHas('&lt;20')` / `assertSourceHas('">b2b & b2c</a>')` для исходника. Разное экранирование в разных местах — тоже фиксируется как есть.
8. JSON-ответы: `visit(url)->assertSee('{"success":true')`.

Не фиксировать: точные значения счётчиков, которые растут от самого прогона (сравнивай относительно), относительные даты («2 дня назад»), sessid и хэши.

## API, которого хватает

`visit(url)`, `->navigate(url)`, `->assertSee/assertDontSee(text)`, `->assertSeeIn/assertDontSeeIn(selector, text)`, `->assertCount(selector, n)`,
`->assertPresent/assertNotPresent(selector)`, `->assertSourceHas/assertSourceMissing(html)`, `->assertQueryStringHas(key, value)`, `->assertPathIs()`,
`->click(textOrSelector)`, `->type(fieldName, value)`, `->select(fieldName, value)`, `->check(fieldName)`, `->press(buttonText)`,
`->text(selector)`, `->attribute(selector, name)`, `->content()`, `->screenshot(filename: '…')`, `->assertNoJavaScriptErrors()`.
Полный список — pestphp.com/docs/browser-testing. Ожидания на значениях — `expect($x)->toBe(...)`.

Группировка: `describe('раздел', function () { test('…', function () { … }); });`. Один `test` — одно поведение; название — по-русски, что именно фиксируется.

## Отладка

`./vendor/bin/pest --headed` — с окном браузера; `./vendor/bin/pest --debug` — пауза на упавшем тесте; `$page->screenshot()` в любом месте цепочки;
`./vendor/bin/pest --filter='сортировка'` — один тест. Упало по таймауту — проверь, что сайт открыт по этому URL в обычном браузере, и что URL абсолютный.

## Чего не делать

- Не подключать `prolog_before.php`, `Bitrix\Main\*`, БД: проект про поведение снаружи. Нужны данные — `seedSite()`.
- Не «улучшать» поведение в тестах: если сортировка сломана — тест фиксирует сломанную.
- Не писать тесты без `--agent`-разведки страницы: селекторы и тексты берутся из реального HTML.
- Не класть тесты в `www/`.
