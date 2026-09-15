<?php

declare(strict_types=1);

namespace Ws\Faq\Validation\Rule;

use Attribute;
use Bitrix\Main\Localization\LocalizableMessageInterface;
use Bitrix\Main\Validation\Rule\AbstractPropertyValidationAttribute;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\Validator\ValidatorInterface;

/**
 * Проверяет, что значение задано (в т.ч. допускает false / 0).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class NotNull extends AbstractPropertyValidationAttribute
{
	public function __construct(
		protected string|LocalizableMessageInterface|null $errorMessage = null,
	) {
	}

	protected function getValidators(): array
	{
		return [
			new class implements ValidatorInterface {
				public function validate(mixed $value): ValidationResult
				{
					$result = new ValidationResult();
					if ($value === null)
					{
						$result->addError(new ValidationError(
							'Значение обязательно',
							'NOT_NULL',
							failedValidator: $this,
						));
					}

					return $result;
				}
			},
		];
	}
}
