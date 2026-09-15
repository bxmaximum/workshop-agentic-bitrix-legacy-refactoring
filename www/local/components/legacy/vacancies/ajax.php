<?
/**
 * AJAX-обработчик раздела вакансий.
 * Подключается из /vacancies/ajax.php (там prolog_before). Раньше дёргался напрямую
 * по /local/components/legacy/vacancies/ajax.php, после переезда на nginx — через страницу.
 *
 * action=favorite&id=N   — переключить избранное (GET, без sessid — "так работало и в приложении")
 * action=favorites       — список ID избранного
 * action=respond         — отклик через AJAX (POST), дублирует логику формы из component.php
 * action=views&id=N      — просмотры вакансии
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

global $APPLICATION, $USER, $DB;

require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/legacy_helpers.php");

$APPLICATION->RestartBuffer();
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$action = isset($_REQUEST["action"]) ? trim((string)$_REQUEST["action"]) : "";
$result = array("success" => false);

switch ($action) {

	case "favorite":
		$id = isset($_REQUEST["id"]) ? intval($_REQUEST["id"]) : 0;
		if ($id <= 0) {
			$result["error"] = "Не указана вакансия";
			break;
		}
		// проверяем, что вакансия есть и активна
		$exists = false;
		if (CModule::IncludeModule("iblock")) {
			$rs = CIBlockElement::GetList(
				array(),
				array("IBLOCK_ID" => legacy_get_iblock_id(), "ID" => $id, "ACTIVE" => "Y"),
				false,
				false,
				array("ID")
			);
			if ($rs->Fetch()) {
				$exists = true;
			}
		}
		if (!$exists) {
			$result["error"] = "Вакансия не найдена";
			break;
		}
		$result["success"] = true;
		$result["favorite"] = legacy_toggle_favorite($id);
		$result["count"] = count(legacy_get_favorites());
		break;

	case "favorites":
		$result["success"] = true;
		$result["items"] = legacy_get_favorites();
		break;

	case "views":
		$id = isset($_REQUEST["id"]) ? intval($_REQUEST["id"]) : 0;
		if ($id <= 0) {
			$result["error"] = "Не указана вакансия";
			break;
		}
		$result["success"] = true;
		$result["views"] = legacy_get_views($id);
		$result["responses"] = legacy_get_response_count($id);
		break;

	case "respond":
		if ($_SERVER["REQUEST_METHOD"] != "POST") {
			$result["error"] = "Только POST";
			break;
		}
		if (!check_bitrix_sessid()) {
			$result["error"] = "Сессия устарела, обновите страницу";
			break;
		}
		if (!CModule::IncludeModule("iblock")) {
			$result["error"] = "Модуль инфоблоков не установлен";
			break;
		}

		$vacancyId = isset($_POST["vacancy_id"]) ? intval($_POST["vacancy_id"]) : 0;
		$name = isset($_POST["name"]) ? legacy_clean($_POST["name"], 100) : "";
		$email = isset($_POST["email"]) ? legacy_clean($_POST["email"], 100) : "";
		$phone = isset($_POST["phone"]) ? legacy_clean($_POST["phone"], 30) : "";
		$message = isset($_POST["message"]) ? legacy_clean_text($_POST["message"], 2000) : "";

		$arErrors = array();
		if (strlen($name) < 2) {
			$arErrors["name"] = "Укажите имя";
		}
		if (!legacy_check_email($email)) {
			$arErrors["email"] = "Некорректный e-mail";
		}
		if (strlen($phone) > 0 && !legacy_check_phone($phone)) {
			$arErrors["phone"] = "Некорректный телефон";
		}
		if (mb_strlen($message) < 10) {
			$arErrors["message"] = "Напишите пару слов о себе";
		}
		if (isset($_SESSION["LEGACY_LAST_RESPONSE"]) && (time() - intval($_SESSION["LEGACY_LAST_RESPONSE"])) < 60) {
			$arErrors["timeout"] = "Вы уже отправляли отклик, подождите минуту";
		}

		$arVacancy = false;
		if ($vacancyId > 0) {
			$rs = CIBlockElement::GetList(
				array(),
				array("IBLOCK_ID" => legacy_get_iblock_id(), "ID" => $vacancyId, "ACTIVE" => "Y", "ACTIVE_DATE" => "Y"),
				false,
				false,
				array("ID", "NAME", "CODE")
			);
			$arVacancy = $rs->GetNext();
		}
		if (!$arVacancy) {
			$arErrors["vacancy"] = "Вакансия не найдена или закрыта";
		}

		if (!empty($arErrors)) {
			$result["errors"] = $arErrors;
			$result["error"] = implode("; ", $arErrors);
			break;
		}

		$ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
		$userId = $USER->IsAuthorized() ? intval($USER->GetID()) : 0;

		$sql = "INSERT INTO " . LEGACY_RESPONSE_TABLE . " (VACANCY_ID, USER_ID, NAME, EMAIL, PHONE, MESSAGE, IP, STATUS, CREATED) VALUES ("
			. intval($arVacancy["ID"]) . ", "
			. $userId . ", "
			. "'" . $DB->ForSql($name) . "', "
			. "'" . $DB->ForSql($email) . "', "
			. "'" . $DB->ForSql($phone) . "', "
			. "'" . $DB->ForSql($message) . "', "
			. "'" . $DB->ForSql($ip, 45) . "', "
			. "'NEW', "
			. $DB->GetNowFunction()
			. ")";
		$rsInsert = $DB->Query($sql, true, "File: " . __FILE__ . "<br>Line: " . __LINE__);
		if (!$rsInsert) {
			$result["error"] = "Не удалось сохранить отклик";
			legacy_log("ajax response insert failed", array("vacancy" => $vacancyId, "email" => $email));
			break;
		}

		$_SESSION["LEGACY_LAST_RESPONSE"] = time();

		CEvent::Send("LEGACY_VACANCY_RESPONSE", SITE_ID, array(
			"VACANCY_ID" => $arVacancy["ID"],
			"VACANCY_NAME" => $arVacancy["~NAME"],
			"VACANCY_URL" => "http://" . $_SERVER["HTTP_HOST"] . legacy_vacancy_url($arVacancy),
			"NAME" => $name,
			"EMAIL" => $email,
			"PHONE" => $phone,
			"MESSAGE" => $message,
			"EMAIL_TO" => "",
			"RESPONSE_ID" => intval($DB->LastID()),
		));

		$result["success"] = true;
		$result["message"] = "Спасибо! Отклик отправлен.";
		break;

	default:
		$result["error"] = "Неизвестное действие";
		break;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
die();
