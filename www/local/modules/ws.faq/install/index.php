<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Ws\Faq\Model\CategoryTable;
use Ws\Faq\Model\QuestionTable;
use Ws\Faq\Model\VoteTable;

Loc::loadMessages(__FILE__);

class ws_faq extends CModule
{
	public $MODULE_ID = 'ws.faq';
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
		$this->MODULE_NAME = (string)Loc::getMessage('WS_FAQ_MODULE_NAME');
		$this->MODULE_DESCRIPTION = (string)Loc::getMessage('WS_FAQ_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = (string)Loc::getMessage('WS_FAQ_PARTNER_NAME');
		$this->PARTNER_URI = (string)Loc::getMessage('WS_FAQ_PARTNER_URI');
	}

	public function DoInstall(): void
	{
		global $USER, $APPLICATION;

		if (!$USER->IsAdmin())
		{
			$APPLICATION->ThrowException((string)Loc::getMessage('WS_FAQ_DENIED'));

			return;
		}

		ModuleManager::registerModule($this->MODULE_ID);

		$this->InstallDB();
		$this->InstallFiles();
		$this->InstallEvents();

		$APPLICATION->IncludeAdminFile(
			(string)Loc::getMessage('WS_FAQ_INSTALL_TITLE'),
			__DIR__ . '/step.php'
		);
	}

	public function DoUninstall(): void
	{
		global $USER, $APPLICATION, $step;

		if (!$USER->IsAdmin())
		{
			$APPLICATION->ThrowException((string)Loc::getMessage('WS_FAQ_DENIED'));

			return;
		}

		$step = (int)$step;
		if ($step < 2)
		{
			$APPLICATION->IncludeAdminFile(
				(string)Loc::getMessage('WS_FAQ_UNINSTALL_TITLE'),
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
			(string)Loc::getMessage('WS_FAQ_UNINSTALL_TITLE'),
			__DIR__ . '/unstep2.php'
		);
	}

	public function InstallDB(): bool
	{
		Loader::includeModule($this->MODULE_ID);

		$connection = Application::getConnection();

		if (!$connection->isTableExists(CategoryTable::getTableName()))
		{
			CategoryTable::getEntity()->createDbTable();
		}

		if (!$connection->isTableExists(QuestionTable::getTableName()))
		{
			QuestionTable::getEntity()->createDbTable();
		}

		if (!$connection->isTableExists(VoteTable::getTableName()))
		{
			VoteTable::getEntity()->createDbTable();
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

		// Обратный порядок: голоса → вопросы → категории
		foreach ([
			VoteTable::getTableName(),
			QuestionTable::getTableName(),
			CategoryTable::getTableName(),
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
		CopyDirFiles(
			__DIR__ . '/admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
			true,
			true
		);

		CopyDirFiles(
			__DIR__ . '/components',
			$_SERVER['DOCUMENT_ROOT'] . '/local/components',
			true,
			true
		);

		return true;
	}

	public function UnInstallFiles(): bool
	{
		DeleteDirFiles(
			__DIR__ . '/admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
		);

		DeleteDirFilesEx('/local/components/ws/faq.list');

		return true;
	}
}
