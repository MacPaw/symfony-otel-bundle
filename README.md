# Symfony OpenTelemetry client Bundle
This bundle provides configured official otel bundle for Symfony application under the hood.
In addition, it provides a general way to configure telemetry collection via configuration list of Kernel listeners.

## Setup bundle
Enable bundle in your Symfony application:
```php
return [
    Macpaw\SymfonyOtelBundle\SymfonyOtelBundle::class => ['all' => true],
    // ...
];
```

## Configuration understanding
This bundle is a decoration under [https://github.com/opentelemetry-php/contrib-sdk-bundle](https://github.com/opentelemetry-php/contrib-sdk-bundle) to simplify integration with the official bundle and provide generic kernel listeners for data tracing.
If you want to get more information about configuration, please refer to the official bundle documentation.

## Setup bundle

## Kernel event listeners
Example of kernel event listener implementation can be found in `Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer` class.
When specific listener need to be configured, you need to add it to `span_tracers` list in configuration after implementation.

### Example

1. Create a custom span tracer class:

    ```php
    namespace Macpaw\SymfonyOtelBundle\Span;

    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    use OpenTelemetry\API\Trace\Span;

    class CustomSpanTracer implements EventSubscriberInterface
    {
        public function onKernelRequest(RequestEvent $event): void
        {
            $span = Span::startSpan('custom_span');
            // Add custom tracing logic here
            $span->end();
        }
   
        public static function getSubscribedEvents(): array
        {
            return [
                KernelEvents::REQUEST => 'onKernelRequest',
            ];
        }
    }
    ```
   
2. Register the custom span tracer in the Symfony configuration:

    ```yaml
    # config/packages/symfony_otel.yaml
    symfony_otel:
        span_tracers:
            - App\Span\CustomSpanTracer
    ```

## Environment Variables

This bundle supports the following OpenTelemetry SDK environment variables for configuration:

- `OTEL_RESOURCE_ATTRIBUTES`: Key-value pairs to be used as resource attributes.
- `OTEL_SERVICE_NAME`: The name of the service.
- `OTEL_TRACES_EXPORTER`: The exporter to be used for traces.
- `OTEL_METRICS_EXPORTER`: The exporter to be used for metrics.
- `OTEL_LOGS_EXPORTER`: The exporter to be used for logs.
- `OTEL_EXPORTER_OTLP_ENDPOINT`: The endpoint for the OTLP exporter.
- `OTEL_EXPORTER_OTLP_HEADERS`: Headers to be sent with each OTLP request.
- `OTEL_EXPORTER_OTLP_TIMEOUT`: Timeout for OTLP requests.
- `OTEL_PROPAGATORS`: Propagators to be used for context propagation.
- `OTEL_TRACES_SAMPLER`: The sampler to be used for traces.
- `OTEL_TRACES_SAMPLER_ARG`: Arguments for the trace sampler.

For a complete list and detailed descriptions, please refer to the [OpenTelemetry SDK Environment Variables documentation](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/).

## QA

### What is a “hook” in OpenTelemetry?
Це механізм, який дає змогу «підчепити» вашу власну логіку до(pre-hook) або після (post-hook) виконання будь-якого методу без зміни його вихідного коду. Зазвичай на pre-hook ви стартуєте спан, а на post-hook — додаєте атрибути, записуєте винятки та завершуєте спан.

- Приклад 1. Zero-code instrumentation — hook на метод DemoClass::run
```php
use OpenTelemetry\Instrumentation;

// Реєструємо hook для методу run() у DemoClass
OpenTelemetry\Instrumentation\hook(
    class: DemoClass::class,
    function: 'run',
    pre: static function ($context, $args) {
        // Створюємо і запускаємо спан перед виконанням run()
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('demo');
        $span = $tracer->spanBuilder('demo.run')->startSpan();
        // Повертаємо новий контекст зі спаном
        return ['context' => $context->withSpan($span)];
    },
    post: static function ($context, $args, $result, $exception) {
        // Отримуємо активний спан з контексту
        $span = $context->getSpan();
        // Якщо була помилка — фіксуємо її
        if ($exception instanceof \Throwable) {
            $span->recordException($exception);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        }
        // Завершуємо спан
        $span->end();
    }
);
```

- Приклад 2. Zero-code instrumentation — hook на PDO-запит

```php
use OpenTelemetry\Instrumentation;

// «Підчепимо» виконання SQL-запиту через PDO::query()
OpenTelemetry\Instrumentation\hook(
    class: PDO::class,
    function: 'query',
    pre: static function ($context, $args) {
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('db');
        $span = $tracer->spanBuilder('db.query')->startSpan();
        // Додаємо атрибут із самим SQL
        $span->setAttribute('db.statement', $args[0]);
        return ['context' => $context->withSpan($span)];
    },
    post: static function ($context, $args, $result, $exception) {
        $span = $context->getSpan();
        if ($exception) {
            $span->recordException($exception);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        } else {
            // За бажанням можна зчитати rowCount() та записати як атрибут
            $span->setAttribute('db.rows_returned', $result->rowCount());
        }
        $span->end();
    }
);
```
> Обидва приклади ілюструють, як без зміни бізнес-логіки ви можете автоматично стартувати та завершувати спани, а також збагачувати їх корисними атрибутами та інформацією про помилки.

### What is a span in OpenTelemetry, and how are spans organized hierarchically?

Це базова одиниця трейсування, яка моделює одну операцію або крок у вашій системі (наприклад, HTTP-запит, виклик методу чи SQL-запит). Кожен span фіксує:

- Ім’я спана (людинозрозуміле, наприклад “HTTP GET /users” або “DB query”).
- TraceContext:
  - trace_id — ідентифікатор усієї транзакції (trace), спільний для всіх спанів у ній.
  - span_id — унікальний ідентифікатор цього спана.
  - parent_span_id — span_id його батька; для кореневого спана — відсутній.
- Час початку та завершення — дає змогу обчислити тривалість операції.
- Атрибути (Attributes) — ключ-значення із метаданими (наприклад, HTTP-статус, SQL-запит, ідентифікатор користувача).
- Суб-події (Events) — точкові позначки всередині спана (наприклад “cache miss” або “DB committed”) з власним таймстампом.
- Зв’язки (Links) — неієрархічні посилання на інші спани (корисні при batch-обробці чи fork/join).
- Статус (Status) — код успіху чи помилки.

#### Ієрархічна організація спанів
1. Trace ID. Уся група спанів (trace) отримує спільний trace_id. 
2. Parent Span ID. Кожен дочірній спан зберігає parent_span_id, що вказує на його батьківський спан. Це формує дерево викликів. 
3. Контекст (Context Propagation).
   - При переході між компонентами (наприклад, сервісами через HTTP) OpenTelemetry серіалізує в заголовки traceparent/tracestate (специфікація W3C Trace Context). 
   - На приймачі ці заголовки десеріалізуються, і дочірні спани автоматично “наслідують” контекст, знаючи свого батька.

Приклад: Створення кореневого спана
```php
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('example');
// Починаємо новий кореневий спан
$rootSpan = $tracer
    ->spanBuilder('root.operation')
    ->startSpan();

// ... логіка операції ...

$rootSpan->end();
```

Приклад: Створення вкладеного (дочірнього) спана
```php
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('example');

// Приклад: у межах rootSpan відкриваємо childSpan
$rootSpan = $tracer->spanBuilder('root.operation')->startSpan();

$childSpan = $tracer
    ->spanBuilder('child.operation')
    // Встановлюємо батьківський контекст вручну (якщо контекст не передається автоматично)
    ->setParent($rootSpan->getContext())
    ->startSpan();

// ... логіка дочірньої операції ...

$childSpan->end();
$rootSpan->end();
```

У результаті ви отримаєте трасу зі спаном «root.operation», у якому вкладено «child.operation» — і цю ієрархію можна візуалізувати в будь-якому бекенді для трейсування.

### What is SpanKind in OpenTelemetry, and which kinds are available?

**SpanKind** — це перелік (enum) ролей, які уточнюють призначення спана щодо віддалених викликів і стиль взаємодії. SpanKind допомагає системам трейсингу зрозуміти:

1. **Напрямок виклику**
   * **CLIENT** / **PRODUCER** – вихідні спани, які ініціюють операцію.
   * **SERVER** / **CONSUMER** – вхідні спани, які обробляють вхідний запит або повідомлення.
2. **Стиль обробки**
   * **CLIENT** / **SERVER** – модель запит → відповідь.
   * **PRODUCER** / **CONSUMER** – відкладене виконання (deferred), наприклад у messaging-сценаріях.
3. **INTERNAL** – локальні внутрішні операції, що не пов’язані з мережевими чи відкладеними викликами.

---

#### Доступні SpanKind

| SpanKind     | Опис ролі                                                                                                                                          |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CLIENT**   | Описує вихідний HTTP/RPC/SOAP-запит до віддаленого сервісу. Зазвичай є батьком віддаленого `SERVER`-спана.                                         |
| **SERVER**   | Описує обробку вхідного запиту (HTTP, gRPC тощо) на стороні сервера. Найчастіше дочірній по відношенню до клієнтського спана.                      |
| **PRODUCER** | Описує ініціалізацію або планування операції (наприклад, відправлення повідомлення до черги). Може завершитися до того, як споживач почне обробку. |
| **CONSUMER** | Описує обробку операції, яку ініціював producer (наприклад, отримання та обробка повідомлення з черги).                                            |
| **INTERNAL** | Значення за замовчуванням. Використовується для внутрішніх дій у межах додатку без мережевих або брокерних викликів.                               |

---

#### Короткі рекомендації

* **Один спан — одна роль.** Не варто поєднувати, наприклад, `SERVER` і `CLIENT` в одному спані.
* Для кожного вихідного виклику створюйте новий `CLIENT`-спан **перед** ін’єкцією контексту в заголовки.
* У messaging-сценаріях на кожне повідомлення створюйте окремий `PRODUCER`-спан, а при його обробці — `CONSUMER`-спан.

---

Таким чином, **SpanKind** дозволяє чітко класифікувати спани за їхньою роллю й полегшує аналіз та візуалізацію розподілених трас.

### How do you add custom attributes to a span?

#### 1. На рівні SpanBuilder
Ви можете «пришити» атрибути ще до того, як спан буде створено. Це зручно, якщо значення вже відомі на момент побудови спана:

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\SpanBuilder;

$tracer = Globals::tracerProvider()->getTracer('example');

// Побудова спана з атрибутами
$span = $tracer
    ->spanBuilder('order.process')
    ->setAttribute('order.id', '12345')            // одиночний атрибут
    ->setAttribute('user.authenticated', true)     // булевий атрибут
    ->setAttribute('retry.count', 3)               // цілочислений атрибут
    ->startSpan();

// … ваша логіка …

$span->end();
```

#### 2. На вже створеному Span
Якщо потрібно додати атрибути в середині виконання операції, то між startSpan() і end() викликайте:

```php
// Припустимо, ми вже маємо спан
$span = $tracer->spanBuilder('db.query')->startSpan();

// Перевіримо, чи спан записується (не відкинутий семплером)
if ($span->isRecording()) {
    // Один атрибут
    $span->setAttribute('db.statement', 'SELECT * FROM users WHERE id = ?');
    // Одразу декілька атрибутів
    $span->setAttributes([
        'db.system'      => 'mysql',
        'db.user'        => 'readonly',
        'db.rows_returned' => 42,
    ]);
}

// … виконання запиту …

$span->end();
```

- Для динамічних значень додавайте їх вже на спані між startSpan() і end()—обов’язково перевіряйте isRecording().
- Дотримуйтеся semantic conventions та не перевантажуйте спани надмірною кількістю висококардинальних атрибутів.
- Таким чином, ви зможете гнучко збагачувати трейсінг-дані корисними метаданими та аналізувати їх у бекенді.

### What are events within a span, and how are they used?
Подія (Event) у span’і — це «точковий» запис із власним таймстампом і необов’язковим набором атрибутів, який повідомляє про важливу подію чи стан у межах виконання операції. Події допомагають:

- Фіксувати миттєві стани (наприклад, cache miss, крок алгоритму, checkpoints)
- Логувати винятки та трасування помилок
- Відмічати бізнес-події (наприклад, “user.logged_in”)

#### Приклад: Додавання події
```php
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('example');
$span = $tracer->spanBuilder('image.process')->startSpan();

if ($span->isRecording()) {
    // Простий івент без атрибутів
    $span->addEvent('cache.miss');

    // Івент з додатковими атрибутами
    $span->addEvent(
        'db.query.executed',
        [
            'db.statement' => 'SELECT * FROM images WHERE id = ?',
            'db.rows'      => 1,
        ]
    );
}

// ... логіка обробки зображення ...

$span->end();
```

#### Приклад: Додавання події з власним timestamp
```php
if ($span->isRecording()) {
    $timestamp = \DateTimeImmutable::createFromFormat(
        'U.u', sprintf('%.6F', microtime(true) - 0.5)
    ); // подія півсекунди тому

    $span->addEvent(
        'message.sent',
        ['message.id' => 'abc123'],
        $timestamp
    );
}

```
#### Приклад: Логування винятків як подій
````php
try {
    // якась логіка, що може викинути виключення
} catch (\Throwable $e) {
    if ($span->isRecording()) {
        $span->addEvent(
            'exception',
            [
                'exception.type'    => get_class($e),
                'exception.message' => $e->getMessage(),
                'exception.stack'   => $e->getTraceAsString(),
            ]
        );
    }
    // можна все одно передати виключення далі
    throw $e;
}
````

#### Коли використовувати події
- Трасування кроків виконання: щоб бачити, коли всередині спана трапилась та чи інша операція.
- Моніторинг і дебаг: події з повідомленнями й помилками допомагають швидко знайти проблему в дашборді.
- Бізнес-події: наприклад, «user.signup», щоб зв’язати телеметрію з бізнес-логікою.

Події роблять спан більш інформативним, додаючи до нього часові позначки ключових моментів і деталі, які важко виразити просто атрибутами.

###   What is the context in OpenTelemetry?

Це незмінний «контейнер» для метаданих трейсингу, який передається між різними частинами вашої програми або між сервісами. Він містить:

- SpanContext (trace_id, span_id, trace_flags, trace_state) — ідентифікаційні дані для зв’язку спанів у межах однієї трасі.
- Активний спан — посилання на поточний спан, до якого будуть додаватися події й атрибути, якщо викликати API без явного контексту.
- Baggage — опційні довільні пари ключ–значення, які теж пропагуються вздовж трасування і можуть використовуватися, наприклад, для передавання корисних бізнес-даних між сервісами.

Контекст гарантує, що коли ви створюєте новий (дочірній) спан або прокидаєте запит по HTTP, він «усвідомлює», хто його батько, і передає необхідні заголовки для продовження трасування на стороні приймача.

#### Приклад. Створення спана та запис у контекст
````php
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Context;

// Отримуємо трейсер
$tracer = Globals::tracerProvider()->getTracer('example');

// Стартуємо кореневий спан
$rootSpan = $tracer
    ->spanBuilder('root.operation')
    ->startSpan();

// Записуємо спан у поточний контекст
$context = $rootSpan->storeInContext(Context::getCurrent());

// Тепер $context містить ідентифікатори трасси та активний спан
````

#### Приклад. Створення дочірнього спана, передаючи контекст
````php
// Замість Context::getCurrent() явно передаємо збережений контекст
$childSpan = $tracer
    ->spanBuilder('child.operation')
    ->setParent($context)          // вказуємо, що батько — rootSpan
    ->startSpan();

// … логіка …

$childSpan->end();
$rootSpan->end();
````

#### Приклад. Пропагування контексту через HTTP (W3C Trace Context)
````php
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

// При підготовці вихідного HTTP-запиту інжектимо заголовки
$propagator = TraceContextPropagator::getInstance();
$headers = [];
$propagator->inject(
    $context,                     // контекст з кореневим або дочірнім спаном
    $headers,
    // колбек для установки заголовка
    function (array &$carrier, string $key, string $value): void {
        $carrier[$key] = $value;
    }
);

// Тепер $headers містить:
//   traceparent: …
//   tracestate: …
//
// – їх потрібно додати до HTTP-запиту клієнта

````
#### На приймаючому боці:
````php
// При обробці вхідного запиту екстрактимо контекст
$extractedContext = $propagator->extract(
    $_SERVER,                     // або масив заголовків
    fn(array $carrier, string $key) => $carrier[$key] ?? null
);

// І вже з $extractedContext створюємо свій серверний спан
$serverSpan = $tracer
    ->spanBuilder('http.server')
    ->setParent($extractedContext)
    ->startSpan();
````

#### Коли і навіщо використовувати Context
1. Ієрархія спанів. Завдяки контексту дочірні спани автоматично «знають» свого батька, без ручного передачі span_id. 
2. Пропагування між процесами. HTTP-заголовки або повідомлення в черзі несуть контекст, тож трейсинг не обривається на межі сервісів. 
3. Baggage. Можна записати в контекст бізнес-дані (наприклад, user.id), і вони пройдуть через усі сервіси разом із trace_id.

Без Context ви б мусили вручну передавати trace_id/span_id і додаткові дані до кожного виклику — Context спрощує й уніфікує це завдання.

### What is a scope in OpenTelemetry, and how does it differ from context?

Це об’єкт, який повертається при активації контексту чи спана та керує тим, який контекст (або спан) вважається «активним» у поточному потоці виконання. Після виклику activate() ви отримуєте Scope, а щоб «деактивувати» цей контекст і відновити попередній, потрібно викликати detach()
OpenTelemetry.

Context — це іммутабельний контейнер метаданих трейсингу (традиційно містить trace_id, span_id, прапор sampled, а також baggage), який переноситься між функціями, потоками чи навіть між сервісами через HTTP-заголовки (W3C Trace Context) і зв’язує спани в одну трасу.

#### Основні відмінності
- Context — дані (SpanContext + Baggage), які описують трасу й поточний спан, але не визначають, який із контекстів саме зараз активний.
- Scope — механізм тимчасового «включення» конкретного Context або SpanContext у глобальний стек активних контекстів поточного потоку.

```php
use OpenTelemetry\API\Globals;

// 1. Створюємо та стартуємо кореневий спан
$tracer = Globals::tracerProvider()->getTracer('example');
$rootSpan = $tracer
    ->spanBuilder('root.operation')
    ->startSpan();

// 2. Активуємо спан — отримуємо Scope
$scope = $rootSpan->activate();  // повертається OpenTelemetry\Context\Scope :contentReference[oaicite:2]{index=2}

// 3. В межах цього scope всі нові спани стануть дочірніми для $rootSpan
$childSpan = $tracer->spanBuilder('child.operation')->startSpan();
$childSpan->end();

// 4. Деактивуємо Scope — відновлюємо попередній контекст
$scope->detach();

// 5. Завершуємо кореневий спан
$rootSpan->end();

```

Без викликів activate()/detach() OpenTelemetry не знатиме, який спан має бути «батьківським» для новостворених спанів, і ієрархія може бути побудована некоректно.

#### What transports does OpenTelemetry support (e.g. HTTP/OTLP, gRPC)?
OpenTelemetry дає змогу гнучко обирати, як і куди відправляти ваші дані трейсингу, метрик і логів. Основні варіанти:

1. **OTLP (OpenTelemetry Protocol)**

   * **gRPC** (порт 4317) — бінарний Protobuf, низька затримка, back-pressure через gRPC.
   * **HTTP** (порт 4318) — POST-запити з Protobuf або JSON, простіший стек.

2. **Підтримка старих бекендів трейсингу**

   * **Jaeger**: Thrift/HTTP (14268) або gRPC (14250).
   * **Zipkin**: HTTP/JSON (9411).

3. **Метрики**

   * **Prometheus** — енпойнт `/metrics` для pull-scrape (стандарт у Kubernetes).
   * **StatsD** — UDP (8125), лічильники й таймери.

4. **Інші опції**

   * **Kafka** — публікувати Protobuf/JSON-пакети в тему.
   * **File** — запис у файл (Protobuf/JSON) для локальної діагностики.
   * **Custom Exporter** — будь-який протокол через власну реалізацію інтерфейсу `Exporter`.

Таким чином, ви можете вибрати оптимальний транспорт залежно від вимог до затримки, сумісності з бекендом і архітектури вашої системи.

### Describe the overall architecture and walk through how to create and export custom spans?
#### Архітектура на високому рівні

1. TracerProvider
– Створюється через TracerProviderFactory і реєструється як сервіс TracerProviderInterface.
– До нього підключаються SpanProcessor (Simple або Batch) і Exporter (OTLP/gRPC, OTLP/HTTP, Jaeger тощо).
2. Exporter
– Реалізує ExporterInterface — наприклад, ReactPhpOtlpExporter надсилає батчі спанів на вказаний OTLP-ендпойнт.
3. SpanProcessor
– SimpleSpanProcessor — відправляє кожен спан одразу після end().
– BatchSpanProcessor — акумулює спани й шле партіями за розкладом або за розміром батчу.
4. TraceService / AsyncTraceService
– Фасади для отримання Tracer та створення спанів у синхронному або асинхронному режимі.
5. InstrumentationRegistry + AbstractInstrumentation
– Реєструють декларативні інструментування (на базі подій Symfony або PHP-хук-інструментів) без правок бізнес-логіки.
6. Event Subscribers
– Підписуються на події Symfony (KernelEvents::REQUEST, TERMINATE тощо) і автоматично стартують/завершують HTTP-спани.

#### Створення і експорт кастомного спана
#### Крок 1. Отримуємо Tracer у сервісі чи контролері
````php
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;

class OrderController
{
    public function __construct(private TraceService $traceService) {}

    public function process(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('app.orders');

````
#### Крок 2. Будуємо і активуємо спан
        $span = $tracer
            ->spanBuilder('order.process')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('order.id', 123)
            ->startSpan();

        $scope = $span->activate();  // щоб дочірні спани автоматично наслідували цей
#### Крок 3. Додаємо атрибути, івенти та обробляємо винятки
````php
        try {
            $span->addEvent('payment.started');
            // … бізнес-логіка …
            $span->addEvent('payment.completed');
        } catch (\Throwable $e) {
            if ($span->isRecording()) {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            }
            throw $e;
        } finally {
            $scope->detach();
            $span->end();  // BatchSpanProcessor забере спан у чергу на експорт
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
4. Використання AbstractInstrumentation для декларативного інструментування

    1. Створіть клас, що наслідує AbstractInstrumentation.
    2. Реалізуйте методи pre() та post(), де запускаєте й завершуюєте спан з власними атрибутами й івентами.
    3. Зареєструйте його в InstrumentationRegistry або через тег у конфігурації.
````
#### Підсумок
- TracerProvider → SpanProcessor → Exporter — канали, через які спан проходить до бекенду.
- TraceService дає вам Tracer у коді для ручного створення спанів.
- AbstractInstrumentation і Event Subscribers дозволяють автоматизувати старт/стоп спанів на базі подій Symfony.
- OTLP-експортер (гнучкий через gRPC або HTTP) надсилає зібрані спани до вашої системи моніторингу без додаткових зусиль.

### What is instrumentation? Within a package.
Інструментування (Instrumentation) у межах пакету — це сукупність коду та механізмів, які автоматично збирають дані спостереження (traces, метрики, логи) без необхідності впроваджувати моніторинговий код у бізнес-логіку.

#### Навіщо потрібне інструментування

- Прозоре: код додатку залишається чистим від викликів телеметрії.
- Послідовне: всі операції трасуються за єдиними правилами.
- Розширюване: дає можливість додавати власні спани або події там, де це потрібно.

#### Як це реалізовано в SymfonyOtelBundle

1. Event Subscribers
– Підписуються на події Symfony (KernelEvents::REQUEST, TERMINATE тощо) й автоматично стартують/закривають HTTP-спани.
2. AbstractInstrumentation
– Базовий клас для декларативного інструментування будь-яких дій (Doctrine-запитів, повідомлень, кастомних подій).
– Вам потрібно лише реалізувати методи pre() і post() або buildSpan(), де ви описуєте, як створювати спан і що в нього додавати.
3. InstrumentationRegistry
– Централізований реєстр усіх активних інструментувань, який автоматично завершує спани під час завершення запиту або скрипту.
4. TraceService / AsyncTraceService
– Дають змогу отримати Tracer та ручним кодом створювати спани у будь-якому місці вашого сервісу чи контролера.

#### Типи інструментування

1. Автоматичне
Використовує Event Subscribers й готові класи (наприклад, ExecutionTimeInstrumentation) для трасування HTTP-запитів, Doctrine, Messenger тощо – без жодних змін у вашому коді.
2. Ручне
Коли вам потрібно відслідкувати специфічну логіку:

```php
use Macpaw\SymfonyOtelBundle\Service\TraceService;

class OrderService
{
    public function __construct(private TraceService $trace) {}

    public function processOrder(array $data): void
    {
        $tracer = $this->trace->getTracer('app.orders');
        $span   = $tracer->spanBuilder('order.process')->startSpan();
        $scope  = $span->activate();

        try {
            // … бізнес-логіка …
            $span->addEvent('payment.completed');
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}

```

3. Кастомне через AbstractInstrumentation
Створіть клас-нащадок, зареєструйте його — і пакет сам підключить його до життєвого циклу запиту:

```php
namespace App\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;

class MyCustomInstrumentation extends AbstractInstrumentation
{
    public function getName(): string
    {
        return 'my_custom';
    }

    protected function buildSpan($builder)
    {
        return $builder
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan()
            ->setAttribute('foo', 'bar');
    }

    public function pre(): void
    {
        $this->initSpan();
    }

    public function post(): void
    {
        $this->span->end();
    }
}
```

Інструментування в пакеті — це автоматична обробка подій, методів та запитів вашого додатку для збору телеметрії. Завдяки абстракціям (AbstractInstrumentation, TraceService, Event Subscribers) ви отримуєте гнучкість: можна обирати між готовим автоматичним трасуванням і ручним додаванням спанів або створювати кастомні інструментування під власні потреби.
