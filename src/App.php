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
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use localzet\Server\Connection\ConnectionInterface;
use localzet\Server\Connection\TcpConnection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Throwable;
use Triangle\Engine\Context;
use Triangle\Engine\Path;
use Triangle\Engine\Plugin;
use Triangle\Engine\Request;
use Triangle\Engine\Response;
use Triangle\Middleware\Bootstrap as Middleware;
use Triangle\Middleware\MiddlewareInterface;
use Triangle\Router;
use Triangle\Router\Dispatcher;
use function array_merge;
use function array_reduce;
use function array_values;
use function clearstatcache;
use function explode;
use function is_array;
use function is_file;
use function is_string;
use function pathinfo;
use function substr;
use function trim;

/**
 * Class App
 */
class App extends \Triangle\Engine\App
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
            Context::set(TcpConnection::class, $connection);
            Context::set(static::$requestClass, $request);

            $path = $request->path();
            $key = $request->method() . $path;

            if (isset(static::$callbacks[$key])) {
                $callback = static::getCallbacks($key, $request);
                static::send($connection, $callback($request), $request);
                return;
            }

            $status = 200;

            if (static::unsafeUri($path)) {
                $callback = static::getFallback(status: 422);
                $request->plugin = $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request, 422), $request);
                return;
            } else if (static::findFile($connection, $path, $key, $request)) {
                return;
            } else if ($callback = static::findRoute($connection, $path, $key, $request, $status)) {
                static::send($connection, $callback($request), $request);
                return;
            }

            $controllerAndAction = static::parseControllerAction($path);
            $plugin = $controllerAndAction['plugin'] ?? Plugin::app_by_path($path);

            if (!$controllerAndAction
                || Router::isDefaultRouteDisabled($plugin, $controllerAndAction['app'] ?: '*')
                || Router::isDefaultRouteDisabled($controllerAndAction['controller'])
                || Router::isDefaultRouteDisabled([$controllerAndAction['controller'], $controllerAndAction['action']])
            ) {
                $request->plugin = $plugin;
                $callback = static::getFallback($plugin, $status);
                $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request, $status), $request);
                return;
            }

            $app = $controllerAndAction['app'];
            $controller = $controllerAndAction['controller'];
            $action = $controllerAndAction['action'];

            $callback = static::getCallback($plugin, $app, [$controller, $action]);
            static::collectCallbacks($key, [$callback, $plugin, $app, $controller, $action, null]);

            $callback = static::getCallbacks($key, $request);
            static::send($connection, $callback($request), $request);
        } catch (Throwable $e) {
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
     * @return callable|Closure Возвращает обратный вызов.
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public static function getCallback(?string $plugin, string $app, $call, array $args = [], bool $withGlobalMiddleware = true, ?array $middlewares = []): callable|Closure
    {
        $plugin ??= '';
        $isController = is_array($call) && is_string($call[0]);
        $container = config('container', plugin: $plugin) ?? config('container');
        $middlewares = array_merge(
            $middlewares,
            Middleware::getMiddleware($plugin, $app, $isController ? $call[0] : '', $withGlobalMiddleware)
        );

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

        $needInject = static::isNeedInject($call, $args);
        $anonymousArgs = array_values($args);
        if ($isController) {
            $controllerReuse = config('app.controller_reuse', true, $plugin);
            if (!$controllerReuse) {
                if ($needInject) {
                    $call = function ($request) use ($call, $plugin, $args, $container) {
                        $call[0] = $container->make($call[0]);
                        $reflector = static::getReflector($call);
                        $args = array_values(static::resolveMethodDependencies($container, $request, array_merge($request->all(), $args), $reflector, config('app.debug', plugin: $plugin)));
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
                try {
                    $call[0] = $container->get($call[0]);
                } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                    throw $e; // TODO
                }
            }
        }

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
        if (($keepAlive === null
                && $request->protocolVersion() === '1.1')
            || $keepAlive === 'keep-alive' || $keepAlive === 'Keep-Alive'
            || ($response instanceof Response
                && $response->getHeader('Transfer-Encoding') === 'chunked')
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
        if (preg_match('/%[0-9a-f]{2}/i', $path)) {
            $path = urldecode($path);
            if (static::unsafeUri($path)) {
                $callback = static::getFallback(status: 422);
                $request->plugin = $request->app = $request->controller = $request->action = '';
                static::send($connection, $callback($request, 422), $request);
                return true;
            }
        }

        $pathExplodes = explode('/', trim($path, '/'));
        $plugin = '';

        if (isset($pathExplodes[1]) && $pathExplodes[0] === config('app.plugin_uri', 'app')) {
            $plugin = $pathExplodes[1];
            $publicDir = config('app.public_path', plugin: $plugin) ?: Path::basePath(config('app.plugin_alias', 'plugin') . "/$plugin/public");
            $path = substr($path, strlen("/" . config('app.plugin_uri', 'app') . "/$plugin/"));
        } else {
            $publicDir = Path::publicPath();
        }

        $file = "$publicDir/$path";
        if (!is_file($file)) {
            return false;
        }

        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            if (!config('app.support_php_files', false, $plugin)) {
                return false;
            }

            static::collectCallbacks($key, [function () use ($file) {
                return static::execPhpFile($file);
            }, '', '', '', '', null]);

            static::getCallbacks($key, $request);
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }

        if (!config('static.enable', false, $plugin)) {
            return false;
        }

        static::collectCallbacks($key, [static::getCallback($plugin, '__static__', function ($request) use ($file, $plugin) {
            clearstatcache(true, $file);
            if (!is_file($file)) {
                $callback = static::getFallback($plugin);
                return $callback($request);
            }
            return (new Response())->file($file);
        }, withGlobalMiddleware: false), '', '', '', '', null]);

        $callback = static::getCallbacks($key, $request);
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
     * @return callable|Closure|null Возвращает true, если маршрут найден, иначе возвращает false.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    protected static function findRoute(TcpConnection $connection, string $path, string $key, mixed $request, int &$status = 200): null|array|false
    {
        $middlewares = [];
        $routeInfo = Router::dispatch($request->method(), $path);
        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                $routeInfo[0] = 'route';
                $callback = $routeInfo[1];
                $args = !empty($routeInfo[2]) ? $routeInfo[2] : [];
                $route = clone $routeInfo[3];
                $app = $controller = $action = '';

                if ($args) $route->setParams($args);

                foreach ($route->getMiddleware() as $className) {
                    $middlewares[] = [$className, 'process'];
                }

                if (is_array($callback)) {
                    $controller = $callback[0];
                    $plugin = Plugin::app_by_class($controller);
                    $app = static::getAppByController($controller);
                    $action = static::getRealMethod($controller, $callback[1]) ?? '';
                } else {
                    $plugin = Plugin::app_by_path($path);
                }

                $callback = static::getCallback($plugin, $app, $callback, $args, true, $middlewares);
                static::collectCallbacks($key, [$callback, $plugin, $app, $controller ?: '', $action, $route]);
                return static::getCallbacks($key, $request);
        }

        $status = $routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED ? 405 : 404;
        return false;
    }
}
