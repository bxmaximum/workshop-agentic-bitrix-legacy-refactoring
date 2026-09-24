# bitrix-stand — MCP-сервер стенда

Учебный MCP-сервер к занятию 5. Даёт агенту в Cursor доступ на чтение к стенду: настройки модулей, инфоблоки, SELECT-запросы к БД. Написан на официальном PHP SDK [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk), транспорт stdio, ядро Битрикса грузится из `../www`.

## Что умеет

| Имя | Тип | Что делает |
| --- | --- | --- |
| `module_options` | tool | Опции модуля из `b_option` и `default_option.php`, секреты маскируются |
| `iblock_elements` | tool | Инфоблок по CODE или ID: поля, свойства, первые элементы |
| `sql_select` | tool | Один SELECT / SHOW / DESCRIBE / EXPLAIN, до 50 строк, транзакция READ ONLY, таймаут 5 с |
| `bitrix://modules` | resource | Установленные модули и версии |

## Устройство

```
mcp/
├── server.php          # точка входа: builder, discovery по атрибутам, stdio
├── src/Kernel.php      # ленивая загрузка ядра, как bootBitrix() в tests/Pest.php
├── src/StandTools.php  # тулы: #[McpTool] + докблок = описание для модели
└── src/SqlGuard.php    # первый слой защиты sql_select
```

Правила, на которых всё держится:

1. **STDOUT принадлежит протоколу.** Любой `echo` или warning в него ломает обмен. Ошибки PHP идут в STDERR (`display_errors=stderr`), вывод ядра при загрузке перехватывается буфером.
2. **Ядро грузится при первом вызове тула.** Сервер стартует и показывает список тулов, даже когда БД стенда выключена; ошибка приходит агенту текстом.
3. **Ошибки для модели — `ToolCallException`.** Модель видит текст («таблицы нет», «модуль не установлен») и сама поправляет вызов. Любое другое исключение превращается в безликое «internal error».
4. **Чтение защищено в три слоя:** проверка запроса → `START TRANSACTION READ ONLY` → на общем стенде отдельный пользователь БД с правами только на SELECT. Регулярки обходятся, поэтому первый слой не единственный. DDL (`DROP`, `ALTER`) делает неявный COMMIT и READ ONLY не защищает, его режет только проверка.

## Установка

```bash
cd mcp && composer install
```

Подключение — `.cursor/mcp.json` в корне репозитория. Путь к PHP и `PHPRC` те же, что для Integration-тестов (см. `AGENTS.md`): без ini Omut PHP не видит сокет MySQL.

После правок кода сервера — выключить и включить `bitrix-stand` в Cursor → Customize → MCP. Процесс долгоживущий: опции модулей ядро кеширует в памяти процесса, после смены настроек в админке сервер тоже нужно перезапустить.

## Проверка без Cursor

MCP Inspector показывает тулы и позволяет вызвать их руками:

```bash
npx @modelcontextprotocol/inspector \
  "$HOME/Library/Application Support/Omut/bin/php-8.4/php" mcp/server.php
```

Переменную `PHPRC` задать в окне Inspector (Environment Variables) или экспортировать перед запуском.

Логи сервера в Cursor: Output (Cmd+Shift+U) → MCP Logs.

## Известные грабли

- Если `php docs/legacy/seed.php` не работает, MCP-сервер тоже не поднимет ядро: сначала чините окружение.
- В процессе два composer-автолоадера: `mcp/vendor` и корневой `vendor/` (его подключает `init.php`). Версии общих пакетов (psr/*, symfony/*) должны совпадать, иначе класс загрузится не из того vendor.
- SDK до версии 1.0 помечен experimental: API между минорными версиями меняется, версия зафиксирована в `composer.json`.
