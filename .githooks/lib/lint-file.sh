#!/usr/bin/env bash
# php -l по PHP-файлам. Общий скрипт для хука Cursor (afterFileEdit) и git pre-commit.
#
#   lint-file.sh a.php b.php     — файлы из рабочей копии
#   lint-file.sh --staged a.php  — версия из индекса (то, что реально уйдёт в коммит)
#   lint-file.sh                 — без аргументов: JSON хука Cursor на stdin, берём file_path
#
# Не-PHP файлы и удалённые файлы пропускаются. Выход 1, если есть синтаксические ошибки.
set -uo pipefail

# shellcheck source=php-bin.sh
source "$(dirname "${BASH_SOURCE[0]}")/php-bin.sh"
staged=0
if [[ "${1:-}" == "--staged" ]]; then
    staged=1
    shift
fi

files=("$@")
if [[ ${#files[@]} -eq 0 && ! -t 0 ]]; then
    file=$("$PHP_BIN" -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["file_path"] ?? "";')
    [[ -n "$file" ]] && files=("$file")
fi

failed=0
for f in "${files[@]}"; do
    [[ "$f" == *.php ]] || continue
    if [[ $staged -eq 1 ]]; then
        out=$(git show ":$f" | "$PHP_BIN" -l 2>&1) || { echo "$f: ${out//Standard input code/$f}" >&2; failed=1; }
    else
        [[ -f "$f" ]] || continue
        out=$("$PHP_BIN" -l "$f" 2>&1) || { echo "$out" >&2; failed=1; }
    fi
done

exit $failed
