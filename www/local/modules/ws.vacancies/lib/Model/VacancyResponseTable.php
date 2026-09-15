<?php

declare(strict_types=1);

namespace Ws\Vacancies\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class VacancyResponseTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'legacy_vacancy_response';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete(),
			(new IntegerField('VACANCY_ID'))
				->configureRequired(),
			(new IntegerField('USER_ID'))
				->configureDefaultValue(0),
			(new StringField('NAME'))
				->configureSize(100)
				->configureDefaultValue('')
				->addValidator(new LengthValidator(null, 100)),
			(new StringField('EMAIL'))
				->configureSize(100)
				->configureDefaultValue('')
				->addValidator(new LengthValidator(null, 100)),
			(new StringField('PHONE'))
				->configureSize(30)
				->configureDefaultValue('')
				->addValidator(new LengthValidator(null, 30)),
			(new TextField('MESSAGE'))
				->configureNullable(),
			(new StringField('IP'))
				->configureSize(45)
				->configureDefaultValue('')
				->addValidator(new LengthValidator(null, 45)),
			(new StringField('STATUS'))
				->configureSize(10)
				->configureDefaultValue('NEW')
				->addValidator(new LengthValidator(null, 10)),
			(new DatetimeField('CREATED'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
		];
	}
}
