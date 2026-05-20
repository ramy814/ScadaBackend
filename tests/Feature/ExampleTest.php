<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_login_route_redirects_to_sso(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(302); // SSO login performs redirect
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }
}


