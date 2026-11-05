#!/usr/bin/env bash
# JobClick на платформе sitegen: установка в папку сайта и обновление.
#
#   Первая установка (папка с заглушкой, база уже создана):
#     sudo bash sitegen.sh install /var/www/sites/575
#   Обновление кода:
#     sudo bash /var/www/sites/575/deploy/sitegen.sh update /var/www/sites/575
#
# Параметры можно передать переменными окружения, тогда скрипт ничего не спрашивает:
#   REPO, BRANCH, PHP, APP_URL, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD
set -euo pipefail

MODE="${1:-}"
DIR="$(realpath -m "${2:-/var/www/sites/575}")"
REPO="${REPO:-git@github.com:hitechinvest/jobclick.git}"
BRANCH="${BRANCH:-claude/keen-cray-o95yih}"

say()  { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!!  %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31mОшибка: %s\033[0m\n' "$*" >&2; exit 1; }

[[ "$MODE" == "install" || "$MODE" == "update" ]] || die "укажите режим: install или update. Пример: sudo bash sitegen.sh install /var/www/sites/575"
[[ -d "$DIR" ]] || die "папка $DIR не найдена — сначала создайте сайт в sitegen"

OWNER="$(stat -c %U "$DIR")"
OWNER_HOME="$(getent passwd "$OWNER" | cut -d: -f6)"
[[ "$OWNER" != "root" ]] || warn "папка принадлежит root — PHP сайта, скорее всего, работает от другого пользователя. Проверьте владельца папки в sitegen."

# Все команды проекта выполняем от владельца папки сайта, чтобы PHP мог писать в storage/
as_owner() {
    if [[ "$(id -un)" == "$OWNER" ]]; then (cd "$DIR" && bash -c "$1"); else (cd "$DIR" && sudo -u "$OWNER" -H env PATH="$PATH" bash -c "$1"); fi
}
ask() { # ask VAR "Вопрос" [по умолчанию] [secret]
    local var="$1" prompt="$2" def="${3:-}" secret="${4:-}" val="${!1:-}"
    if [[ -z "$val" && ! -t 0 && -n "$def" ]]; then val="$def"; fi
    if [[ -z "$val" ]]; then
        [[ -t 0 ]] || die "не задано $var (запустите в терминале или передайте переменной окружения)"
        if [[ -n "$secret" ]]; then read -r -s -p "$prompt: " val; echo; else read -r -p "$prompt${def:+ [$def]}: " val; fi
        val="${val:-$def}"
    fi
    printf -v "$var" '%s' "$val"
}
set_env() { # set_env KEY VALUE
    as_owner "php deploy/env-set.php $(printf '%q' "$1") $(printf '%q' "$2")"
}

# ── PHP и расширения ─────────────────────────────────────────────
PHP="${PHP:-}"
if [[ -z "$PHP" ]]; then
    for candidate in php php8.4 php8.3 php8.2; do
        if command -v "$candidate" >/dev/null && "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'; then PHP="$(command -v "$candidate")"; break; fi
    done
fi
[[ -n "$PHP" ]] || die "нужен PHP 8.2 или новее (sudo apt install php8.3-cli php8.3-fpm)"
PHP_BIN_DIR="$(mktemp -d)"; ln -s "$PHP" "$PHP_BIN_DIR/php"; chmod 755 "$PHP_BIN_DIR"; export PATH="$PHP_BIN_DIR:$PATH"
trap 'rm -rf "$PHP_BIN_DIR"' EXIT
PHPV="$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
say "PHP $PHPV ($PHP), владелец сайта: $OWNER"

missing=()
for ext in pdo_mysql mbstring dom xml xmlreader curl zip gd intl fileinfo openssl tokenizer ctype iconv; do
    "$PHP" -m | grep -qix "$ext" || missing+=("$ext")
done
if (( ${#missing[@]} )); then
    pkgs=""; for e in "${missing[@]}"; do case "$e" in pdo_mysql) e=mysql;; dom|xmlreader) e=xml;; esac; pkgs+=" php$PHPV-$e"; done
    die "не хватает расширений PHP: ${missing[*]}. Установите: sudo apt install$pkgs && sudo systemctl restart php$PHPV-fpm"
fi

COMPOSER="composer"
if ! command -v composer >/dev/null; then
    say "Composer не найден — скачиваю composer.phar в папку проекта"
    COMPOSER="php composer.phar"
fi

# ── Код из GitHub ────────────────────────────────────────────────
GIT_SSH=""
KEY="$OWNER_HOME/.ssh/jobclick_deploy"
if [[ "$REPO" == git@* ]]; then
    if [[ ! -f "$KEY" ]]; then
        say "Создаю ключ доступа к GitHub для пользователя $OWNER"
        as_owner "mkdir -p '$OWNER_HOME/.ssh' && chmod 700 '$OWNER_HOME/.ssh' && ssh-keygen -q -t ed25519 -N '' -C 'jobclick-deploy@$(hostname)' -f '$KEY'"
    fi
    GIT_SSH="ssh -i $KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
    if ! as_owner "GIT_SSH_COMMAND='$GIT_SSH' git ls-remote --heads '$REPO' >/dev/null 2>&1"; then
        echo
        warn "GitHub не пускает сервер к репозиторию. Добавьте этот ключ:"
        echo "    GitHub → hitechinvest/jobclick → Settings → Deploy keys → Add deploy key (без права записи)"
        echo
        cat "$KEY.pub"
        echo
        die "после добавления ключа запустите эту же команду ещё раз"
    fi
fi
git_owner() { as_owner "GIT_SSH_COMMAND='$GIT_SSH' git $1"; }

if [[ "$MODE" == "install" && -d "$DIR/.git" ]]; then
    say "Проект уже загружен — продолжаю установку с текущего кода ($BRANCH)"
    git_owner "fetch -q origin '$BRANCH'"
    git_owner "reset -q --hard 'origin/$BRANCH'"
elif [[ "$MODE" == "install" ]]; then
    if [[ -n "$(ls -A "$DIR")" ]]; then
        say "Убираю заглушку sitegen в $DIR/.stub-backup"
        as_owner "mkdir -p .stub-backup && find . -mindepth 1 -maxdepth 1 ! -name .stub-backup -exec mv -t .stub-backup {} +"
    fi
    say "Загружаю код ($BRANCH)"
    git_owner "init -q"
    git_owner "remote add origin '$REPO'"
    git_owner "fetch -q --depth 50 origin '$BRANCH'"
    git_owner "checkout -q -f -B '$BRANCH' 'origin/$BRANCH'"
else
    [[ -d "$DIR/.git" ]] || die "в $DIR нет проекта — сначала режим install"
    say "Включаю режим обслуживания и обновляю код"
    as_owner "php artisan down --retry=15 >/dev/null 2>&1 || true"
    git_owner "fetch -q origin '$BRANCH'"
    git_owner "reset -q --hard 'origin/$BRANCH'"
fi

if [[ "$COMPOSER" != "composer" && ! -f "$DIR/composer.phar" ]]; then
    as_owner "php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\" && php composer-setup.php --quiet && rm composer-setup.php"
fi

# ── .env и база ──────────────────────────────────────────────────
if [[ ! -f "$DIR/.env" ]]; then
    say "Настройка .env — доступы к базе MariaDB, созданной в sitegen"
    ask APP_URL "Адрес сайта (с https://)"
    ask DB_HOST "Хост базы" "127.0.0.1"
    ask DB_PORT "Порт базы" "3306"
    ask DB_DATABASE "Имя базы"
    ask DB_USERNAME "Пользователь базы"
    ask DB_PASSWORD "Пароль базы (ввод скрыт)" "" secret
    as_owner "cp .env.example .env && chmod 640 .env"
    set_env APP_URL "${APP_URL%/}"
    set_env DB_CONNECTION mariadb
    set_env DB_HOST "$DB_HOST"
    set_env DB_PORT "$DB_PORT"
    set_env DB_DATABASE "$DB_DATABASE"
    set_env DB_USERNAME "$DB_USERNAME"
    set_env DB_PASSWORD "$DB_PASSWORD"
fi

say "Зависимости PHP"
as_owner "$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-progress"
grep -q '^APP_KEY=base64:' "$DIR/.env" || as_owner "php artisan key:generate --force"

say "Проверка подключения к базе"
as_owner "php artisan db:show >/dev/null" || die "не удалось подключиться к базе — проверьте DB_* в $DIR/.env"

say "Миграции"
as_owner "php artisan migrate --force"
if [[ "$(as_owner "php artisan tinker --execute='echo App\\Models\\Region::count();'" | tail -1)" == "0" ]]; then
    as_owner "php artisan db:seed --force"
fi

say "Файлы, админка и кэш"
as_owner "php artisan storage:link >/dev/null 2>&1 || true"
as_owner "php artisan filament:assets >/dev/null"
as_owner "chmod -R ug+rwX storage bootstrap/cache"
as_owner "php artisan optimize:clear >/dev/null && php artisan optimize >/dev/null"

if [[ "$MODE" == "install" ]] && [[ "$(as_owner "php artisan tinker --execute='echo App\\Models\\User::where(\"is_admin\", true)->count();'" | tail -1)" == "0" ]]; then
    say "Администратор для входа в /admin"
    ask ADMIN_EMAIL "E-mail администратора"
    ask ADMIN_PASSWORD "Пароль администратора (ввод скрыт)" "" secret
    as_owner "php artisan jobclick:admin '$ADMIN_EMAIL' '$ADMIN_PASSWORD'"
fi

# ── Планировщик: письма, сверка оплат, проверка вакансий ─────────
CRON_LINE="* * * * * cd $DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
if ! command -v crontab >/dev/null; then
    warn "cron не установлен (sudo apt install cron). Добавьте в cron пользователя $OWNER строку: $CRON_LINE"
elif ! crontab -u "$OWNER" -l 2>/dev/null | grep -qF "cd $DIR && "; then
    say "Добавляю планировщик в cron пользователя $OWNER"
    if [[ "$(id -u)" == "0" ]]; then
        { crontab -u "$OWNER" -l 2>/dev/null || true; echo "$CRON_LINE"; } | crontab -u "$OWNER" -
    elif [[ "$(id -un)" == "$OWNER" ]]; then
        { crontab -l 2>/dev/null || true; echo "$CRON_LINE"; } | crontab -
    else
        warn "добавьте в cron пользователя $OWNER строку: $CRON_LINE"
    fi
fi

[[ "$MODE" == "update" ]] && { as_owner "php artisan queue:restart >/dev/null && php artisan up >/dev/null"; }

# ── Куда смотрит веб-сервер ──────────────────────────────────────
say "Проверка веб-сервера"
ROOTS="$(grep -RhsE "^\s*(root|DocumentRoot)\s+\"?$DIR/?(public)?/?\"?\s*;?\s*$" /etc/nginx /etc/apache2 /etc/httpd 2>/dev/null | sed 's/^\s*//' | sort -u || true)"
if [[ -z "$ROOTS" ]]; then
    warn "не нашёл конфиг веб-сервера для $DIR. Корень сайта должен быть $DIR/public"
elif grep -qE "public/?\"?\s*;?\s*$" <<<"$ROOTS"; then
    echo "OK: корень сайта — $DIR/public"
elif grep -q "DocumentRoot" <<<"$ROOTS"; then
    echo "Apache смотрит в $DIR — запросы перенаправляет .htaccess в public/. Лучше сменить DocumentRoot на $DIR/public."
else
    warn "nginx отдаёт $DIR как корень сайта — так видны .env и исходники!"
    warn "Поменяйте в конфиге сайта:  root $DIR/public;  затем: sudo nginx -t && sudo systemctl reload nginx"
fi

say "Готово"
URL="$(grep '^APP_URL=' "$DIR/.env" | cut -d= -f2- | tr -d '"')"
echo "Сайт:    $URL"
echo "Админка: $URL/admin  →  Настройки: SMTP, ключ Вордстата, DeepSeek, Qwen, DaData, ЮKassa, реквизиты"
