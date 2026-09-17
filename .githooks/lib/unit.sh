#!/usr/bin/env bash
# Unit-тесты Pest (ядро Битрикса не нужно). Общий скрипт для хука Cursor (stop) и git pre-commit.
#
#   unit.sh           — вывод Pest в консоль, выход 1 при падении
#   unit.sh --cursor  — режим хука stop: вывод Pest в stderr, при падении в stdout JSON
#                       с followup_message, чтобы агент Cursor продолжил и починил тесты
set -uo pipefail

# shellcheck source=php-bin.sh
source "$(dirname "${BASH_SOURCE[0]}")/php-bin.sh"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root/tests" || exit 1

if [[ ! -x vendor/bin/pest ]]; then
    echo "tests/vendor не установлен: cd tests && composer install" >&2
    exit 1
fi

# --test-directory . — Pest.php лежит в корне tests/, без флага Pest ищет tests/tests/
cmd=("$PHP_BIN" vendor/bin/pest --test-directory . --testsuite=Unit --colors=never)

if [[ "${1:-}" == "--cursor" ]]; then
    if ! out=$("${cmd[@]}" 2>&1); then
        echo "$out" >&2
        OUT="$out" "$PHP_BIN" -r 'echo json_encode(["followup_message" => "Unit-тесты упали (cd tests && ./vendor/bin/pest --test-directory . --testsuite=Unit). Почини:\n\n" . substr(getenv("OUT"), -4000)], JSON_UNESCAPED_UNICODE);'
        exit 0
    fi
    echo '{}'
    exit 0
fi

"${cmd[@]}"
