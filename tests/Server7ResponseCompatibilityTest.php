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

require getenv('ENGINE_HTTP_VENDOR_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

use localzet\Server\Protocols\Http\Response as ServerResponse;
use Triangle\Engine\Config;
use Triangle\Engine\Response as EngineResponse;
use Triangle\Http\App;

$normalizeResponse = new ReflectionMethod(App::class, 'normalizeResponse');
$serverResponse = new ServerResponse(201, ['X-Test' => 'preserved'], 'response body');
$normalizedResponse = $normalizeResponse->invoke(null, $serverResponse);

if ($normalizedResponse !== $serverResponse) {
    throw new RuntimeException('A Server HTTP response must pass through unchanged.');
}

if (!$normalizedResponse instanceof ServerResponse) {
    throw new RuntimeException('The callback result must remain an HTTP response object.');
}

$normalizedValue = $normalizeResponse->invoke(null, 'plain body');
if (!$normalizedValue instanceof EngineResponse) {
    throw new RuntimeException('A plain callback value must be wrapped in an Engine response.');
}

Config::set(['app' => require dirname(__DIR__) . '/src/Config/app.php']);
$configuredResponse = new EngineResponse();
if ($configuredResponse->getHeader('Content-Language') !== 'ru') {
    throw new RuntimeException('Engine response defaults must use the Engine 9 http_headers setting.');
}

if ($configuredResponse->getHeader('Access-Control-Allow-Origin') !== null) {
    throw new RuntimeException('CORS origins must be explicitly configured by the application.');
}

fwrite(STDOUT, "Server HTTP response compatibility passed.\n");
