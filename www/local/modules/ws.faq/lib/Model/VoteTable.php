<?php

declare(strict_types=1);

namespace Ws\Faq\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;

class VoteTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'ws_faq_vote';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete(),
			(new IntegerField('QUESTION_ID'))
				->configureRequired(),
			(new IntegerField('USER_ID'))
				->configureNullable(),
			(new StringField('GUEST_HASH'))
				->configureNullable()
				->configureSize(64)
				->addValidator(new LengthValidator(null, 64)),
			(new BooleanField('IS_USEFUL'))
				->configureRequired()
				->configureValues('N', 'Y'),
			(new StringField('IP_ADDRESS'))
				->configureRequired()
				->configureSize(45)
				->addValidator(new LengthValidator(null, 45)),
			(new DatetimeField('CREATED_AT'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
			(new DatetimeField('UPDATED_AT'))
				->configureRequired()
				->configureDefaultValue(static fn(): DateTime => new DateTime()),
			(new Reference(
				'QUESTION',
				QuestionTable::class,
				Join::on('this.QUESTION_ID', 'ref.ID')
			))->configureJoinType(Join::TYPE_INNER),
		];
	}
}
