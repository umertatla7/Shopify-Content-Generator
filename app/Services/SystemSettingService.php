<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingService
{
    public function get(string $key, mixed $fallback = null): mixed
    {
        $value = $this->storedValue($key);

        return filled($value) ? $value : $fallback;
    }

    public function configured(string $key, mixed $fallback = null): bool
    {
        return filled($this->get($key, $fallback));
    }

    public function source(string $key, mixed $fallback = null): string
    {
        if (filled($this->storedValue($key))) {
            return 'admin';
        }

        return filled($fallback) ? 'env' : 'missing';
    }

    public function set(string $key, mixed $value, bool $isSecret = true, array $metadata = []): SystemSetting
    {
        return SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'is_secret' => $isSecret,
                'metadata' => $metadata ?: null,
            ]
        );
    }

    private function storedValue(string $key): mixed
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return null;
            }

            return SystemSetting::query()->where('key', $key)->value('value');
        } catch (Throwable) {
            return null;
        }
    }
}
