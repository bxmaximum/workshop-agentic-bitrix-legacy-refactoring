#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * MCP-сервер стенда. Cursor запускает его подпроцессом (.cursor/mcp.json) и говорит с ним JSON-RPC через stdin/stdout.
 *
 * STDOUT занят протоколом: любой echo или warning в него ломает обмен.
 * Поэтому ошибки PHP идут в STDERR, а вывод ядра при загрузке перехватывает Kernel::boot().
 */

ini_set('display_errors', 'stderr');

require __DIR__ . '/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

$server = Server::builder()
	->setServerInfo('bitrix-stand', '0.1.0')
	->setInstructions(
		'Доступ только на чтение к стенду 1С-Битрикс: настройки модулей, инфоблоки, SELECT-запросы к БД. '
		. 'Сначала смотри структуру (iblock_elements, SHOW TABLES / DESCRIBE), потом пиши выборку.'
	)
	// Ищет классы с атрибутами #[McpTool] / #[McpResource] в src/
	->setDiscovery(__DIR__, ['src'])
	->build();

exit($server->run(new StdioTransport()));
