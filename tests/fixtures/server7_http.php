<?php declare(strict_types=1);

/**
 * @package Triangle HTTP Component
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use localzet\Server;
use localzet\Server\Protocols\Http;
use localzet\Server\Protocols\Http\Response as ServerResponse;
use Monolog\Logger;
use Triangle\Engine\Context;
use Triangle\Engine\Request;
use Triangle\Http\App;

$port = (int)($argv[2] ?? 0);
$runtimeDirectory = (string)($argv[3] ?? '');

if (!function_exists('posix_setpgid') || !posix_setpgid(0, 0) || posix_getpgrp() !== getmypid()) {
    fwrite(STDERR, "Could not isolate the HTTP integration process group.\n");
    exit(1);
}

file_put_contents($runtimeDirectory . '/process-group', (string)posix_getpgrp(), LOCK_EX);

$autoload = getenv('ENGINE_HTTP_VENDOR_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
require $autoload;

Server::$pidFile = $runtimeDirectory . '/server.pid';
Server::$statusFile = $runtimeDirectory . '/server.status';
Server::$stdoutFile = $runtimeDirectory . '/stdout.log';
Server::$logFile = $runtimeDirectory . '/server.log';

Http::requestClass(Request::class);
Context::init();

$app = new App(Request::class, new Logger('engine-http-loopback'));
$callback = App::getCallback(null, '', static fn($request) => new ServerResponse(
    202,
    ['X-Adapter-Test' => 'preserved', 'Connection' => 'close'],
    'response from a base Server 7 HTTP response',
));

$collectCallback = new ReflectionMethod(App::class, 'collectCallbacks');
$collectCallback->setAccessible(true);
$collectCallback->invoke(null, 'GET/server-response', [$callback, '', '', '', '', null]);

$server = new Server("http://127.0.0.1:$port");
$server->count = 1;
$server->onMessage = [$app, 'onMessage'];

Server::runAll();
