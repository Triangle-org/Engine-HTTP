<?php

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

use localzet\Server;
use localzet\Server\Connection\TcpConnection;
use Triangle\Http\App;
use Triangle\Http\Request;
use Triangle\Http\Response;

if (!function_exists('response')) {
    /**
     * @param mixed $body
     * @param int $status
     * @param array $headers
     * @param bool $http_status
     * @param bool $onlyJson
     * @return Response
     * @throws Throwable
     */
    function response(mixed $body = '', int $status = 200, array $headers = [], bool $http_status = false, bool $onlyJson = false): Response
    {
        $status = ($http_status === true) ? $status : 200;
        $body = [
            'status' => $status,
            'data' => $body
        ];

        if (config('app.debug')) {
            $body['debug'] = config('app.debug');
        }

        if (!function_exists('responseView') || request()->expectsJson() || $onlyJson) {
            return responseJson($body, $status, $headers);
        } else {
            return responseView($body, $status, $headers);
        }
    }
}

/**
 * @param string $blob
 * @param string $type
 * @return Response
 */
function responseBlob(string $blob, string $type = 'image/png'): Response
{
    return new Response(200, ['Content-Type' => $type], $blob);
}

/**
 * @param $data
 * @param int $status
 * @param array $headers
 * @param int $options
 * @return Response
 */
function responseJson($data, int $status = 200, array $headers = [], int $options = JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR): Response
{
    return new Response($status, ['Content-Type' => 'application/json'] + $headers, json($data, $options));
}

/**
 * @param string $location
 * @param int $status
 * @param array $headers
 * @return Response
 */
function redirect(string $location, int $status = 302, array $headers = []): Response
{
    $response = new Response($status, ['Location' => $location]);
    if (!empty($headers)) {
        $response->withHeaders($headers);
    }
    return $response;
}

if (!function_exists('not_found')) {
    /**
     * @return Response
     * @throws Throwable
     */
    function not_found(): Response
    {
        return response('Ничего не найдено', 404);
    }
}

if (!function_exists('jsonp')) {
    /**
     * @param $data
     * @param string $callbackName
     * @return Response
     */
    function jsonp($data, string $callbackName = 'callback'): Response
    {
        if (!is_scalar($data) && null !== $data) {
            $data = json_encode($data);
        }
        return new Response(200, [], "$callbackName($data)");
    }
}

if (!function_exists('connection')) {
    /**
     * @return TcpConnection|null
     */
    function connection(): ?TcpConnection
    {
        return App::connection();
    }
}

if (!function_exists('request')) {
    /**
     * @return Request
     */
    function request(): Request
    {
        return App::request();
    }
}

if (!function_exists('server')) {
    /**
     * @return Server|null
     */
    function server(): ?Server
    {
        return App::server();
    }
}