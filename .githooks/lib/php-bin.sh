#!/usr/bin/env bash
# Выбор PHP для хуков: явный PHP_BIN, иначе Omut shim (php-8.4 + ini), иначе php из PATH.
# Подключать: source "$(dirname "$0")/php-bin.sh"   или   source "$lib/php-bin.sh"
if [[ -z "${PHP_BIN:-}" ]]; then
	_omut_php="${HOME}/Library/Application Support/Omut/bin/shims/php"
	if [[ -x "$_omut_php" ]]; then
		PHP_BIN="$_omut_php"
	else
		PHP_BIN=php
	fi
	unset _omut_php
fi
