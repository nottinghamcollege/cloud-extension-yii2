<?php

namespace craft\cloud\runtime\variables;

class VariablesLoader
{
    public const FILENAME = '/var/task/.env.php';

    public static function load(): void
    {
        if (!file_exists(self::FILENAME)) {
            return;
        }

        $vars = include self::FILENAME;

        if (!is_array($vars)) {
            return;
        }

        foreach ($vars as $key => $value) {
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
