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

    public function test_root_route_redirects_to_dashboard(): void
    {
        $response = $this->get('/');
        $response->dump();
        $response->assertRedirect(route('login'));
    }

    public function test_sso_callback_redirects_dev_state(): void
    {
        // Force app.env to be production-like for this test to trigger the dev_ redirect
        config(['app.env' => 'production']);

        $response = $this->get('/auth/sso/callback?token=495295cd-bf0c-43a1-94f6-74eabbcf18e0&state=dev_94ebce5f-43a1-4242-8af5-6c622bec5298');
        
        $response->assertRedirect('http://127.0.0.1:8000/auth/sso/callback?token=495295cd-bf0c-43a1-94f6-74eabbcf18e0&state=dev_94ebce5f-43a1-4242-8af5-6c622bec5298');
    }
}


