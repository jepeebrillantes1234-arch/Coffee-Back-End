<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebsiteErrorPagesTest extends TestCase
{
    public function test_laravel_renders_friendly_pages_with_the_original_http_status(): void
    {
        foreach ([404, 403, 419, 429, 500, 503] as $status) {
            Route::get("/_test/website-error/{$status}", function () use ($status) {
                abort($status, '', $status === 429 ? ['Retry-After' => '45'] : []);
            });
            $this->get("/_test/website-error/{$status}")
                ->assertStatus($status)
                ->assertSee((string) $status)
                ->assertSee('KJM Coffee Shop');
        }
    }

    public function test_http_429_explains_retry_after(): void
    {
        Route::get('/_test/website-error/retry-after', fn () => abort(429, '', ['Retry-After' => '45']));
        $this->get('/_test/website-error/retry-after')->assertStatus(429)->assertSee('45 seconds');
    }

    public function test_unexpected_browser_exception_is_generic_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/_test/website-error/unexpected', function () {
            throw new \RuntimeException('private SQL details and file path');
        });
        $this->get('/_test/website-error/unexpected')
            ->assertStatus(500)->assertSee('We are sorry')
            ->assertDontSee('private SQL details and file path');
    }

    public function test_forbidden_recovery_never_links_to_another_role_dashboard(): void
    {
        foreach ([
            'admin' => route('admin.dashboard'),
            'staff' => route('staff.home'),
            'customer' => route('login'),
        ] as $role => $expectedUrl) {
            Route::get("/_test/website-error/403/{$role}", function () use ($role) {
                Auth::setUser(new User(['role' => $role, 'status' => 'active']));
                abort(403);
            });
            $this->get("/_test/website-error/403/{$role}")
                ->assertStatus(403)->assertSee('href="'.$expectedUrl.'"', false);
        }
    }

    public function test_login_and_dashboard_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertTrue(Route::has('staff.home'));
        $this->get(route('login'))->assertOk();
    }
}
