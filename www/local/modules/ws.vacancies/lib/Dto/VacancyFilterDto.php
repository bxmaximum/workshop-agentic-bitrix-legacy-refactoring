<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

use Bitrix\Main\HttpRequest;

final readonly class VacancyFilterDto
{
	public function __construct(
		public int $section = 0,
		public int $city = 0,
		public int $exp = 0,
		public int $salary = 0,
		public bool $hot = false,
		public bool $fav = false,
		public string $q = '',
		public string $sort = 'date',
		public int $page = 1,
		public int $pageSize = 5,
		/** @var list<int> */
		public array $favoriteIds = [],
	) {
	}

	public static function fromRequest(HttpRequest $request, int $pageSize, string $defaultSort): self
	{
		$sort = trim((string)($request->get('sort') ?? ''));
		if (!in_array($sort, ['date', 'salary', 'views', 'name'], true))
		{
			$sort = $defaultSort;
		}

		$page = (int)($request->get('page') ?? 1);
		if ($page <= 0)
		{
			$page = 1;
		}

		$q = trim(strip_tags((string)($request->get('q') ?? '')));
		$q = preg_replace('/\s+/u', ' ', $q) ?? $q;
		if (mb_strlen($q) > 100)
		{
			$q = mb_substr($q, 0, 100);
		}

		return new self(
			section: (int)($request->get('section') ?? 0),
			city: (int)($request->get('city') ?? 0),
			exp: (int)($request->get('exp') ?? 0),
			salary: (int)($request->get('salary') ?? 0),
			hot: (string)($request->get('hot') ?? '') === 'Y',
			fav: (string)($request->get('fav') ?? '') === 'Y',
			q: $q,
			sort: $sort,
			page: $page,
			pageSize: $pageSize > 0 ? min($pageSize, 50) : 5,
		);
	}

	public function withFavoriteIds(array $favoriteIds): self
	{
		return new self(
			section: $this->section,
			city: $this->city,
			exp: $this->exp,
			salary: $this->salary,
			hot: $this->hot,
			fav: $this->fav,
			q: $this->q,
			sort: $this->sort,
			page: $this->page,
			pageSize: $this->pageSize,
			favoriteIds: array_values(array_map(static fn($id): int => (int)$id, $favoriteIds)),
		);
	}
}
