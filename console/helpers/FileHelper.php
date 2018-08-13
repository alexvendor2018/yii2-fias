<?php

namespace alexvendor2018\fias\console\helpers;

use yii\base\InvalidConfigException;

class FileHelper
{
    public static function ensureIsReadable($path)
    {
        if (!is_readable($path)) {
            throw new InvalidConfigException('Путь недоступен для чтения: ' . $path);
        }
    }

    public static function ensureIsWritable($path)
    {
        if (!is_writable($path)) {
            throw new InvalidConfigException('Путь недоступен для записи: ' . $path);
        }
    }

    public static function ensureIsDirectory($path)
    {
        if (!is_dir($path)) {
            throw new InvalidConfigException('Не является директорией: ' . $path);
        }
    }
}
