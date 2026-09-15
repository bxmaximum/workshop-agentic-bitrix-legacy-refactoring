<?php

declare(strict_types=1);

namespace Ws\Faq\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class CategoryTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'ws_faq_category';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete(),
			(new StringField('CODE'))
				->configureNullable()
				->configureSize(50)
				->addValidator(new LengthValidator(null, 50)),
			(new StringField('NAME'))
				->configureRequired()
				->configureSize(255)
				->addValidator(new LengthValidator(1, 255)),
			(new IntegerField('SORT'))
				->configureRequired()
				->configureDefaultValue(500),
			(new BooleanField('IS_ACTIVE'))
				->configureRequired()
				->configureValues('N', 'Y')
				->configureDefaultValue(true),
			(new DatetimeField('CREATED_AT'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
		];
	}
}
