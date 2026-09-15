<?php

declare(strict_types=1);

namespace Ws\Vacancies\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;

class VacancyStatTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'legacy_vacancy_stat';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('VACANCY_ID'))
				->configurePrimary(),
			(new IntegerField('VIEWS'))
				->configureDefaultValue(0),
			(new DatetimeField('LAST_VIEW'))
				->configureNullable(),
		];
	}
}
