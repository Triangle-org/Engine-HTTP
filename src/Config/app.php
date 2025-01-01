<?php

/**
 * @package     Triangle HTTP Component
 * @link        https://github.com/Triangle-org/Http
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2025 Triangle Framework Team
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

declare(strict_types=1);

return [
    'debug' => (bool)env('APP_DEBUG', false),
    'name' => env('APP_NAME', 'Triangle App'),

    'plugin_alias' => env('APP_PLUGIN_ALIAS', 'plugin'),
    'plugin_uri' => env('APP_PLUGIN_URI', 'app'),

    'controller_suffix' => env('CONTROLLER_SUFFIX', ''),
    'controller_reuse' => env('CONTROLLER_REUSE', true),

    'headers' => [
        'Content-Language' => 'ru',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Methods' => '*',
        'Access-Control-Allow-Headers' => '*',
        'X-Powered-By' => 'Triangle-Core/' . Composer\InstalledVersions::getVersion('triangle/engine'),
    ],
];
