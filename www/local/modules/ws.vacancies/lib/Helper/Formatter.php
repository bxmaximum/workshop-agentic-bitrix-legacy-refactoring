<?php

declare(strict_types=1);

namespace Ws\Vacancies\Helper;

/**
 * Форматирование зарплат, чисел, дат и склонений — как в легаси-хелперах.
 */
final class Formatter
{
	public static function salary(int $from, int $to, string $currency = '₽'): string
	{
		if ($from <= 0 && $to <= 0)
		{
			return 'по договорённости';
		}

		if ($from > 0 && $to > 0)
		{
			if ($from === $to)
			{
				return self::number($from) . ' ' . $currency;
			}

			return 'от ' . self::number($from) . ' до ' . self::number($to) . ' ' . $currency;
		}

		if ($from > 0)
		{
			return 'от ' . self::number($from) . ' ' . $currency;
		}

		return 'до ' . self::number($to) . ' ' . $currency;
	}

	public static function number(int $n): string
	{
		return number_format($n, 0, '.', ' ');
	}

	/**
	 * Склонение: plural(5, 'отклик', 'отклика', 'откликов').
	 */
	public static function plural(int $n, string $one, string $two, string $five): string
	{
		$n = abs($n) % 100;
		$n1 = $n % 10;
		if ($n > 10 && $n < 20)
		{
			return $five;
		}
		if ($n1 > 1 && $n1 < 5)
		{
			return $two;
		}
		if ($n1 === 1)
		{
			return $one;
		}

		return $five;
	}

	/**
	 * «сегодня» / «вчера» / «N дней назад» / дата.
	 */
	public static function daysAgo(string $dateString): string
	{
		if ($dateString === '')
		{
			return '';
		}

		$ts = MakeTimeStamp($dateString);
		if ($ts <= 0)
		{
			return $dateString;
		}

		$today = mktime(0, 0, 0);
		$day = mktime(0, 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
		$diff = (int)(($today - $day) / 86400);

		if ($diff <= 0)
		{
			return 'сегодня';
		}
		if ($diff === 1)
		{
			return 'вчера';
		}
		if ($diff < 30)
		{
			return $diff . ' ' . self::plural($diff, 'день', 'дня', 'дней') . ' назад';
		}

		return date('d.m.Y', $ts);
	}

	public static function date(string $dateString): string
	{
		if ($dateString === '')
		{
			return '';
		}

		$ts = MakeTimeStamp($dateString);

		return $ts > 0 ? FormatDate('d.m.Y', $ts) : '';
	}
}
