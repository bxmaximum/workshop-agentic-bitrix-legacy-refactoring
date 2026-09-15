---
name: bitrix-module-architecture
description: Трёхслойная архитектура модуля 1С-Битрикс (Model → Repository → Service, тонкие входы Controller/Component/Command/Agent/EventHandler) без DDD и лишних абстракций. Используй всегда, когда создаёшь или дорабатываешь модуль в /local/modules — добавляешь таблицу, ORM-таблет, репозиторий, сервис, DTO, AJAX-контроллер, агент, консольную команду или обработчик события; когда выносишь логику из компонента или init.php в модуль; когда решаешь, куда положить класс, как назвать папку, регистрировать ли сервис в .settings.php, запускать ли make:controller/make:service/make:tablet; когда делаешь ревью модуля на нарушения слоёв. Применяй даже если пользователь не произносит слово «архитектура», а просто просит «сделай модуль» или «добавь фичу в модуль».
---

# Архитектура модуля Битрикс: три слоя и тонкие входы

Модуль состоит из трёх слоёв и любого числа входов. Поток данных всегда один:

```
вход (Controller / Component / Command / Agent / EventHandler)
  → Service      (сценарий: проверил правила, сходил в репозитории, вернул Result)
    → Repository (единственный слой, который знает про ORM)
      → Model    (ORM-таблет: описание таблицы и ничего больше)
```

Почему так, а не «чистая архитектура» с Domain/Application/Infrastructure: каждое правило ниже можно проверить `grep`'ом. «`*Table::` встречается только в `lib/Repository/`» — проверяемо. «Соблюдай слои» — нет. Правила, которые нельзя проверить, агент нарушает незаметно, и это заметят через полгода.

Проверено по ядру main 26.700: факты про генераторы и ServiceLocator ниже взяты из `bitrix/modules/main/lib/cli/` и `lib/DI/ServiceLocator.php`, а не из документации.

## Карта модуля

```
local/modules/vendor.stock/
├── install/
│   ├── index.php                 # CModule: registerModule, createDbTable(), события, агенты
│   ├── version.php
│   └── components/vendor/...     # компоненты модуля — только отображение
├── lang/ru/...
├── lib/
│   ├── Model/        SubscriptionTable.php        # слой данных
│   ├── Repository/   SubscriptionRepository.php   # слой доступа
│   ├── Service/      SubscriptionService.php      # слой сценариев
│   ├── Dto/          SubscribeDto.php, SubscriptionDto.php
│   ├── Controller/   Subscription.php             # вход: AJAX/REST
│   ├── Command/      NotifyCommand.php            # вход: CLI (если нужен)
│   ├── Agent/        NotifyAgent.php              # вход: агент (если нужен)
│   └── EventHandler/ Catalog.php                  # вход: события (если нужен)
├── .settings.php                 # controllers, services (только исключения), console
├── default_option.php
└── options.php                   # если у модуля есть настройки
```

Неймспейс `vendor.stock` → `\Vendor\Stock`. Папка = сегмент неймспейса, файл = имя класса, PSR-4 подхватывается ядром без регистрации. Никакого `include.php` с `registerAutoLoadClasses`.

Куда положить класс:

| Что это | Папка | Имя | Пример |
|---|---|---|---|
| Описание таблицы | `Model/` | `<Сущность>Table` | `SubscriptionTable` |
| Чтение/запись сущности | `Repository/` | `<Сущность>Repository` | `SubscriptionRepository` |
| Сценарий, бизнес-правило | `Service/` | `<Сущность или процесс>Service` | `SubscriptionService`, `NotifyService` |
| Структура данных между слоями | `Dto/` | `<Смысл>Dto` | `SubscribeDto` (вход), `SubscriptionDto` (выход) |
| AJAX/REST-экшены | `Controller/` | существительное без суффикса | `Subscription` |
| Консольная команда | `Command/` | `<Действие>Command` | `NotifyCommand` |
| Агент | `Agent/` | `<Действие>Agent` | `NotifyAgent` |
| Обработчики событий | `EventHandler/` | по модулю-источнику | `Catalog`, `Main` |
| Исключение (редко) | `Exception/` | `<Причина>Exception` | `StorageUnavailableException` |

Один класс — одна папка. Если класс не подходит ни под одну строку, это повод пересмотреть класс, а не завести новую папку.

## Правила слоёв

| Слой | Делает | Не делает | Как проверить |
|---|---|---|---|
| **Model** | `extends DataManager`, `getTableName()`, `getMap()` с типизированными полями, валидаторами, `Reference`. | Методы кроме ORM-описания, бизнес-логика, статические «хелперы». | В файле нет методов кроме `getTableName`, `getMap`, `getObjectClass`/`getCollectionClass`, `getUfId`. |
| **Repository** | Единственное место, где есть `*Table::`, `->query()`, `add()/update()/delete()`. Методы названы предметно: `findActive()`, `listByProduct()`, `save()`. Возвращает DTO, `?DTO`, `list<DTO>`, `AddResult`/`UpdateResult`/`Result`. | Бизнес-правила, `Context`, `$_REQUEST`, права, возврат сырых массивов ORM наружу, универсальные `getList(array $filter)`. | `grep -rl "Table::" lib | grep -v Repository/ | grep -v Model/` пуст. |
| **Service** | `final`, зависимости только через конструктор, публичные методы принимают DTO/скаляры и возвращают `Bitrix\Main\Result`. Транзакции, проверки, координация репозиториев. | HTTP-знание (`Request`, `Response`, `Context`), ORM напрямую, исключения для ожидаемых ошибок, `ServiceLocator::getInstance()` внутри. | `grep -rl "Context::\|HttpRequest\|Table::" lib/Service` пуст. |
| **Dto** | `final readonly class`, публичные свойства, `fromRow()`/`fromArray()` для маппинга. | Валидация бизнес-правил, зависимости, сеттеры. | Нет методов кроме конструктора и `from*`. |
| **Входы** | Разобрать вход → собрать DTO → вызвать сервис → отдать результат. Экшен умещается в ~15 строк. | `*Table::`, SQL, транзакции, любая логика кроме маппинга и обработки `Result`. | `grep -rl "Table::\|->query()" lib/Controller lib/Command lib/Agent lib/EventHandler install/components` пуст. |

### Решения, которые приняты заранее

Агенту не нужно выбирать — эти вопросы уже решены.

- **Ошибки — `Result` + `Error`, не исключения.** «Товар не найден», «подписка уже есть», «нет прав» — это `$result->addError(new Error('...', 'CODE'))`. Исключение — только для ошибок программиста и инфраструктуры (нет соединения, битая конфигурация). Во входе: `$this->addErrors($result->getErrors()); return null;`.
- **Интерфейс появляется только при второй реализации.** Репозиторий и сервис — конкретные `final` классы. Интерфейс заводится, когда реально есть две реализации (кука и БД, файл и S3) или нужна подмена в тесте. Не раньше.
- **Таблица создаётся из таблета:** в `install/index.php` → `SubscriptionTable::getEntity()->createDbTable()`. `install/mysql/*.sql` не используются.
- **Компоненты — только отображение.** Класс компонента получает сервис через `ServiceLocator::getInstance()->get(SubscriptionService::class)` (в компонентах конструкторного DI нет) и рендерит DTO. AJAX — через контроллер модуля, не через `Controllerable` в компоненте: у модуля один API.
- **Валидация формы (обязательность, формат)** — во входе или в конструкторе DTO. **Бизнес-валидация** (дубликаты, статусы, права) — в сервисе.
- **`declare(strict_types=1)`** в каждом PHP-файле `lib/`. PHP 8.2+: `readonly`, enum, `match`, именованные аргументы.
- **Транзакции** — в сервисе: `Application::getConnection()->startTransaction()` / `commitTransaction()` / `rollbackTransaction()`, репозиторий про них не знает.

## DI: три факта из ядра, которые меняют привычки

`Bitrix\Main\DI\ServiceLocator` (`lib/DI/ServiceLocator.php`):

1. **Незарегистрированный конкретный класс контейнер собирает сам.** `get(SubscriptionService::class)` без записи в `.settings.php` → `createObjectWithFullConstruct()` → рекурсивно разрешает типы конструктора через тот же `get()`. Регистрировать сервисы и репозитории не нужно.
2. **Регистрация `X::class => ['className' => X::class]` ломает автосборку.** Для зарегистрированного не-абстрактного ключа контейнер делает `new $class(...$constructorParams)` — без автовайринга (`createItemByServiceName`). Сервис с зависимостями в конструкторе упадёт с `ArgumentCountError`. Документация это подтверждает («создаст сервис вызвав `new $className`»), но агенты и люди регистрируют по привычке.
3. **В экшен контроллера сервис инжектится параметром метода**, но `Engine\AutoWire\Binder` берёт из контейнера только то, что проходит `class_exists()` — **интерфейс параметром экшена не разрешится**. Конструктор контроллера DI не поддерживает вообще: `ControllerBuilder` делает `newInstance($request)`.

Следствия:

- В `.settings.php → services` регистрируем **только исключения**: интерфейс → реализация, классы со скалярами в конструкторе (`constructorParams`), фабрики (`constructor`-замыкание), сторонние объекты (`LoggerInterface`).
- Интерфейс в конструкторе сервиса требует регистрации — иначе `get(Interface::class)` упадёт с `ServiceNotFoundException`.
- В экшенах и компонентах типизируем **конкретные классы** сервисов.
- Где конструкторного DI нет (контроллер, компонент, команда, агент, обработчик события) — `ServiceLocator::getInstance()->get(XService::class)` в одном месте на входе, не глубже.

```php
<?php
// .settings.php
return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Vendor\\Stock\\Controller',
        ],
        'readonly' => true,
    ],
    'services' => [
        'value' => [
            // Конкретные сервисы и репозитории здесь НЕ перечисляются — контейнер соберёт их сам.
            // Только исключения:
            \Psr\Log\LoggerInterface::class => [
                // Logger::create() вернёт null, если логгер не описан в секции loggers — подстраховываемся NullLogger
                'constructor' => static fn (): \Psr\Log\LoggerInterface
                    => \Bitrix\Main\Diag\Logger::create('vendor.stock') ?? new \Psr\Log\NullLogger(),
            ],
            // \Vendor\Stock\Service\StorageInterface::class => ['className' => \Vendor\Stock\Service\DbStorage::class],
        ],
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                // \Vendor\Stock\Command\NotifyCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
```

## Генераторы `make:*`: что запускать, а что нет

Раскладка генераторов зашита в код и **не совпадает с нашей**: `make:controller` → `lib/Infrastructure/Controller/`, `make:service` → `lib/Public/Service/`, `make:entity` → `lib/Internals/Entity/`, `make:request` → `lib/Infrastructure/Controller/Request/`, `make:agent` → `lib/Infrastructure/Agent/`, `make:tablet` → `lib/Tablet/`. Опция `-P` добавляет сегмент *перед* этим неймспейсом, `-C` — *после*; свой неймспейс есть только у `make:tablet`. Содержимое каркасов бедное (пустой класс, `?string`-свойства, контроллер без фильтров и `strict_types`), а `Renderer` **молча перезаписывает** существующий файл при повторном запуске.

| Команда | Решение | Почему |
|---|---|---|
| `make:module vendor.stock --name="..." -n` | **Да** | Каркас `install/` (index, version, lang, default_option). `.settings.php` и `lib/` создаются руками. |
| `make:tablet <table> vendor.stock --namespace 'Vendor\Stock\Model' -n` | **Да, только для существующей таблицы** | Читает схему из БД (нужен модуль `perfmon`); `--namespace` кладёт файл сразу в `Model/`. Для новой таблицы таблет пишется по шаблону ниже. |
| `make:component Vendor:stock.subscribe --module=vendor.stock` | Можно | Каркас компонента нейтрален к раскладке `lib/`. |
| `orm:annotate -m vendor.stock` | **Да, после каждого изменения `Model/`** | Аннотации читает не только IDE, но и агент. |
| `make:controller`, `make:service`, `make:entity`, `make:request`, `make:agent` | **Нет** | Чужая раскладка, каркас беднее шаблона, молчаливая перезапись. Пиши класс по шаблону. |

Если файл всё же оказался в `lib/Infrastructure/…`, `lib/Public/…`, `lib/Internals/…`, `lib/Tablet/…` — перенеси в папку слоя, поправь строку `namespace`, прогони `php -l`, убедись, что второй копии не осталось и никто не импортирует старый неймспейс (`grep -rn "Infrastructure\\\\\|Public\\\\\|Internals\\\\" lib`).

Консоль ядра запускается из корня сайта: в Omut — `php www/bitrix/bitrix.php <команда>`; в другом окружении меняется только путь до `bitrix.php`. Требует `composer install` в корне сайта (без `symfony/console` `bitrix.php` умирает).

## Порядок работы

### Новый модуль

1. `make:module vendor.stock --name="Уведомления о поступлении" -n`.
2. `.settings.php` по образцу выше. Удали пустые `install/mysql/*.sql`.
3. `lib/Model/<X>Table.php` по шаблону. Проверь `php -l`.
4. `install/index.php`: `InstallDB()` → `createDbTable()`, `UnInstallDB()` → `dropTable()`; события и агенты регистрируются тут же и снимаются при деинсталляции.
5. `lib/Dto/` — входной и выходной DTO.
6. `lib/Repository/<X>Repository.php` — только те методы, которые нужны сценариям. Не пиши CRUD «на будущее».
7. `lib/Service/<X>Service.php` — сценарии, `Result`.
8. Входы: `lib/Controller/`, при необходимости `Command/`, `Agent/`, `EventHandler/`, компонент в `install/components/`.
9. Установи модуль: админка «Marketplace → Установленные решения», либо одноразовый скрипт (ниже).
10. `orm:annotate -m vendor.stock`.
11. Самопроверка (раздел ниже), затем дымовой тест: `curl` к экшену и `SELECT` из таблицы.

Одноразовая установка из консоли (сгенерированный `DoInstall()` молча выходит, если `$USER` не админ, поэтому авторизуемся):

```php
<?php
// install-module.php в корне сайта; запуск: php install-module.php
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
$USER->Authorize(1);
$module = CModule::CreateModuleObject('vendor.stock');
$module->DoInstall();
echo \Bitrix\Main\ModuleManager::isModuleInstalled('vendor.stock') ? "OK\n" : "FAIL\n";
```

### Фича в существующем модуле

1. Найди сервис, отвечающий за сущность. Нет — создай, не раздувай соседний.
2. Новый метод сервиса → нужный метод репозитория → при необходимости поле в `Model/` (и `orm:annotate`).
3. Вход подключается последним и остаётся тонким.
4. Если модуль живёт в другой раскладке — не перекладывай его без просьбы. Новый код кладётся по **существующей** раскладке, но правила слоёв (ORM только в репозитории, `Result` из сервиса, тонкий вход) соблюдаются всё равно. Предложи миграцию отдельным пунктом в ответе.

### Ревью модуля

Прогони самопроверку, затем пройди по файлам. Формат вывода: `файл:строка — правило — что не так — как исправить`. Не переписывай код молча: сначала список нарушений, правки — по подтверждению.

## Самопроверка перед ответом

Запусти из корня сайта (подставь путь к модулю). Каждая команда должна вернуть пустой вывод.

```bash
M=www/local/modules/vendor.stock/lib

# ORM вне Repository/Model
grep -rln "Table::\|->query()" $M --include=*.php | grep -v "/Repository/\|/Model/"

# HTTP и суперглобалы вне входов
grep -rln 'Context::getCurrent\|HttpRequest\|\$_REQUEST\|\$_POST\|\$_GET' $M --include=*.php | grep -v "/Controller/\|/Command/"

# Ручное создание сервисов вместо контейнера
grep -rn "new [A-Za-z\\\\]*\(Service\|Repository\)(" $M --include=*.php

# Регистрация конкретных сервисов в .settings.php (ломает автосборку)
grep -n "Service::class => \[\s*'className'\|Repository::class => \[\s*'className'" www/local/modules/vendor.stock/.settings.php

# Файлы без strict_types
grep -rL "declare(strict_types=1)" $M --include=*.php

# Остатки чужой раскладки
find $M -type d \( -name Infrastructure -o -name Public -o -name Internals -o -name Tablet \)

php -l на каждый изменённый файл
```

Что-то вернулось — исправь до ответа, а не описывай пользователю как «известное ограничение».

## Шаблоны

Сквозной пример — модуль `vendor.stock`, «сообщить о поступлении товара». Подставляй свои имена, структуру не меняй.

### Model

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

class SubscriptionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'vendor_stock_subscription';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('PRODUCT_ID'))->configureRequired(),
            (new IntegerField('USER_ID'))->configureNullable(),
            (new StringField('EMAIL'))->configureRequired()->configureSize(255)
                ->addValidator(new LengthValidator(null, 255)),
            (new DatetimeField('CREATED_AT'))->configureRequired()
                ->configureDefaultValue(static fn (): DateTime => new DateTime()),
            (new DatetimeField('NOTIFIED_AT'))->configureNullable(),
        ];
    }
}
```

Таблет не `final`: ядро оборачивает `*Table` в сгенерированные классы объектов и коллекций. Имя таблицы — `<vendor>_<module>_<сущность>`.

### Dto

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Dto;

final readonly class SubscribeDto
{
    public function __construct(
        public int $productId,
        public string $email,
        public ?int $userId = null,
    ) {}
}
```

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Dto;

use Bitrix\Main\Type\DateTime;

final readonly class SubscriptionDto
{
    public function __construct(
        public int $id,
        public int $productId,
        public ?int $userId,
        public string $email,
        public DateTime $createdAt,
        public ?DateTime $notifiedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['ID'],
            productId: (int)$row['PRODUCT_ID'],
            userId: $row['USER_ID'] !== null ? (int)$row['USER_ID'] : null,
            email: (string)$row['EMAIL'],
            createdAt: $row['CREATED_AT'],
            notifiedAt: $row['NOTIFIED_AT'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'email' => $this->email,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'notifiedAt' => $this->notifiedAt?->format(DATE_ATOM),
        ];
    }
}
```

### Repository

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Repository;

use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Type\DateTime;
use Vendor\Stock\Dto\SubscriptionDto;
use Vendor\Stock\Model\SubscriptionTable;

final class SubscriptionRepository
{
    public function findActive(int $productId, string $email): ?SubscriptionDto
    {
        $row = SubscriptionTable::query()
            ->setSelect(['*'])
            ->where('PRODUCT_ID', $productId)
            ->where('EMAIL', $email)
            ->whereNull('NOTIFIED_AT')
            ->setLimit(1)
            ->fetch();

        return $row ? SubscriptionDto::fromRow($row) : null;
    }

    /** @return list<SubscriptionDto> */
    public function listActiveByProduct(int $productId): array
    {
        $rows = SubscriptionTable::query()
            ->setSelect(['*'])
            ->where('PRODUCT_ID', $productId)
            ->whereNull('NOTIFIED_AT')
            ->setOrder(['ID' => 'ASC'])
            ->fetchAll();

        return array_map(SubscriptionDto::fromRow(...), $rows);
    }

    public function add(int $productId, ?int $userId, string $email): AddResult
    {
        return SubscriptionTable::add([
            'PRODUCT_ID' => $productId,
            'USER_ID' => $userId,
            'EMAIL' => $email,
        ]);
    }

    public function markNotified(int $id): UpdateResult
    {
        return SubscriptionTable::update($id, ['NOTIFIED_AT' => new DateTime()]);
    }
}
```

Метод репозитория отвечает на вопрос сценария (`findActive`, `listActiveByProduct`), а не повторяет ORM (`getList($filter, $select)`). Если сценарию нужен новый вопрос — добавляется метод, а не параметр-массив.

### Service

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Service;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Vendor\Stock\Dto\SubscribeDto;
use Vendor\Stock\Repository\SubscriptionRepository;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function subscribe(SubscribeDto $dto): Result
    {
        $result = new Result();

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            return $result->addError(new Error('Некорректный email', 'INVALID_EMAIL'));
        }

        if ($this->subscriptions->findActive($dto->productId, $dto->email) !== null) {
            return $result->addError(new Error('Подписка на этот товар уже оформлена', 'ALREADY_SUBSCRIBED'));
        }

        $addResult = $this->subscriptions->add($dto->productId, $dto->userId, $dto->email);
        if (!$addResult->isSuccess()) {
            return $result->addErrors($addResult->getErrors());
        }

        return $result->setData(['id' => $addResult->getId()]);
    }
}
```

Зависимости — только через конструктор, и только конкретные классы (или зарегистрированные интерфейсы). Ни `ServiceLocator::getInstance()`, ни `new SubscriptionRepository()` внутри сервиса.

### Controller

```php
<?php declare(strict_types=1);

namespace Vendor\Stock\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Vendor\Stock\Dto\SubscribeDto;
use Vendor\Stock\Service\SubscriptionService;

final class Subscription extends Controller
{
    public function configureActions(): array
    {
        return [
            'subscribe' => [
                '-prefilters' => [ActionFilter\Authentication::class],   // гость тоже может подписаться
                '+prefilters' => [new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST])],
            ],
        ];
    }

    public function subscribeAction(int $productId, string $email, SubscriptionService $service): ?array
    {
        $dto = new SubscribeDto(
            productId: $productId,
            email: trim($email),
            userId: $this->getCurrentUser()?->getId(),
        );

        $result = $service->subscribe($dto);
        if (!$result->isSuccess()) {
            $this->addErrors($result->getErrors());

            return null;
        }

        return $result->getData();
    }
}
```

Фильтры по умолчанию у `Engine\Controller` — `Authentication`, `HttpMethod(GET, POST)`, `Csrf`; убирай только осознанно через `-prefilters`. Сервис приходит параметром экшена — его резолвит `Binder` через `ServiceLocator`. Вызов: `POST /bitrix/services/main/ajax.php?action=vendor:stock.Subscription.subscribe` с `sessid`.

### Вход без DI (компонент, команда, агент, обработчик)

```php
$service = \Bitrix\Main\DI\ServiceLocator::getInstance()->get(\Vendor\Stock\Service\SubscriptionService::class);
```

Одна строка на входе. Перед ней — `Loader::includeModule('vendor.stock')`, если модуль ещё не подключён (в контроллере и в коде самого модуля не нужно).

### install/index.php — часть с БД

```php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Vendor\Stock\Model\SubscriptionTable;

public function InstallDB()
{
    Loader::includeModule($this->MODULE_ID);
    $connection = Application::getConnection();
    if (!$connection->isTableExists(SubscriptionTable::getTableName())) {
        SubscriptionTable::getEntity()->createDbTable();
    }
}

public function UnInstallDB()
{
    Loader::includeModule($this->MODULE_ID);
    $connection = Application::getConnection();
    if ($connection->isTableExists(SubscriptionTable::getTableName())) {
        $connection->dropTable(SubscriptionTable::getTableName());
    }
}
```

`registerModule()` вызывается до `InstallDB()` (так в каркасе от `make:module`), иначе `Loader::includeModule` вернёт `false` и классы модуля не найдутся. Обработчики событий регистрируются в `InstallEvents()` через `EventManager::getInstance()->registerEventHandler(...)` и снимаются в `UnInstallEvents()`.

## Антипаттерны — узнавай и не повторяй

| Было | Стало |
|---|---|
| `SubscriptionTable::getList([...])` в компоненте или контроллере | Метод репозитория, вызванный из сервиса. |
| `throw new \Exception('Подписка уже есть')` в сервисе | `$result->addError(new Error('...', 'ALREADY_SUBSCRIBED'))`. |
| `interface SubscriptionRepositoryInterface` с одной реализацией | `final class SubscriptionRepository`. Интерфейс — при второй реализации. |
| `SubscriptionService::class => ['className' => SubscriptionService::class]` в `.settings.php` | Ничего: контейнер соберёт сам. Регистрация только для интерфейсов, скаляров, фабрик. |
| `public function subscribeAction(int $productId, StorageInterface $storage)` | Конкретный класс в параметре экшена; интерфейс `Binder` не разрешит. |
| `new SubscriptionRepository()` внутри сервиса | Зависимость в конструкторе. |
| `Context::getCurrent()->getRequest()` в сервисе | Значения приходят в DTO из входа. |
| `lib/Infrastructure/Controller/Subscription.php` от `make:controller` | `lib/Controller/Subscription.php`, написанный по шаблону. |
| `install/mysql/install.sql` с `CREATE TABLE` | `createDbTable()` из таблета. |
| Компонент с `Controllerable` и своими AJAX-экшенами | Компонент рендерит, AJAX — в `lib/Controller/`. |
| `getList(array $filter, array $select = [])` в репозитории | `findActive()`, `listActiveByProduct()` — метод на вопрос сценария. |
