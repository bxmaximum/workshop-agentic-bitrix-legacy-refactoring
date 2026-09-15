# План архитектурного рефакторинга раздела «Вакансии» (/vacancies/)

> **КРИТИЧЕСКИЙ ПРИНЦИП:** Рефакторинг проводится **строго без исправления ошибок и изменения существующего поведения**. Все 18 зафиксированных аномалий и багов легаси-кода должны быть сохранены в точности («баг-в-баг»), чтобы функционал успешно проходил существующий набор браузерных характеристических тестов `e2e/tests/Browser/VacanciesTest.php`.
> Исправление найденных ошибок вынесено в отдельный документ: `docs/bugfix_plan.md`.

---

## 1. Цели и задачи рефакторинга

1. **Устранение легаси-антипаттернов:**
   - Избавление от прямого процедурного кода в `component.php` (670 строк) и `templates/.default/template.php` (350 строк).
   - Ликвидация глобальных переменных `$DB`, `$APPLICATION`, `$USER` и прямого выполнения SQL-запросов (`$DB->Query(...)`) внутри компонента и шаблона.
   - Избавление от устаревших API инфоблоков (`CIBlockElement::GetList`, `CIBlockSection::GetList`, `CIBlockPropertyEnum::GetList`) в пользу современного ядра D7.
   - Устранение дублирования кода выборки свойств, форматирования зарплат и дат между `component.php`, `result_modifier.php` и `ajax.php`.
2. **Переход на трёхслойную архитектуру Bitrix D7 (`Model → Repository → Service`):**
   - Выделение нового модуля `local/modules/ws.vacancies/` (неймспейс `\Ws\Vacancies`).
   - Слой **Model:** ORM-таблеты на базе `DataManager` для кастомных таблиц `legacy_vacancy_stat` и `legacy_vacancy_response`, а также D7 ORM-сущность инфоблока вакансий (`\Bitrix\Iblock\Elements\ElementVacancyTable`, `API_CODE=Vacancy`).
   - Слой **Repository:** единственная точка вызова ORM-таблетов и ConditionTree; возвращает типизированные DTO.
   - Слой **Service:** бизнес-сценарии, валидация, работа с избранным, инкремент просмотров, подготовка данных.
   - Слой **Входы (UI / AJAX):** тонкий ООП-компонент `class.php` (`\CBitrixComponent`) и тонкий контроллер/скрипт `ajax.php`.
3. **Строгое сохранение публичных контрактов и тестов:**
   - Сохранение параметров вызова компонента `legacy:vacancies` в `/vacancies/index.php`.
   - Сохранение структуры DOM-дерева, CSS-классов, id элементов, текстов ошибок, заголовков страниц и формата JSON-ответов `ajax.php`.

---

## 2. Матрица сохранения легаси-поведения и багов

Все зафиксированные в `docs/legacy/vacancies.md` особенности должны быть осознанно воспроизведены в новой архитектуре:

| № бага / аномалии | Зафиксированное поведение (из `vacancies.md` и тестов) | Как поведение сохраняется в рефакторинге | Слой реализации |
|---|---|---|---|
| **№1** (Сортировка `sort=views`) | Сортировка по просмотрам выполняется не в SQL, а в памяти PHP для 5 элементов текущей страницы по дате. `DevOps-инженер` (190 просмотров) оказывается на 2-й странице. | `VacancyService::listVacancies()` при `sort === 'views'` запрашивает страницу элементов у `VacancyRepository` с сортировкой по дате (`ACTIVE_FROM DESC, ID DESC`), а затем выполняет `usort` по просмотрам полученного среза. | `VacancyService` |
| **№2** (Рассинхрон откликов) | На детальной странице общий счётчик откликов исключает `SPAM`, а недельный счётчик считает абсолютно все отклики (включая `SPAM`). В сайдбаре списка в сводке также суммируются недельные записи. | `VacancyResponseRepository` реализует два разных метода: `getValidCountByVacancyId()` (`STATUS <> 'SPAM'`) и `getWeekCountIncludingSpamByVacancyId()` (без фильтра по статусу). | `VacancyResponseRepository` |
| **№3** (Подмешивание чужого раздела) | В похожих вакансиях, если в разделе вакансий < 3, недостающие добираются по совпадению `CITY_ID` из чужих разделов (для `support-l1` добирается `middle-php-developer`). | `VacancyRepository::getRelatedVacancies()` сохраняет двухэтапную логику: выборка по `SECTION_ID`, затем добор недостающих по `CITY_ID` с исключением текущей и уже выбранных. | `VacancyRepository` |
| **№4** (Регистрозависимость `CODE`/`ID`) | Запросы со строчными `?code=...` и `?id=...` игнорируются, открывается список. Работают только верхнерегистровые `CODE` и `ID`. | `VacanciesComponent` считывает строго `$request->get('ID')` и `$request->get('CODE')`, без проверки строчных ключей. | `VacanciesComponent` |
| **№5** (Приоритет `ID` над `CODE`) | При `?CODE=frontend-vue&ID=1` открывается вакансия с ID 1. | В `VacanciesComponent` проверка ID предшествует проверке CODE: `if ($id > 0) { ... } elseif ($code !== '') { ... }`. | `VacanciesComponent` |
| **№6** (ЧПУ без обработки) | По ЧПУ-адресу `/vacancies/senior-php-bitrix/` отдаётся HTTP 200 с пустым HTML `<html><head></head><body></body></html>`. | Правила URL-rewrite не создаются; обработка ЧПУ не подключается. | Конфигурация / роутинг |
| **№7** (Граничные значения пагинатора) | `page=99` отдаёт последнюю существующую страницу (стр. 3). `page <= 0` и `page=abc` сбрасываются на стр. 1. Первая страница всегда формируется без `page=1`. При `<= 5` элементов блока пагинации нет. | `PaginationHelper` / DTO пагинации инкапсулирует эти математические правила: `$page = min(max(1, $page), $totalPages)`. | `VacancyService` |
| **№8** (Фильтр зарплаты) | Фильтрация по `salary` проверяет только условие `SALARY_FROM >= $salary`. `SALARY_TO` игнорируется. Вакансия с вилкой 90к-200к при поиске 200к не находится. | В `VacancyRepository::buildFilter()` накладывается строгое условие `>=SALARY_FROM`. Поле `SALARY_TO` не задействуется. | `VacancyRepository` |
| **№9** (Поиск `q` в анонсе) | Запрос `q` ищет регистронезависимо по `NAME`, `TAGS` и `PREVIEW_TEXT` (`LOGIC => OR`). | В `VacancyRepository` формируется ConditionTree с тремя условиями `%NAME`, `%PREVIEW_TEXT`, `%TAGS`. | `VacancyRepository` |
| **№10** (Скрытый лимит длины) | В форме отклика сообщение длиной < 10 символов возвращает ошибку «Напишите пару слов о себе». | В `VacancyResponseValidator` сохраняется валидация `mb_strlen($message) < 10` с сообщением `GetMessage('LV_FORM_ERR_MESSAGE')`. | `VacancyResponseService` |
| **№11** (Игнорирование CSRF) | Поле `sessid` рендерится в форме, но на бэкенде не проверяется (пустой `sessid` успешно сохраняет отклик). В AJAX проверка CSRF также отсутствует. | Сервисы обработки отклика и избранного не вызывают `check_bitrix_sessid()`. | `VacancyResponseService`, `FavoriteService` |
| **№12** (Фейковый статус `sent=Y`) | Прямой GET с `?sent=Y` всегда показывает плашку успеха и скрывает форму. | Компонент проверяет `$request->get('sent') === 'Y'` и проставляет флаг успешной отправки в `$arResult['FORM']['SENT']`. | `VacanciesComponent` |
| **№13** (Накрутка просмотров) | Любой GET-запрос детальной страницы безусловно инкрементирует счётчик просмотров на 1. | `VacancyService::getDetail()` вызывает инкремент просмотров при каждом GET-запросе без проверки сессии. | `VacancyService` |
| **№14** (Особенности экранирования) | В `account-manager` знак `<` экранирован (`&lt;20`), кавычки сырые. В теге `b2b & b2c` амперсанд экранирован в HTML, но сырой `&` в URL разрывает параметры. | Вывод анонса: `~PREVIEW_TEXT` без дополнительного экранирования; вывод тегов: `htmlspecialcharsbx($tag)` и `urlencode($tag)` в `href`. | `VacanciesComponent`, Шаблон |
| **№15** (Разное поведение кнопки избранного) | В списке карточек текст кнопки всегда `★` (переключается только CSS-класс `lv-fav-on`). На детальной текст меняется `☆ В избранное` ↔ `★ В избранном`. | JS-скрипт сохраняет проверку `link.textContent.trim() !== "★"`. | `script.js` |
| **№16** (Разный состав сайдбара) | На списке: Направления, Популярные, Сводка. На детальной: Похожие, Популярные (без текущей!). На 404: сайдбар отсутствует. | `SidebarService` формирует разные наборы данных в зависимости от режима (`list` vs `detail`). Шаблон рендерит только переданные блоки. | `SidebarService`, Шаблон |
| **№17** (Метатеги: title и description) | Детальная: `<title>` = `#NAME# (от X до Y ₽)`. Список: `<meta name="description">` = `Вакансии: ` + первые 5 названий текущей страницы через запятую. | Логика генерации title и description выносится в компонент и сохраняет идентичные строковые шаблоны. | `VacanciesComponent` |
| **№18** (Бейдж «Новая» `<= 2 дней`) | Бейдж выводится строго для вакансий с возрастом публикации `<= 2 дней`. | Формула проверки новизны: `(time() - MakeTimeStamp($activeFrom)) < 3 * 86400` (что строго даёт `<= 2 дней`). | `VacancyService` / `VacancyDto` |

---

## 3. Целевая структура файлов

Рефакторинг распределяет код по канонам `AGENTS.md` и `bitrix-module-architecture`:

```
local/
├── modules/ws.vacancies/                      # Новый модуль ядра
│   ├── .settings.php                         # controllers, services
│   ├── install/
│   │   ├── index.php                         # Регистрация модуля
│   │   └── version.php
│   ├── lib/
│   │   ├── Model/
│   │   │   ├── VacancyStatTable.php          # ORM-таблет таблицы legacy_vacancy_stat
│   │   │   └── VacancyResponseTable.php      # ORM-таблет таблицы legacy_vacancy_response
│   │   ├── Dto/
│   │   │   ├── VacancyDto.php                # Неизменяемый DTO вакансии
│   │   │   ├── VacancyFilterDto.php          # DTO параметров фильтрации и сортировки
│   │   │   ├── VacancyListDto.php            # DTO страницы списка (элементы, пагинация)
│   │   │   ├── VacancyResponseInputDto.php   # DTO входящих данных формы отклика
│   │   │   ├── SectionDto.php                # DTO раздела инфоблока
│   │   │   └── SidebarDto.php                # DTO блоков сайдбара
│   │   ├── Repository/
│   │   │   ├── VacancyRepository.php         # Доступ к инфоблоку VACANCIES через D7
│   │   │   ├── VacancyStatRepository.php     # Доступ к legacy_vacancy_stat
│   │   │   └── VacancyResponseRepository.php # Доступ к legacy_vacancy_response
│   │   ├── Service/
│   │   │   ├── VacancyService.php            # Сценарии списка, детальной, похожих
│   │   │   ├── FavoriteService.php           # Сценарий работы с сессионным избранным
│   │   │   ├── VacancyResponseService.php    # Валидация, сохранение отклика, CEvent::Send
│   │   │   └── SidebarService.php            # Сборка данных для сайдбара
│   │   ├── Controller/
│   │   │   ├── Favorite.php                  # Modern AJAX-контроллер избранного
│   │   │   ├── Vacancy.php                   # Modern контроллер вакансий и откликов
│   │   │   └── Request/                      # HTTP-входы контроллеров (валидация + автовайринг)
│   │   │       ├── ToggleFavoriteRequest.php # Request переключения избранного с атрибутами валидации
│   │   │       ├── VacancyResponseRequest.php# Request формы отклика с атрибутами валидации
│   │   │       └── VacancyListRequest.php    # Request фильтрации и пагинации списка
│   │   └── Validation/Rule/                  # Кастомные правила валидации (если требуются)
│   └── lang/ru/...
├── components/legacy/vacancies/               # Тонкий компонент отображения
│   ├── class.php                             # CBitrixComponent (заменяет component.php)
│   ├── ajax.php                              # Тонкий фасад над FavoriteService
│   ├── .description.php
│   ├── .parameters.php
│   └── templates/.default/
│       ├── template.php                      # Чистый HTML без SQL-запросов
│       ├── style.css                         # Стили без изменений
│       ├── script.js                         # Логика избранного
│       └── lang/ru/template.php
└── php_interface/include/legacy_helpers.php   # Фасад обратной совместимости (proxy к сервисам)
```

---

## 4. Пошаговый сценарий реализации

### Шаг 1. Инициализация модуля `ws.vacancies`

1. Создать структуру каталогов модуля:
   - `local/modules/ws.vacancies/install/`
   - `local/modules/ws.vacancies/lib/Model/`
   - `local/modules/ws.vacancies/lib/Repository/`
   - `local/modules/ws.vacancies/lib/Service/`
   - `local/modules/ws.vacancies/lib/Dto/`
2. Создать `version.php` с версией `1.0.0`.
3. Создать `install/index.php`:
   - Наследник `CModule`, класс `ws_vacancies`.
   - В `DoInstall()` вызывается `ModuleManager::registerModule('ws.vacancies')`.
   - Таблицы `legacy_vacancy_stat` и `legacy_vacancy_response` уже существуют в БД стенда (созданы `seed.php`), поэтому в `InstallDB()` их повторное создание оборачивается в проверку наличия.
4. Зарегистрировать модуль в системе через Bitrix API (`ModuleManager::registerModule('ws.vacancies')`).
5. Создать `local/modules/ws.vacancies/.settings.php`:
   - Настроить контроллеры: `'defaultNamespace' => '\\Ws\\Vacancies\\Controller'`.

---

### Шаг 2. Слой данных (Model) — D7 ORM-таблеты

1. **`lib/Model/VacancyStatTable.php`:**
   - Наследник `Bitrix\Main\ORM\Data\DataManager`.
   - Таблица: `legacy_vacancy_stat`.
   - Поля:
     - `IntegerField('VACANCY_ID', ['primary' => true])`
     - `IntegerField('VIEWS', ['default' => 0])`
     - `DatetimeField('LAST_VIEW')`
2. **`lib/Model/VacancyResponseTable.php`:**
   - Наследник `Bitrix\Main\ORM\Data\DataManager`.
   - Таблица: `legacy_vacancy_response`.
   - Поля:
     - `IntegerField('ID', ['primary' => true, 'autocomplete' => true])`
     - `IntegerField('VACANCY_ID', ['required' => true])`
     - `IntegerField('USER_ID', ['default' => 0])`
     - `StringField('NAME', ['size' => 100])`
     - `StringField('EMAIL', ['size' => 100])`
     - `StringField('PHONE', ['size' => 30])`
     - `TextField('MESSAGE')`
     - `StringField('IP', ['size' => 45])`
     - `StringField('STATUS', ['size' => 10, 'default' => 'NEW'])`
     - `DatetimeField('CREATED')`
3. **ORM инфоблока вакансий:**
   - В инфоблоке `VACANCIES` задан `API_CODE=Vacancy` (версия инфоблока 2).
   - Для доступа к элементам инфоблока используется сгенерированный D7-класс `\Bitrix\Iblock\Elements\ElementVacancyTable` либо динамический таблет через `IblockTable::compileEntity('Vacancy')`.

---

### Шаг 3. Слой структур данных (DTO)

Создать строго типизированные `final readonly` классы без бизнес-логики:

1. **`lib/Dto/VacancyDto.php`:**
   - Поля: `id`, `name`, `code`, `sectionId`, `sectionName`, `sectionUrl`, `previewText`, `previewTextType`, `detailText`, `detailTextType`, `salaryFrom`, `salaryTo`, `salaryText`, `city`, `cityId`, `experience`, `experienceId`, `isHot`, `isNew`, `tags` (array), `dateFormatted`, `dateText`, `views`, `responseCount`, `isFavorite`, `url`.
   - Метод `fromRow(array $fields, array $properties, array $extra): self`.
2. **`lib/Dto/VacancyFilterDto.php`:**
   - Поля: `section` (int), `city` (int), `exp` (int), `salary` (int), `hot` (bool), `fav` (bool), `q` (string), `sort` (string), `page` (int), `pageSize` (int).
   - Фабричный метод `fromRequest(HttpRequest $request, int $pageSize, string $defaultSort): self`.
3. **`lib/Dto/VacancyListDto.php`:**
   - Поля: `items` (list<VacancyDto>), `totalCount` (int), `totalPages` (int), `currentPage` (int), `pageSize` (int).
4. **`lib/Dto/VacancyResponseInputDto.php`:**
   - Поля: `vacancyId` (int), `name` (string), `email` (string), `phone` (string), `message` (string), `ip` (string), `userId` (int).
5. **`lib/Dto/SidebarDto.php`:**
   - Поля: `sections` (list<SectionDto>), `popular` (list<VacancyDto>), `weekSummary` (?array), `related` (list<VacancyDto>).

---

### Шаг 3a. Слой HTTP-входа контроллеров (Controller Request с валидацией для автовайринга)

> **ВАЖНОЕ АРХИТЕКТУРНОЕ ПРАВИЛО:** Входящие данные для экшенов контроллера (`\Bitrix\Main\Engine\Controller`) оформляются **не как универсальные DTO**, а как специализированные классы **Controller Request** в пространстве имён `Ws\Vacancies\Controller\Request\*`.
> Они снабжаются PHP 8-атрибутами валидации (`#[PositiveNumber]`, `#[NotNull]`, `#[Email]`, `#[Length]`), фабричным методом `createFromRequest(Request $request): self` и связываются с движком контроллеров через `ValidationParameter` для автоматической валидации и внедрения (autowiring) в параметры методов `*Action()`.

Создать классы в `local/modules/ws.vacancies/lib/Controller/Request/`:

1. **`ToggleFavoriteRequest.php`:**
   ```php
   final readonly class ToggleFavoriteRequest
   {
       public function __construct(
           #[PositiveNumber(errorMessage: 'Не указана вакансия')]
           public ?int $id = null,
       ) {}

       public static function createFromRequest(Request $request): self
       {
           $raw = $request->get('id');
           return new self(id: ($raw !== null && $raw !== '' && is_numeric($raw)) ? (int)$raw : null);
       }
   }
   ```
2. **`VacancyResponseRequest.php`:**
   ```php
   final readonly class VacancyResponseRequest
   {
       public function __construct(
           #[PositiveNumber(errorMessage: 'Не указана вакансия')]
           public ?int $vacancyId = null,

           #[Length(min: 2, max: 100, errorMessage: 'Укажите имя')]
           public ?string $name = null,

           #[Email(errorMessage: 'Некорректный e-mail')]
           public ?string $email = null,

           public ?string $phone = null,

           #[Length(min: 10, max: 2000, errorMessage: 'Напишите пару слов о себе')]
           public ?string $message = null,
       ) {}

       public static function createFromRequest(Request $request): self
       {
           return new self(
               vacancyId: (int)($request->get('vacancy_id') ?? 0) ?: null,
               name: ($v = $request->get('name')) !== null ? trim((string)$v) : null,
               email: ($v = $request->get('email')) !== null ? trim((string)$v) : null,
               phone: ($v = $request->get('phone')) !== null ? trim((string)$v) : null,
               message: ($v = $request->get('message')) !== null ? trim((string)$v) : null,
           );
       }
   }
   ```
3. **`VacancyListRequest.php`:**
   - Поля: `$section`, `$city`, `$exp`, `$salary`, `$hot`, `$fav`, `$q`, `$sort`, `$page`.
   - Фабричный метод `createFromRequest(Request $request): self`.

#### Механизм автовайринга в контроллере (`Controller::getAutoWiredParameters`):
Контроллеры модуля переопределяют метод `getAutoWiredParameters()`, связывая класс запроса с фабрикой через `ValidationParameter`:
```php
public function getAutoWiredParameters(): array
{
    return [
        new ValidationParameter(
            ToggleFavoriteRequest::class,
            fn(): ToggleFavoriteRequest => ToggleFavoriteRequest::createFromRequest($this->getRequest()),
        ),
        new ValidationParameter(
            VacancyResponseRequest::class,
            fn(): VacancyResponseRequest => VacancyResponseRequest::createFromRequest($this->getRequest()),
        ),
    ];
}
```
Благодаря этому экшен контроллера принимает валидированный типизированный объект в аргументах:
```php
public function toggleAction(ToggleFavoriteRequest $request, FavoriteService $favoriteService): ?array
```
Если входные данные нарушают правила атрибутов, ядро Битрикса автоматически прерывает выполнение экшена и возвращает структурированный ответ с ошибками валидации.

#### Разделение обязанностей между Request и Service DTO:
- **`Controller\Request\*`**: отвечает за считывание сырых HTTP-параметров и первичную валидацию типов/форматов на границе системы.
- **Маппинг в контроллере**: экшен преобразует проверенный `Request` во внутренний `Dto` (например, `VacancyResponseInputDto`).
- **`Dto\*`**: независимые от HTTP чистые структуры данных для межслойного обмена (`Service` ↔ `Repository`).
- **Бизнес-валидация**: проверки бизнес-правил (наличие вакансии в БД, активность, таймауты между откликами, спам) выполняются в сервисах и возвращают `Bitrix\Main\Result`.

---

### Шаг 4. Слой доступа к данным (Repository)

Все обращения к БД инкапсулируются строго в `lib/Repository/`:

1. **`lib/Repository/VacancyStatRepository.php`:**
   - `getViews(int $vacancyId): int`: чтение поля `VIEWS` из `VacancyStatTable`.
   - `getViewsMap(array $vacancyIds): array<int, int>`: пакетная выборка просмотров для устранения проблемы N+1 запросов.
   - `incrementViews(int $vacancyId): void`: атомарный инкремент просмотров (`ON DUPLICATE KEY UPDATE` через `Application::getConnection()` или ORM).
   - `getTopPopularIds(int $limit): array<int, int>`: топ по просмотрам (`ORDER BY VIEWS DESC, VACANCY_ID ASC LIMIT $limit`).
2. **`lib/Repository/VacancyResponseRepository.php`:**
   - `create(VacancyResponseInputDto $dto): Result`: добавление записи в `VacancyResponseTable`.
   - `getValidCountByVacancyId(int $vacancyId): int`: количество откликов по вакансии со статусом `STATUS <> 'SPAM'`.
   - `getValidCountMap(array $vacancyIds): array<int, int>`: пакетный подсчёт для списка вакансий.
   - `getWeekCountIncludingSpam(int $vacancyId): int`: количество откликов за 7 дней **без фильтра по SPAM** (сохранение бага №2 для детальной страницы).
   - `getWeekSummary(): array{total: int, vacancies: int}`: общее количество валидных откликов и уникальных вакансий за 7 дней (`COUNT(*)`, `COUNT(DISTINCT VACANCY_ID)` где `STATUS <> 'SPAM'`).
3. **`lib/Repository/VacancyRepository.php`:**
   - `getById(int $id): ?array`: выборка активного элемента инфоблока по ID.
   - `getByCode(string $code): ?array`: выборка активного элемента по CODE.
   - `findList(VacancyFilterDto $filter, array $sort): array{rows: array, total: int}`:
     - Построение фильтра инфоблока:
       - `city` -> `PROPERTY_CITY`
       - `section` -> `SECTION_ID` (+ `INCLUDE_SUBSECTIONS => Y`)
       - `exp` -> `PROPERTY_EXPERIENCE`
       - `salary` -> `>=PROPERTY_SALARY_FROM` (строго сохраняя баг №8)
       - `hot` -> `!PROPERTY_HOT => false`
       - `fav` -> `ID => $favoriteIds`
       - `q` -> ConditionTree `LOGIC => OR` (`%NAME`, `%PREVIEW_TEXT`, `%PROPERTY_TAGS`, сохраняя баг №9)
     - Подсчёт общего числа элементов `total`.
     - Выборка среза пагинации с учётом рассчитанного `$filter->page`.
   - `getRelated(int $vacancyId, int $sectionId, int $cityId, int $limit): array`:
     - Шаг 1: выборка из того же раздела `SECTION_ID` (исключая текущую).
     - Шаг 2: если элементов < `$limit` и `$cityId > 0`, добор недостающих элементов из того же города (`PROPERTY_CITY = $cityId`, исключая уже выбранные), сохраняя баг №3.
   - `getSectionsWithCounts(int $iblockId): array`: выборка активных разделов инфоблока с подсчётом активных элементов.
   - `getCities(int $iblockId): array`: список городов из списочного свойства `CITY`.
   - `getExperienceList(int $iblockId): array`: список опыта из списочного свойства `EXPERIENCE`.

---

### Шаг 5. Слой сценариев (Service)

Классы в `lib/Service/` содержат логику сценариев, принимают и возвращают DTO и `Result`:

1. **`lib/Service/FavoriteService.php`:**
   - `getFavoriteIds(): array<int>`: чтение массива ID из `$_SESSION['LEGACY_VACANCY_FAV']`.
   - `toggle(int $vacancyId): bool`: добавление/удаление из сессии.
   - `isFavorite(int $vacancyId): bool`: проверка наличия ID.
   - Метод валидации: проверка, что вакансия существует и активна (`existsActive($vacancyId)`).
2. **`lib/Service/VacancyResponseService.php`:**
   - `sendResponse(VacancyResponseInputDto $dto, int $timeout): Result`:
     - Валидация входных данных:
       - `name`: минимум 2 символа (`LV_FORM_ERR_NAME`).
       - `email`: регулярное выражение `/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i` (`LV_FORM_ERR_EMAIL`).
       - `phone`: если передан — проверка 10–15 цифр (`LV_FORM_ERR_PHONE`).
       - `message`: строго `mb_strlen($message) < 10` (сохраняя баг №10).
       - Таймаут: проверка `time() - $_SESSION['LEGACY_LAST_RESPONSE'] < $timeout`.
     - Сохранение в `VacancyResponseRepository`.
     - Отправка почтового события через `CEvent::Send('LEGACY_VACANCY_RESPONSE', SITE_ID, ...)`.
     - Запись отметки времени в `$_SESSION['LEGACY_LAST_RESPONSE']`.
3. **`lib/Service/VacancyService.php`:**
   - `getList(VacancyFilterDto $filter): VacancyListDto`:
     - Определение порядка сортировки:
       - `salary` -> `['PROPERTY_SALARY_FROM' => 'DESC,NULLS', 'ID' => 'DESC']`
       - `name` -> `['NAME' => 'ASC', 'ID' => 'DESC']`
       - `views` -> **Специальная обработка (Баг №1):** выборка по дате (`ACTIVE_FROM DESC, ID DESC`), затем пост-сортировка полученных элементов текущей страницы по `VIEWS DESC, ID DESC` через `usort`.
       - `date` / fallback -> `['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC']`
     - Вызов `VacancyRepository::findList()`.
     - Пакетное обогащение списка просмотров и откликов (через `VacancyStatRepository::getViewsMap()` и `VacancyResponseRepository::getValidCountMap()`).
     - Расчёт новизны вакансий: `(time() - $ts) < 3 * 86400` (сохраняя баг №18).
     - Форматирование зарплат и дат (`legacy_format_salary`, `legacy_days_ago`).
   - `getDetail(int $id, string $code, bool $isPost): ?VacancyDto`:
     - Разрешение вакансии: **приоритет ID над CODE (Баг №5)**.
     - Проверка активности: если не найдена или `ACTIVE !== 'Y'` -> возврат `null` (для 404).
     - Инкремент просмотров: если `!$isPost`, вызов `VacancyStatRepository::incrementViews($id)` (сохраняя баг №13).
     - Получение счётчиков: валидные отклики и недельные отклики со спамом (сохраняя баг №2).
   - `getRelated(VacancyDto $vacancy, int $limit): list<VacancyDto>`: получение похожих вакансий через репозиторий.
4. **`lib/Service/SidebarService.php`:**
   - `getForList(int $currentSectionId, int $popularCount): SidebarDto`: сборка разделов, топ-популярных вакансий и недельной сводки.
   - `getForDetail(VacancyDto $currentVacancy, int $popularCount, int $relatedCount): SidebarDto`:
     - Блок «Популярные»: топ вакансий по просмотрам, **за исключением текущей вакансии** (`$popularId != $currentVacancy->id`).
     - Блок «Похожие»: результат `VacancyService::getRelated()`.
     - Блоки «Направления» и «Сводка» не заполняются (сохраняя баг №16).

---

### Шаг 6. Рефакторинг компонента `local/components/legacy/vacancies/`

Замена устаревшего процедурного файла `component.php` на объектный `class.php`:

1. Создать `local/components/legacy/vacancies/class.php`:
   - Объявить класс `LegacyVacanciesComponent extends \CBitrixComponent`.
   - Внедрить зависимости через `ServiceLocator::getInstance()->get(...)`:
     - `VacancyService`
     - `VacancyResponseService`
     - `FavoriteService`
     - `SidebarService`
   - Реализовать `onPrepareComponentParams($arParams)`: типизация параметров (`PAGE_SIZE`, `DEFAULT_SORT`, `BASE_URL`, `SHOW_POPULAR`, `RELATED_COUNT`, `FORM_TIMEOUT`).
   - Реализовать `executeComponent()`:
     - Определение режима:
       - Если передан `$_REQUEST['ID']` или `$_REQUEST['CODE']` (строго в верхнем регистре, сохраняя баг №4) -> детальный режим или 404.
       - Иначе -> режим списка.
     - В режиме детальной:
       - Обработка POST-формы отклика (вызов `VacancyResponseService::sendResponse`). При успехе — `LocalRedirect($item->url . '&sent=Y#respond')`.
       - Обработка прямого GET `?sent=Y` (сохраняя баг №12).
       - Если вакансия не найдена: установка статуса 404 (`\CHTTP::SetStatus('404 Not Found')`, `@define('ERROR_404', 'Y')`), заголовок окна `Вакансия не найдена или уже закрыта`, отображение шаблона 404.
       - Установка `<title>` с зарплатой в скобках (сохраняя баг №17): `#NAME# (от X до Y ₽)`.
       - Установка метатегов `description` (из `PREVIEW_TEXT`) и `keywords` (из `TAGS`).
     - В режиме списка:
       - Сборка `VacancyFilterDto`.
       - Получение `VacancyListDto`.
       - Расчёт URL пагинации (ссылка на 1-ю страницу всегда без `page=1`).
       - Установка заголовка страницы и `description` (конкатенация названий первых 5 элементов, сохраняя баг №17).
       - Сборка данных сайдбара через `SidebarService`.
     - Вызов `$this->includeComponentTemplate()`.
2. **Шаблон `templates/.default/template.php`:**
   - **Удалить все прямые SQL-запросы к БД** (`$rsWeek = $DB->Query(...)`, `$rsTotal = $DB->Query(...)`). Все данные теперь передаются в `$arResult` готовыми из компонента.
   - Сохранить абсолютно неизменными:
     - Разметку HTML, ID (`#legacy-vacancies`, `#vacancy-N`, `#respond`), классы (`.lv`, `.lv-list`, `.lv-item-hot`, `.lv-badge-new`, `.lv-fav`, `.lv-empty`, `.lv-form-ok`, `.lv-side` и др.).
     - Атрибуты ссылок, формы, инпутов (`name="legacy_respond"`, `name="sessid"`, `name="vacancy_id"`, `name="q"`, `name="section"`).
   - Вынести JS в отдельный файл `script.js` шаблона, сохранив поведение клика по звездочке избранного и текст всплывающего `alert` при ошибке.
3. **Ликвидация `result_modifier.php`:**
   - Логика из `result_modifier.php` (подготовка тегов, расчет новизны, установка title и description) полностью перенесена внутрь `class.php` и сервисов. Файл `result_modifier.php` удаляется или оставляется пустым.

---

### Шаг 7. Рефакторинг `/vacancies/ajax.php` и современные контроллеры модуля

1. **Создание современных контроллеров модуля с `Controller\Request`:**
   - `\Ws\Vacancies\Controller\Favorite`:
     ```php
     final class Favorite extends Controller
     {
         public function getAutoWiredParameters(): array
         {
             return [
                 new ValidationParameter(
                     ToggleFavoriteRequest::class,
                     fn(): ToggleFavoriteRequest => ToggleFavoriteRequest::createFromRequest($this->getRequest()),
                 ),
             ];
         }

         #[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])] // Csrf временно отключен для баг-в-баг
         public function toggleAction(ToggleFavoriteRequest $request, FavoriteService $favoriteService): ?array
         {
             $result = $favoriteService->toggle($request->id);
             if (!$result->isSuccess()) {
                 $this->addErrors($result->getErrors());
                 return null;
             }
             return $result->getData();
         }
     }
     ```
   - `\Ws\Vacancies\Controller\Vacancy`:
     - Экшен `respondAction(VacancyResponseRequest $request, VacancyResponseService $responseService)` с автовайрингом `VacancyResponseRequest`.
2. **Адаптер обратной совместимости `local/components/legacy/vacancies/ajax.php`:**
   - Очистить файл от процедурного кода и SQL-запросов.
   - Использовать `FavoriteService` / контроллер для выполнения операций.
   - Сохранить **байт-в-байт** форматы JSON-ответов:
     - `{"success":true,"favorite":true,"count":1}`
     - `{"success":true,"favorite":false,"count":0}`
     - `{"success":false,"error":"Не указана вакансия"}`
     - `{"success":false,"error":"Вакансия не найдена"}`
     - `{"success":false,"error":"Неизвестное действие"}`
   - Заголовки ответа: `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`.

---

### Шаг 8. Совместимость с `legacy_helpers.php`

В `/local/php_interface/include/legacy_helpers.php` объявлялись глобальные функции (`legacy_get_views`, `legacy_format_salary`, `legacy_plural` и др.).
Чтобы сторонний код или консольные команды не сломались:
- Функции форматирования (`legacy_format_salary`, `legacy_plural`, `legacy_days_ago`, `legacy_format_number`) сохраняются как чистые функции или проксируют вызовы в хелперы модуля.
- Функции работы с БД (`legacy_get_views`, `legacy_register_view`, `legacy_get_response_count`) преобразуются в тонкие прокси-вызовы к соответствующим репозиториям модуля `ws.vacancies`.

---

## 5. План верификации и тестирования

1. **Дымовое тестирование CLI:**
   - Проверка синтаксиса всех созданных PHP-файлов: `php -l`.
   - Проверка автозагрузки PSR-4 модуля `ws.vacancies` через `Bitrix\Main\Loader::includeModule('ws.vacancies')`.
2. **Прогон характеристических тестов Pest:**
   - Выполнить команду запуска e2e-тестов:
     ```bash
     export PATH="/Users/kirk/Library/Application Support/Omut/bin/shims:$PATH"
     cd /Users/kirk/Omut/lesson3-copy.bitrix/e2e && ./vendor/bin/pest
     ```
   - **Критерий приёмки:** Все 45 тестов в `e2e/tests/Browser/VacanciesTest.php` должны завершаться со статусом **PASS**.
3. **Контрольный чеклист соответствия правилам:**
   - [ ] Код размещён строго в `/local/`, ядро `/bitrix/` не затронуто.
   - [ ] В `lib/` нет ни одного вызова `$_GET`, `$_POST`, `$_REQUEST` (только во входных точках: `class.php` / контроллерах).
   - [ ] Прямые вызовы `$DB->Query` и `*Table::` отсутствуют в компоненте, шаблоне и сервисах (только в `Repository/`).
   - [ ] Все PHP-файлы содержат `declare(strict_types=1);`.
   - [ ] Ни один легаси-баг не был исправлен случайно при рефакторинге.
