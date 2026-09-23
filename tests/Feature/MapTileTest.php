<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapTileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_requires_an_inspector_token(): void
    {
        $this->get('/api/map/tiles/12/2439/1669.png')->assertUnauthorized();
    }

    public function test_it_proxies_an_allowed_gaza_tile_as_png(): void
    {
        Http::fake([
            'apps.mogaza.org/*' => Http::response(['id' => 1]),
            'geo.mogaza.org/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->withToken('valid-inspector-token')
            ->get('/api/map/tiles/12/2439/1669.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('png-bytes');
    }

    public function test_it_rejects_tiles_outside_the_allowed_coverage(): void
    {
        Http::fake(['apps.mogaza.org/*' => Http::response(['id' => 1])]);

        $this->withToken('valid-inspector-token')
            ->get('/api/map/tiles/18/0/0.png')
            ->assertNotFound();
    }
}
