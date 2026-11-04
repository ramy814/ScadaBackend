<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaRootViewTest extends TestCase
{
    public function test_inertia_root_view_renders_app_container_and_assets(): void
    {
        $html = view('app', [
            'page' => [
                'component' => 'Portal',
                'props' => [],
                'url' => '/',
                'version' => null,
            ],
        ])->render();

        $this->assertStringContainsString('id="app"', $html);
        $this->assertStringContainsString('data-page=', $html);
        $this->assertStringContainsString('/build/assets/app-', $html);
    }
}
