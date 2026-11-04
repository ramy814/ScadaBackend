<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Sign-out is an Inertia (XHR) visit from the shell; the SSO package answers with a redirect to
 * the SSO server on another host, which only a full browser navigation can follow.
 */
class SignOutTest extends TestCase
{
    public function test_inertia_sign_out_becomes_a_full_page_navigation(): void
    {
        $this->actingAsUser(5, ['fleet']);

        $this->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));

        $this->assertGuest('admin');
    }

    public function test_plain_form_sign_out_still_redirects(): void
    {
        $this->actingAsUser(5, ['fleet']);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_sso_logout_redirect_to_another_host_is_followed_by_the_browser(): void
    {
        eval(<<<'PHP'
            namespace Mog\ProjectHelper;
            class AuthHelpers {
                public static function logout($request, string $guard) {
                    \Illuminate\Support\Facades\Auth::guard($guard)->logout();
                    return redirect()->away('https://sso.example.test/logout?app=mog');
                }
            }
        PHP);
        $this->actingAsUser(5, ['fleet']);

        $this->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://sso.example.test/logout?app=mog');
    }
}
