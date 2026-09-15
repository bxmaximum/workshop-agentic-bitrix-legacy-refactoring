<?php

declare(strict_types=1);

namespace Ws\Faq\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;

class QuestionTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'ws_faq_question';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete(),
			(new IntegerField('CATEGORY_ID'))
				->configureNullable(),
			(new BooleanField('IS_ACTIVE'))
				->configureRequired()
				->configureValues('N', 'Y')
				->configureDefaultValue(true),
			(new IntegerField('SORT'))
				->configureRequired()
				->configureDefaultValue(500),
			(new StringField('QUESTION'))
				->configureRequired()
				->configureSize(500)
				->addValidator(new LengthValidator(1, 500)),
			(new TextField('ANSWER'))
				->configureRequired(),
			(new IntegerField('USEFUL_COUNT'))
				->configureRequired()
				->configureDefaultValue(0),
			(new IntegerField('NOT_USEFUL_COUNT'))
				->configureRequired()
				->configureDefaultValue(0),
			(new DatetimeField('CREATED_AT'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
			(new DatetimeField('UPDATED_AT'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
			(new Reference(
				'CATEGORY',
				CategoryTable::class,
				Join::on('this.CATEGORY_ID', 'ref.ID')
			))->configureJoinType(Join::TYPE_LEFT),
		];
	}
}
