<?php

declare(strict_types=1);

namespace Ws\Faq\Dto;

use Bitrix\Main\Type\DateTime;

final readonly class QuestionDto
{
	public function __construct(
		public int $id,
		public ?int $categoryId,
		public string $question,
		public string $answer,
		public int $sort,
		public int $usefulCount,
		public int $notUsefulCount,
		public ?string $categoryName = null,
		public bool $isActive = true,
		public ?string $updatedAt = null,
		public ?string $createdAt = null,
	) {
	}

	public static function fromRow(array $row): self
	{
		$categoryId = $row['CATEGORY_ID'] ?? null;

		return new self(
			id: (int)$row['ID'],
			categoryId: $categoryId !== null && $categoryId !== '' ? (int)$categoryId : null,
			question: (string)$row['QUESTION'],
			answer: (string)$row['ANSWER'],
			sort: (int)($row['SORT'] ?? 500),
			usefulCount: (int)($row['USEFUL_COUNT'] ?? 0),
			notUsefulCount: (int)($row['NOT_USEFUL_COUNT'] ?? 0),
			categoryName: isset($row['CATEGORY_NAME']) && $row['CATEGORY_NAME'] !== ''
				? (string)$row['CATEGORY_NAME']
				: null,
			isActive: self::toBool($row['IS_ACTIVE'] ?? true),
			updatedAt: self::toDateString($row['UPDATED_AT'] ?? null),
			createdAt: self::toDateString($row['CREATED_AT'] ?? null),
		);
	}

	private static function toBool(mixed $value): bool
	{
		return $value === true || $value === 'Y' || $value === 1 || $value === '1';
	}

	private static function toDateString(mixed $value): ?string
	{
		if ($value instanceof DateTime)
		{
			return $value->toString();
		}

		if ($value === null || $value === '')
		{
			return null;
		}

		return (string)$value;
	}
}
