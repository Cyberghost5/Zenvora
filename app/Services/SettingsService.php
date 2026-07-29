<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to the `settings` table with a cached read path.
 *
 * Settings are read on nearly every request (deposit bounds, withdrawal window,
 * referral rates), so the whole table is cached as one array and the cache is
 * dropped on any write.
 */
class SettingsService
{
    private const CACHE_KEY = 'zenvora.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        return $this->loaded = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all) && $all[$key] !== null) {
            return $all[$key];
        }

        return $fallback ?? config("zenvora.defaults.{$key}");
    }

    public function integer(string $key, int $fallback = 0): int
    {
        return (int) $this->get($key, $fallback);
    }

    public function boolean(string $key, bool $fallback = false): bool
    {
        $value = $this->get($key, $fallback);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    public function string(string $key, string $fallback = ''): string
    {
        return (string) ($this->get($key, $fallback) ?? $fallback);
    }

    /**
     * @return array<int, mixed>
     */
    public function array(string $key, array $fallback = []): array
    {
        $value = $this->get($key, $fallback);

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $fallback;
        }

        return is_array($value) ? $value : $fallback;
    }

    /**
     * Settings that hold money are stored as minor units, same as every other
     * amount in the system.
     */
    public function money(string $key, int $fallbackMinor = 0): Money
    {
        return Money::fromMinor($this->integer($key, $fallbackMinor));
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        if (is_array($value)) {
            $type = 'json';
            $value = json_encode(array_values($value));
        } elseif (is_bool($value)) {
            $type = 'boolean';
            $value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $type = 'integer';
            $value = (string) $value;
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
