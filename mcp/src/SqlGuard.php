<?php

declare(strict_types=1);

namespace Workshop\Mcp;

use Mcp\Exception\ToolCallException;

/**
 * Первый слой защиты sql_select: пропускает только один читающий запрос.
 *
 * Регулярки легко обойти, поэтому слой не единственный: запрос ещё выполняется
 * в транзакции READ ONLY. На общем стенде или проде — отдельный пользователь БД с правами только на SELECT.
 */
final class SqlGuard
{
	/** SHOW и DESCRIBE ничего не меняют, их проверяем только на «;» */
	private const META = '/^(SHOW|DESCRIBE|DESC)\b/i';

	private const SELECT = '/^(SELECT|EXPLAIN)\b/i';

	private const FORBIDDEN = [
		'/\b(INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|CREATE|TRUNCATE|RENAME|GRANT|REVOKE|LOAD|HANDLER|CALL|LOCK|UNLOCK|SET)\b/i',
		'/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
		'/\bFOR\s+UPDATE\b/i',
		'/\b(SLEEP|BENCHMARK|GET_LOCK)\s*\(/i',
	];

	public static function assertReadOnly(string $sql): string
	{
		$sql = rtrim(trim($sql), "; \t\n\r");

		if ($sql === '')
		{
			throw new ToolCallException('Пустой запрос.');
		}

		if (str_contains($sql, ';'))
		{
			throw new ToolCallException('Только один запрос, без «;» внутри.');
		}

		if (preg_match(self::META, $sql))
		{
			return $sql;
		}

		if (!preg_match(self::SELECT, $sql))
		{
			throw new ToolCallException('Разрешены только SELECT, SHOW, DESCRIBE и EXPLAIN.');
		}

		foreach (self::FORBIDDEN as $pattern)
		{
			if (preg_match($pattern, $sql, $match))
			{
				throw new ToolCallException("Запрещённая конструкция «{$match[0]}»: инструмент только читает.");
			}
		}

		return $sql;
	}
}
