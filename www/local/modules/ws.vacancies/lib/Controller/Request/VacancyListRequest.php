<?php

declare(strict_types=1);

namespace Ws\Vacancies\Controller\Request;

use Bitrix\Main\Request;

/**
 * HTTP-вход фильтрации и пагинации списка вакансий.
 */
final readonly class VacancyListRequest
{
	public function __construct(
		public int $section = 0,
		public int $city = 0,
		public int $exp = 0,
		public int $salary = 0,
		public bool $hot = false,
		public bool $fav = false,
		public string $q = '',
		public string $sort = '',
		public int $page = 1,
	) {
	}

	public static function createFromRequest(Request $request): self
	{
		$page = (int)($request->get('page') ?? 1);
		if ($page <= 0)
		{
			$page = 1;
		}

		return new self(
			section: (int)($request->get('section') ?? 0),
			city: (int)($request->get('city') ?? 0),
			exp: (int)($request->get('exp') ?? 0),
			salary: (int)($request->get('salary') ?? 0),
			hot: (string)($request->get('hot') ?? '') === 'Y',
			fav: (string)($request->get('fav') ?? '') === 'Y',
			q: trim((string)($request->get('q') ?? '')),
			sort: trim((string)($request->get('sort') ?? '')),
			page: $page,
		);
	}
}
