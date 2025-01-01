<?php declare(strict_types=1);

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

namespace Triangle\Http;

/**
 * Класс Install
 * Этот класс предназначен для установки и обновления плагина.
 */
class Install
{
    public const TRIANGLE_PLUGIN = true;

    /**
     * Установка плагина
     */
    public static function install(): void
    {
        if (!self::TRIANGLE_PLUGIN) {
            return;
        }

        $sources = [__DIR__ . "/Config" => config_path()];

        foreach ($sources as $source => $target) {
            if (is_dir($source) && !empty($sourceFiles = glob($source . "/*.php"))) {
                foreach ($sourceFiles as $sourceFile) {
                    $path = path_combine($target, str_replace($source, "", $sourceFile));
                    if (!file_exists($path)) {
                        copy_dir($sourceFile, $path);
                        echo "Создан $path\r\n";
                    }
                }
            }
        }
    }
}
