# Triangle Engine HTTP

HTTP-адаптер для приложений Triangle. Он связывает обработку запросов с Localzet Server 7, Router и Middleware Triangle. Конфигурация приложения, маршруты и запуск listener-а остаются ответственностью bootstrap проекта.

## Совместимость

Версия 3.x рассчитана на PHP 8.1+, Localzet Server 7.1+, Triangle Engine 9, Router 2, Middleware 2 и расширение PHP cURL. Версия 2.x относится к прежней ветке совместимости Engine. Не смешивайте эти наборы зависимостей в одном lock-файле.

## Установка

```bash
composer require triangle/engine-http:^3.0
```

Полный порядок запуска и проверки версий описан в [руководстве по установке](https://server.localzet.com/projects/engine-http/install).

## Обработка запроса и ответа

Регистрируйте маршруты до сборки dispatcher-а Router:

```php
use localzet\Server\Protocols\Http\Response;
use Triangle\Router;

Router::any('/health', static fn () => new Response(
    200,
    ['Content-Type' => 'application/json'],
    '{"status":"ok"}',
));

Router::collect([]);
```

Engine HTTP передаёт запрос через middleware маршрута и разрешение контроллеров. HTTP-ответ Localzet Server сохраняет статус, заголовки и тело. Остальные значения превращаются в ответ `200` со строковым телом.

## Безопасность и ограничения

- Аутентификацию и проверку прав настраивает приложение или его middleware.
- CORS по умолчанию выключен. Если нужен доступ с другого origin, задайте явные origin и заголовки в `app.http_headers`.
- Выполнение PHP-файлов внутри публичного каталога по умолчанию выключено. Включайте его только при ясной необходимости и проверяемой границе исполнения.
- Listener, HTTP framing, жизненный цикл worker-ов и backpressure реализует Localzet Server.

Смотрите [обработку запросов](https://server.localzet.com/projects/engine-http/requests), [конфигурацию](https://server.localzet.com/projects/engine-http/architecture) и [руководство по безопасности](https://server.localzet.com/projects/engine-http/security).

## Проверки

```bash
composer validate --strict --no-check-publish --no-interaction
composer run test --no-interaction
composer audit --abandoned=report
```

Тесты проверяют совместимость ответов и HTTP-запрос к Server 7 через loopback.

## Лицензия

AGPL-3.0-or-later. Текст лицензии — в файле [LICENSE](LICENSE).
