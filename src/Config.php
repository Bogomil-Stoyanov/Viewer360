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
            'audio' => [
                'max_size' => 15 * 1024 * 1024, // 15MB in bytes
                'allowed_types' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/x-wav'],
                'allowed_extensions' => ['mp3', 'wav', 'ogg'],
                'upload_dir' => __DIR__ . '/../public/uploads/audio/'
            ],
            'app' => [
                'name' => 'Viewer360',
                'base_url' => self::getBaseUrl()
            ]
        ];
    }

    private static function getBaseUrl(): string
    {
        // Check if BASE_URL is set in environment
        $baseUrl = getenv('BASE_URL');
        if ($baseUrl !== false) {
            return rtrim($baseUrl, '/');
        }

        // Auto-detect base URL based on script location
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        // If running from public directory, remove 'public' from path
        if (basename($basePath) === 'public') {
            $basePath = dirname($basePath);
        }
        
        // Ensure it starts with / and doesn't end with /
        $basePath = '/' . trim($basePath, '/');
        
        return $basePath === '/' ? '' : $basePath . '/public';
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
    
    public static function url(string $path = ''): string
    {
        $baseUrl = self::get('app.base_url', '');
        $path = ltrim($path, '/');
        return $baseUrl . ($path ? '/' . $path : '');
    }
}
