<?php

namespace App;

class Config
{
    private static array $config = [];

    public static function init(): void
    {
        self::$config = [
            'db' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'name' => getenv('DB_NAME') ?: 'viewer360',
                'user' => getenv('DB_USER') ?: 'viewer360_user',
                'pass' => getenv('DB_PASS') ?: 'viewer360_pass',
                'charset' => 'utf8mb4'
            ],
            'upload' => [
                'max_size' => 50 * 1024 * 1024, // 50MB in bytes
                'allowed_types' => ['image/jpeg', 'image/png'],
                'allowed_extensions' => ['jpg', 'jpeg', 'png'],
                'upload_dir' => __DIR__ . '/../public/uploads/'
            ],
            'app' => [
                'name' => 'Viewer360',
                'base_url' => '/'
            ]
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (empty(self::$config)) {
            self::init();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
