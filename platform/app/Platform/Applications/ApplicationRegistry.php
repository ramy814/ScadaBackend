<?php

declare(strict_types=1);

namespace App\Platform\Applications;

use Illuminate\Contracts\Container\Container;

class ApplicationRegistry
{
    public function __construct(private readonly Container $container) {}

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return config('applications', []);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function module(string $key): Module
    {
        return $this->container->make($this->all()[$key]['module']);
    }

    /** Public, locale-resolved description used by the shell and the directory. */
    public function present(string $key, string $locale): array
    {
        $app = $this->get($key);

        return [
            'key' => $key,
            'accent' => $app['accent'],
            'icon' => $app['icon'],
            'name' => $app['name'][$locale] ?? $app['name']['ar'],
            'domain' => $app['domain'][$locale] ?? $app['domain']['ar'],
            'home' => route($app['home']),
        ];
    }
}
