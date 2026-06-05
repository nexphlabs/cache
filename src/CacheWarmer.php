<?php
namespace Nexph\Cache;

use Nexph\Cache\Stores\ApcuStore;
use Nexph\Support\Config;

class CacheWarmer
{
    public static function warm(): array
    {
        $stats = [
            'apcu_enabled' => ApcuStore::enabled(),
            'warmed' => [],
            'errors' => []
        ];

        if (!ApcuStore::enabled()) {
            return $stats;
        }

        try {
            $metaPath = __DIR__ . '/../../metadata';
            if (is_dir($metaPath)) {
                $files = glob($metaPath . '/*.json');
                foreach ($files as $file) {
                    $name = basename($file, '.json');
                    try {
                        $data = json_decode(file_get_contents($file), true);
                        if ($data) {
                            ApcuStore::set("metadata:{$name}", $data, 3600);
                            $stats['warmed'][] = "metadata:{$name}";
                        }
                    } catch (\Exception $e) {
                        $stats['errors'][] = "metadata:{$name} - {$e->getMessage()}";
                    }
                }
            }

            $stats['warmed'][] = 'metadata:all';

            $configPath = __DIR__ . '/../../config/app.php';
            if (file_exists($configPath)) {
                try {
                    Config::load($configPath);
                    $stats['warmed'][] = 'config:app';
                } catch (\Exception $e) {
                    $stats['errors'][] = "config:app - {$e->getMessage()}";
                }
            }

            $apiPolicyPath = __DIR__ . '/../../config/api.json';
            if (file_exists($apiPolicyPath)) {
                try {
                    \Nexph\Http\ApiPolicy::fromFile($apiPolicyPath);
                    $stats['warmed'][] = 'apipolicy';
                } catch (\Exception $e) {
                    $stats['errors'][] = "apipolicy - {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $stats['errors'][] = "warmup - {$e->getMessage()}";
        }

        return $stats;
    }

    public static function clear(): bool
    {
        ApcuStore::delete('nexph:config:app');
        ApcuStore::delete('nexph:apipolicy');
        ApcuStore::delete('nexph:routes');
        return ApcuStore::clear();
    }
}
