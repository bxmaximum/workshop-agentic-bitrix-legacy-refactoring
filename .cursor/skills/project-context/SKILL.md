---
name: project-context
description: Предметка и команды стенда lesson3-copy — раздел «Вакансии» (модуль ws.vacancies, инфоблок VACANCIES, таблицы откликов и просмотров), эталонные данные seed.php, реестр багов, тесты по сьютам, MCP bitrix-stand. Используй в каждой стадии /flow и когда нужно понять, где что лежит и чем проверять.
---

# Стенд lesson3-copy

Учебный стенд воркшопа Agentic Bitrix. Пользовательский код — только `www/local/`, ядро `www/bitrix/` не трогаем. Каноны кода — `AGENTS.md`.

## Предметка: раздел «Вакансии» (`/vacancies/`)

| Что | Где |
|-----|-----|
| Модуль | `www/local/modules/ws.vacancies/lib/`: Controller → Service → Repository → Model (ORM), Dto, Presenter |
| Публичный компонент | `www/local/components/legacy/vacancies/` — тонкий, вся логика в сервисах модуля |
| Вакансии | инфоблок `VACANCIES` (тип `legacy`, API_CODE `Vacancy`), 16 элементов, 14 активных, 3 раздела |
| Просмотры | таблица `legacy_vacancy_stat` (`VACANCY_ID`, `VIEWS`, `LAST_VIEW`), ORM `VacancyStatTable` |
| Отклики | таблица `legacy_vacancy_response`, ORM `VacancyResponseTable`, репозиторий `VacancyResponseRepository` |
| Статусы откликов | `NEW`, `VIEWED`, `SPAM`. **SPAM не входит ни в один счётчик** — ни в общий, ни в недельный, ни в сводку сайдбара |
| FAQ | модуль `ws.faq`, опция `show_counters` |

## Эталонные данные

`docs/legacy/seed.php` сбрасывает инфоблок, просмотры и отклики к эталону: 12 откликов, из них 1 SPAM (у `senior-php-bitrix`, 1 день назад). Даты откликов считаются от текущего момента, поэтому «за неделю» стабильно.

Integration, Feature и e2e перед прогоном сами вызывают seed. Если руками меняли данные — перед сверкой через MCP запустите seed.

## Реестр багов

`docs/bugfix_plan.md` — баги №1–18 раздела, целевые решения и матрица обновления e2e-тестов (§3). Баг №1 закрыт. Ожидаемые числа в реестре сверяйте с данными стенда: реестр составлен в занятии 3 и местами расходится с кодом и базой.

## Команды

PHP для тестов с ядром — Omut php-8.4 с ini (иначе нет сокета MySQL):

```bash
export PATH="$HOME/Library/Application Support/Omut/bin/php-8.4:$PATH"
export PHPRC="$HOME/Library/Application Support/Omut/configs/php/php-8.4-mysql-8.4.ini"

cd tests && composer test:unit          # без ядра, секунды
cd tests && composer test:integration   # ядро + БД, перезаливает seed
cd tests && SITE_URL=http://lesson3-copy.bitrix:8765 composer test:feature
php docs/legacy/seed.php                # сброс данных вручную
```

e2e (браузер, перезаливает seed) — через Omut shim, как в `AGENTS.md`:

```bash
export PATH="$HOME/Library/Application Support/Omut/bin/shims:$PATH"
cd e2e && ./vendor/bin/pest
```

Линт одного файла: `.githooks/lib/lint-file.sh <file>`.

## Git

- База для задач — ветка, с которой запущен `/flow`. Ветки задач `<type>/<slug>`.
- `git config core.hooksPath .githooks` — `pre-commit` гоняет `php -l` и Unit.
- CI (`.github/workflows/ci.yml`) — только lint и Unit: ядра и БД в CI нет.

## MCP `bitrix-stand`

Сервер в `mcp/`, подключён в `.cursor/mcp.json`. Только чтение.

| Тул | Когда |
|-----|-------|
| `sql_select` | посчитать строки, проверить гипотезу, узнать фактическое число до и после правки |
| `iblock_elements` | ID и поля вакансии по коду, свойства инфоблока |
| `module_options` | настройки модуля (`ws.faq`, `main`, `iblock`) |
| ресурс `bitrix://modules` | установленные модули и версии; читается через `fetch_mcp_resource`, а не через SQL по `b_module` |

Пример: `SELECT STATUS, COUNT(*) CNT FROM legacy_vacancy_response WHERE CREATED >= NOW() - INTERVAL 7 DAY GROUP BY STATUS`.

## Грабли

- e2e-тесты раздела характеризующие: часть из них фиксирует баги («баг №N» в названии). Исправили баг — обновите тест по §3 реестра, а не удаляйте.
- Integration/Feature/e2e пишут в БД: в readonly-ролях (reviewer) их не запускать.
