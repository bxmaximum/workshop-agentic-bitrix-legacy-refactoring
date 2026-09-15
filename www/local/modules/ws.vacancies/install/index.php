<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Ws\Vacancies\Model\VacancyResponseTable;
use Ws\Vacancies\Model\VacancyStatTable;

Loc::loadMessages(__FILE__);

class ws_vacancies extends CModule
{
	public $MODULE_ID = 'ws.vacancies';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $PARTNER_NAME;
	public $PARTNER_URI;
	public $MODULE_GROUP_RIGHTS = 'N';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';

		$this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '';
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '';
		$this->MODULE_NAME = (string)Loc::getMessage('WS_VACANCIES_MODULE_NAME');
		$this->MODULE_DESCRIPTION = (string)Loc::getMessage('WS_VACANCIES_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = (string)Loc::getMessage('WS_VACANCIES_PARTNER_NAME');
		$this->PARTNER_URI = (string)Loc::getMessage('WS_VACANCIES_PARTNER_URI');
	}

	public function DoInstall(): void
	{
		global $USER, $APPLICATION;

		if (!$USER->IsAdmin())
		{
			$APPLICATION->ThrowException((string)Loc::getMessage('WS_VACANCIES_DENIED'));

			return;
		}

		ModuleManager::registerModule($this->MODULE_ID);

		$this->InstallDB();
		$this->InstallFiles();
		$this->InstallEvents();

		$APPLICATION->IncludeAdminFile(
			(string)Loc::getMessage('WS_VACANCIES_INSTALL_TITLE'),
			__DIR__ . '/step.php'
		);
	}

	public function DoUninstall(): void
	{
		global $USER, $APPLICATION, $step;

		if (!$USER->IsAdmin())
		{
			$APPLICATION->ThrowException((string)Loc::getMessage('WS_VACANCIES_DENIED'));

			return;
		}

		$step = (int)$step;
		if ($step < 2)
		{
			$APPLICATION->IncludeAdminFile(
				(string)Loc::getMessage('WS_VACANCIES_UNINSTALL_TITLE'),
				__DIR__ . '/unstep1.php'
			);

			return;
		}

		$this->UnInstallEvents();
		$this->UnInstallFiles();
		$this->UnInstallDB([
			'savedata' => ($_REQUEST['savedata'] ?? 'N') === 'Y' ? 'Y' : 'N',
		]);
		ModuleManager::unRegisterModule($this->MODULE_ID);

		$APPLICATION->IncludeAdminFile(
			(string)Loc::getMessage('WS_VACANCIES_UNINSTALL_TITLE'),
			__DIR__ . '/unstep2.php'
		);
	}

	public function InstallDB(): bool
	{
		Loader::includeModule($this->MODULE_ID);

		$connection = Application::getConnection();

		// Таблицы уже могут существовать (созданы seed.php) — создаём только при отсутствии.
		if (!$connection->isTableExists(VacancyStatTable::getTableName()))
		{
			VacancyStatTable::getEntity()->createDbTable();
		}

		if (!$connection->isTableExists(VacancyResponseTable::getTableName()))
		{
			VacancyResponseTable::getEntity()->createDbTable();
		}

		return true;
	}

	public function UnInstallDB(array $arParams = []): bool
	{
		Loader::includeModule($this->MODULE_ID);

		if (($arParams['savedata'] ?? 'N') === 'Y')
		{
			return true;
		}

		$connection = Application::getConnection();

		foreach ([
			VacancyResponseTable::getTableName(),
			VacancyStatTable::getTableName(),
		] as $tableName)
		{
			if ($connection->isTableExists($tableName))
			{
				$connection->dropTable($tableName);
			}
		}

		return true;
	}

	public function InstallEvents(): bool
	{
		return true;
	}

	public function UnInstallEvents(): bool
	{
		return true;
	}

	public function InstallFiles(): bool
	{
		return true;
	}

	public function UnInstallFiles(): bool
	{
		return true;
	}
}
