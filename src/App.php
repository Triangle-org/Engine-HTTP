<?php declare(strict_types=1);

/**
 * @package     Triangle HTTP Component
 * @link        https://github.com/Triangle-org/Http
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2024 Triangle Framework Team
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *              For any questions, please contact <triangle@localzet.com>
 */

namespace Triangle\Http;

use Closure;
use ErrorException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use localzet\Server;
use localzet\Server\Connection\ConnectionInterface;
use localzet\Server\Connection\TcpConnection;
use localzet\Server\Protocols\Http;
use localzet\ServerAbstract;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;
use Triangle\Engine\Autoload;
use Triangle\Engine\Config;
use Triangle\Engine\Context;
use Triangle\Engine\Path;
use Triangle\Engine\Plugin;
use Triangle\Exception\ExceptionHandler;
use Triangle\Exception\ExceptionHandlerInterface;
use Triangle\Exception\InputTypeException;
use Triangle\Exception\MissingInputException;
use Triangle\Middleware\Bootstrap as Middleware;
use Triangle\Middleware\MiddlewareInterface;
use Triangle\Router;
use Triangle\Router\Dispatcher;
use Triangle\Router\RouteObject;
use Triangle\support\Request;
use Triangle\support\Response;
use function array_merge;
use function array_pop;
use function array_reduce;
use function array_splice;
use function array_values;
use function class_exists;
use function clearstatcache;
use function count;
use function current;
use function end;
use function explode;
use function get_class_methods;
use function gettype;
use function implode;
use function in_array;
use function is_a;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_string;
use function key;
use function method_exists;
use function ob_get_clean;
use function ob_start;
use function pathinfo;
use function scandir;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

/**
 * Class App
 */
class App extends ServerAbstract
{
    /**
     * Функция для обработки сообщений.
     *
     * @param mixed $connection Соединение TCP.
     * @param mixed $request Запрос.
     * @throws Throwable
     */
    public function onMessage(ConnectionInterface &$connection, mixed $request): void
    {
        try {
            // Устанавливаем контекст для соединения и запроса
            Context::set(TcpConnection::class, $connection);
            Context::set(Request::class, $request);

            // Получаем путь из запроса
            $path = $request->path();

            // Создаем ключ из метода запроса и пути
            $key = $request->method() . $path;

            // Если для данного ключа уже есть обратные вызовы
            if (isset(static::$callbacks[$key])) {
                // Получаем обратные вызовы
                $callback = static::getCallbacks($key, $request);

                // Отправляем обратный вызов
                static::send($connection, $callback($request), $request);
                return;
            }

            $status = 200;

            // Проверяем на небезопасные URI, находим файл или маршрут
            if (static::unsafeUri($path)) {
                $callback = static::getFallback(status: 422);
                $request->plugin = $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request, 422), $request);
                return;
            } else if (static::findFile($connection, $path, $key, $request)) return;
            else if ($callback = static::findRoute($connection, $path, $key, $request, $status)) {
                static::send($connection, $callback($request), $request);
                return;
            }

            // Парсим контроллер и действие из пути
            $controllerAndAction = static::parseControllerAction($path);

            // Получаем плагин по пути или из контроллера и действия
            $plugin = $controllerAndAction['plugin'] ?? Plugin::app_by_path($path);

            // Если контроллер и действие не найдены или маршрут по умолчанию отключен
            if (!$controllerAndAction
                || Router::isDefaultRouteDisabled($plugin, $controllerAndAction['app'] ?: '*')
                || Router::isDefaultRouteDisabled($controllerAndAction['controller'])
                || Router::isDefaultRouteDisabled([$controllerAndAction['controller'], $controllerAndAction['action']])
            ) { // Устанавливаем плагин в запросе
                $request->plugin = $plugin;

                // Получаем обратный вызов для отката
                $callback = static::getFallback($plugin, $status);

                // Устанавливаем приложение, контроллер и действие в запросе
                $request->app = $request->controller = $request->action = '';

                // Отправляем обратный вызов
                static::send($connection, $callback($request, $status), $request);
                return;
            }

            // Получаем приложение, контроллер и действие
            $app = $controllerAndAction['app'];
            $controller = $controllerAndAction['controller'];
            $action = $controllerAndAction['action'];

            // Получаем обратный вызов
            $callback = static::getCallback($plugin, $app, [$controller, $action]);

            // Собираем обратные вызовы
            static::collectCallbacks($key, [$callback, $plugin, $app, $controller, $action, null]);

            // Получаем обратные вызовы
            $callback = static::getCallbacks($key, $request);

            // Отправляем обратный вызов
            static::send($connection, $callback($request), $request);
        } catch (Throwable $e) {
            // Если возникло исключение, отправляем ответ на исключение
            static::send($connection, static::exceptionResponse($e, $request), $request);
        }
    }

    /**
     * Функция для получения обратного вызова.
     *
     * @param string $plugin Плагин.
     * @param string $app Приложение.
     * @param mixed $call Вызов.
     * @param array $args Аргументы.
     * @param bool $withGlobalMiddleware Использовать глобальное промежуточное ПО.
     * @param array|null $middlewares
     * @return callable|Closure Возвращает обратный вызов.
     */
    public static function getCallback(string $plugin, string $app, $call, array $args = [], bool $withGlobalMiddleware = true, ?array $middlewares = [])
    {
        $plugin ??= '';
        $isController = is_array($call) && is_string($call[0]);
        $container = static::container($plugin) ?? static::container();
        $middlewares = array_merge(
            $middlewares,
            Middleware::getMiddleware($plugin, $app, $isController ? $call[0] : '', $withGlobalMiddleware)
        );

        // Создаем экземпляры промежуточного ПО
        foreach ($middlewares as $key => $item) {
            $middleware = $item[0];
            if (is_string($middleware)) {
                $middleware = $container->get($middleware);
            } elseif ($middleware instanceof Closure) {
                $middleware = call_user_func($middleware, $container);
            }
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException('Неподдерживаемый тип middleware');
            }
            $middlewares[$key][0] = $middleware;
        }

        // Проверяем, нужно ли внедрять зависимости в вызов
        $needInject = static::isNeedInject($call, $args);
        $anonymousArgs = array_values($args);
        if ($isController) {
            $controllerReuse = static::config($plugin, 'app.controller_reuse', true);
            if (!$controllerReuse) {
                if ($needInject) {
                    $call = function ($request) use ($call, $plugin, $args, $container) {
                        $call[0] = $container->make($call[0]);
                        $reflector = static::getReflector($call);
                        $args = array_values(static::resolveMethodDependencies($container, $request, array_merge($request->all(), $args), $reflector, static::config($plugin, 'app.debug')));
                        return $call(...$args);
                    };
                    $needInject = false;
                } else {
                    $call = function ($request, ...$anonymousArgs) use ($call, $plugin, $container) {
                        $call[0] = $container->make($call[0]);
                        return $call($request, ...$anonymousArgs);
                    };
                }
            } else {
                $call[0] = $container->get($call[0]);
            }
        }

        // Если нужно внедрить зависимости, внедряем их
        if ($needInject) {
            $call = static::resolveInject($plugin, $call, $args);
        }

        $callback = function ($request) use ($call, $anonymousArgs) {
            try {
                $response = $anonymousArgs ? $call($request, ...$anonymousArgs) : $call($request);
            } catch (Throwable $e) {
                return static::exceptionResponse($e, $request);
            }

            return $response instanceof Response ? $response : new Response(200, [], static::stringify($response));
        };

        return $middlewares ? array_reduce($middlewares, function ($carry, $pipe) {
            return function ($request) use ($carry, $pipe) {
                try {
                    return $pipe($request, $carry);
                } catch (Throwable $e) {
                    return static::exceptionResponse($e, $request);
                }
            };
        }, $callback) : $callback;
    }

    /**
     * @param TcpConnection|mixed $connection
     * @param mixed $response
     * @param Request|mixed $request
     * @return void
     * @throws Throwable
     */
    protected static function send(mixed $connection, mixed $response, mixed $request): void
    {
        $keepAlive = $request->header('connection');
        Context::destroy();
        if ((
                $keepAlive === null
                && $request->protocolVersion() === '1.1'
            )
            || $keepAlive === 'keep-alive' || $keepAlive === 'Keep-Alive'
            || (
                is_a($response, Response::class)
                && $response->getHeader('Transfer-Encoding') === 'chunked'
            )
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }


    /**
     * @param string|null $plugin
     * @param int $status
     * @return Closure
     */
    protected static function getFallback(?string $plugin = '', int $status = 404): Closure
    {
        $fallback = Router::getFallback($plugin ?? '', $status);
        if (!$fallback) {
            Router::fallback(fn() => not_found(), $plugin);
            $fallback = Router::getFallback($plugin ?? '', $status);
        }

        return $fallback;
    }

    /**
     * Функция для поиска файла.
     *
     * @param TcpConnection $connection Соединение TCP.
     * @param string $path Путь.
     * @param string $key Ключ.
     * @param mixed $request Запрос.
     * @return bool Возвращает true, если файл найден, иначе возвращает false.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws Throwable
     */
    protected static function findFile(TcpConnection $connection, string $path, string $key, mixed $request): bool
    {
        // Если в пути есть процентное кодирование, декодируем его
        if (preg_match('/%[0-9a-f]{2}/i', $path)) {
            $path = urldecode($path);
            if (static::unsafeUri($path)) {
                $callback = static::getFallback(status: 422);
                $request->plugin = $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request, 422), $request);
                return true;
            }
        }

        // Разбиваем путь на части
        $pathExplodes = explode('/', trim($path, '/'));
        $plugin = '';

        // Если путь указывает на плагин
        if (isset($pathExplodes[1]) && $pathExplodes[0] === config('app.plugin_uri', 'app')) {
            $plugin = $pathExplodes[1];
            $publicDir = static::config($plugin, 'app.public_path') ?: Path::basePath(config('app.plugin_alias', 'plugin') . "/$plugin/public");
            $path = substr($path, strlen("/" . config('app.plugin_uri', 'app') . "/$plugin/"));
        } else {
            // Иначе используем общедоступную директорию
            $publicDir = Path::publicPath();
        }

        // Получаем полный путь к файлу
        $file = "$publicDir/$path";

        // Если файл не существует, возвращаем false
        if (!is_file($file)) {
            return false;
        }

        // Если файл является PHP-файлом
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            // Если PHP-файлы не поддерживаются, возвращаем false
            if (!static::config($plugin, 'app.support_php_files', false)) {
                return false;
            }

            // Добавляем обратный вызов для выполнения PHP-файла
            static::collectCallbacks($key, [function () use ($file) {
                return static::execPhpFile($file);
            }, '', '', '', '', null]);

            // Получаем обратные вызовы
            static::getCallbacks($key, $request);

            // Отправляем обратный вызов
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }

        // Если статические файлы не поддерживаются, возвращаем false
        if (!static::config($plugin, 'static.enable', false)) {
            return false;
        }

        // Добавляем обратный вызов для отправки файла
        static::collectCallbacks($key, [static::getCallback($plugin, '__static__', function ($request) use ($file, $plugin) {
            clearstatcache(true, $file);
            if (!is_file($file)) {
                $callback = static::getFallback($plugin);
                return $callback($request);
            }
            return (new Response())->file($file);
        }, withGlobalMiddleware: false), '', '', '', '', null]);
        // Получаем обратные вызовы
        $callback = static::getCallbacks($key, $request);
        // Отправляем обратный вызов
        static::send($connection, $callback($request), $request);
        return true;
    }

    /**
     * Функция для поиска маршрута.
     *
     * @param TcpConnection $connection Соединение TCP.
     * @param string $path Путь.
     * @param string $key Ключ.
     * @param mixed $request Запрос.
     * @param int $status Статус.
     * @return bool Возвращает true, если маршрут найден, иначе возвращает false.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws Throwable
     */
    protected static function findRoute(TcpConnection $connection, string $path, string $key, mixed $request, int &$status = 200): null|callable|Closure
    {
        // Получаем информацию о маршруте
        $middlewares = [];
        $routeInfo = Router::dispatch($request->method(), $path);
        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                $routeInfo[0] = 'route';
                $callback = $routeInfo[1];
                $args = !empty($routeInfo[2]) ? $routeInfo[2] : [];
                $route = clone $routeInfo[3];
                $app = $controller = $action = '';

                // Установка параметров маршрута, если они есть
                if ($args) {
                    $route->setParams($args);
                }

                // Получение middleware для маршрута
                foreach ($route->getMiddleware() as $className) {
                    $middlewares[] = [$className, 'process'];
                }

                // Если обратный вызов - это массив
                if (is_array($callback)) {
                    $controller = $callback[0];
                    $plugin = Plugin::app_by_class($controller);
                    $app = static::getAppByController($controller);
                    $action = static::getRealMethod($controller, $callback[1]) ?? '';
                } else {
                    // Иначе получаем плагин по пути
                    $plugin = Plugin::app_by_path($path);
                }

                // Получаем обратный вызов
                $callback = static::getCallback($plugin, $app, $callback, $args, true, $middlewares);
                // Собираем обратные вызовы
                static::collectCallbacks($key, [$callback, $plugin, $app, $controller ?: '', $action, $route]);
                // Получаем обратные вызовы
                return static::getCallbacks($key, $request);
        }
        $status = $routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED ? 405 : 404;
        // Если маршрут не найден, возвращаем false
        return false;
    }
}
