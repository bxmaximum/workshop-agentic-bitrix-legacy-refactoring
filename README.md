# lesson3-copy.bitrix

Стенд воркшопа Agentic Bitrix: пользовательский код в `www/local/`, тесты в `tests/` (Pest: Unit, Integration, Feature) и `e2e/`.

## Проверки

Скрипты в `.githooks/lib/` общие для всех проверок:

- `lint-file.sh` — `php -l` по PHP-файлам;
- `unit.sh` — Unit-тесты Pest (`tests/`, ядро Битрикса не нужно).

Их вызывают:

- хуки агента Cursor (`.cursor/hooks.json`): после правки `*.php` — `php -l` по файлу, на остановке агента — Unit-тесты;
- git-хук `.githooks/pre-commit`: `php -l` по staged `*.php` и Unit-тесты;
- CI (`.github/workflows/ci.yml`): `php -l` по `www/local` и Unit-тесты. Integration, Feature и e2e требуют стенда с ядром и БД, их запускают локально.

Перед первым запуском: `cd tests && composer install`. Хуки сами берут Omut shim (`~/Library/Application Support/Omut/bin/shims/php`), иначе `php` из PATH; переопределение — `PHP_BIN`.

## git config core.hooksPath .githooks

Git-хуки лежат в репозитории, в `.githooks/`. Подключить один раз после клонирования:

```bash
git config core.hooksPath .githooks
```

После этого `git commit` прогоняет `php -l` по staged PHP-файлам и Unit-тесты; при ошибке коммит отменяется.
Обойти в крайнем случае: `git commit --no-verify`.
