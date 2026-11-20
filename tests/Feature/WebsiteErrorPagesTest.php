<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebsiteErrorPagesTest extends TestCase
{
    public function test_shared_web_request_overlay_has_loading_and_safe_recovery_copy(): void
    {
        $html = view('components.request-loading-overlay')->render();

        $this->assertStringContainsString('Loading...', $html);
        $this->assertStringNotContainsString('Please wait while we load your page.', $html);
        $this->assertStringContainsString('Unable to Load Page', $html);
        $this->assertStringContainsString('Something went wrong while loading this page.', $html);
        $this->assertStringContainsString('class="kjm-request-error" hidden', $html);
        $this->assertStringContainsString('data-kjm-refresh', $html);
        $this->assertStringContainsString('data-kjm-back', $html);
        $this->assertStringNotContainsString('30000', $html);
        $this->assertStringContainsString('kjm-request-logo', $html);
        $this->assertStringContainsString('logo1.png', $html);
        $this->assertStringContainsString('kjm-logo-rotate', $html);
        $this->assertStringContainsString('linear infinite', $html);
        $this->assertStringNotContainsString('kjm-logo-pulse', $html);
        $this->assertStringNotContainsString("document.addEventListener('click'", $html);
        $this->assertStringNotContainsString("document.addEventListener('submit'", $html);
        $this->assertStringContainsString("document.body.dataset.kjmErrorRecovery==='admin'", $html);
        $this->assertStringContainsString('window.fetch=(input,init)=>', $html);
        $this->assertStringContainsString('const retryDelays=[500,1500]', $html);
        $this->assertStringContainsString('response.status===422||!handledStatuses.has(response.status)', $html);
        $this->assertStringContainsString('window.addEventListener(\'online\'', $html);
        $this->assertStringContainsString('window.kjmStartRequestLoading=start', $html);
        $this->assertStringNotContainsString('kjm-request-content{background', $html);
    }

    public function test_laravel_renders_friendly_pages_with_the_original_http_status(): void
    {
        foreach ([400, 401, 403, 404, 405, 408, 409, 419, 422, 429, 500, 502, 503, 504] as $status) {
            Route::get("/_test/website-error/{$status}", function () use ($status) {
                abort($status, '', $status === 429 ? ['Retry-After' => '45'] : []);
            });
            $response = $this->get("/_test/website-error/{$status}");
            $html = $response->assertStatus($status)
                ->assertSee((string) $status)
                ->assertSee('KJM Coffee Shop')
                ->assertSee('Unable to Load Page')
                ->assertSee('Something went wrong while loading this page. Would you like to try again?')
                ->assertSee('>Refresh</button>', false)
                ->assertSee('>Go Back</button>', false)
                ->assertSee('error-mark', false)
                ->assertDontSee('error-spinner', false)
                ->assertDontSee('kjm-error-spin', false)
                ->assertDontSee('Additional recovery actions', false)
                ->assertDontSee('status-heading', false)
                ->assertDontSee('status-description', false)
                ->getContent();
            $this->assertSame(2, substr_count($html, 'class="button'));
        }
    }

    public function test_http_429_status_remains_visible_on_the_generic_error_page(): void
    {
        Route::get('/_test/website-error/retry-after', fn () => abort(429, '', ['Retry-After' => '45']));
        $this->get('/_test/website-error/retry-after')->assertStatus(429)->assertSee('>429</span>', false);
    }

    public function test_unexpected_browser_exception_is_generic_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/_test/website-error/unexpected', function () {
            throw new \RuntimeException('private SQL details and file path');
        });
        $this->get('/_test/website-error/unexpected')
            ->assertStatus(500)->assertSee('Unable to Load Page')
            ->assertDontSee('private SQL details and file path');
    }

    public function test_refresh_on_a_post_error_uses_a_safe_page_and_never_replays_the_post(): void
    {
        Route::post('/_test/website-error/failed-post', fn () => abort(500));

        $html = $this->from('/login')->post('/_test/website-error/failed-post')
            ->assertStatus(500)
            ->assertSee('failedRequestMethod === \'GET\'', false)
            ->assertSee('safePreviousPage() || event.currentTarget.dataset.fallbackUrl', false)
            ->assertSee('window.kjmStartRequestLoading?.()', false)
            ->getContent();

        $refreshStart = strpos($html, "document.getElementById('error-refresh')");
        $refreshScript = substr($html, $refreshStart);
        $loadingPosition = strpos($refreshScript, 'window.kjmStartRequestLoading?.()');
        $navigationPosition = strpos($refreshScript, 'window.location.assign');
        $this->assertNotFalse($refreshStart);
        $this->assertNotFalse($loadingPosition);
        $this->assertNotFalse($navigationPosition);
        $this->assertLessThan($navigationPosition, $loadingPosition);
        $this->assertStringNotContainsString('form.submit()', $refreshScript);
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
                ->assertStatus(403)->assertSee('data-fallback-url="'.$expectedUrl.'"', false);
        }
    }

    public function test_login_and_dashboard_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertTrue(Route::has('staff.home'));
        $this->get(route('login'))->assertOk()->assertSee('kjm-request-overlay', false);
    }

    public function test_admin_layout_scopes_error_recovery_to_failed_requests_not_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('<body data-kjm-error-recovery="admin">', $layout);
        $this->assertStringNotContainsString("document.addEventListener('click'", $layout);
        $this->assertStringNotContainsString("document.addEventListener('submit'", $layout);
    }

    public function test_standalone_auth_pages_show_the_shared_loading_overlay(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Loading...', false)
            ->assertSee('kjm-request-logo', false);

        $this->get(route('password.reset', ['token' => 'test-token']))
            ->assertOk()
            ->assertSee('kjm-request-overlay', false);
    }

    public function test_final_server_error_page_keeps_loading_overlay_hidden_until_refresh(): void
    {
        Route::get('/_test/website-error/final-no-spinner', fn () => abort(404));

        $this->get('/_test/website-error/final-no-spinner')
            ->assertStatus(404)
            ->assertSee('Unable to Load Page')
            ->assertSee('id="kjm-request-overlay" class="kjm-request-overlay" hidden', false)
            ->assertSee('aria-busy="false"', false)
            ->assertSee('window.kjmStartRequestLoading?.()', false)
            ->assertSee('>Refresh</button>', false)
            ->assertSee('>Go Back</button>', false);
    }

    public function test_api_http_errors_keep_the_api_json_envelope_and_status(): void
    {
        foreach ([400, 401, 403, 404, 405, 408, 409, 419, 422, 429, 500, 502, 503, 504] as $status) {
            Route::get("/api/_test/api-error/{$status}", function () use ($status) {
                abort($status, 'internal exception text should not replace the safe message');
            });

            $this->getJson("/api/_test/api-error/{$status}")
                ->assertStatus($status)
                ->assertJsonStructure(['success', 'message', 'data', 'errors'])
                ->assertJsonPath('success', false)
                ->assertJsonPath('data', null)
                ->assertDontSee('internal exception text should not replace the safe message');
        }
    }

    public function test_api_validation_errors_include_field_errors_without_html(): void
    {
        Route::post('/api/_test/api-validation', function (Request $request) {
            $request->validate(['name' => ['required', 'string']]);
            return response()->json(['success' => true]);
        });

        $this->postJson('/api/_test/api-validation', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_database_connection_failure_is_reported_as_a_safe_503_for_api(): void
    {
        Route::get('/api/_test/database-unavailable', function () {
            throw new LostConnectionException('private database host and credentials');
        });

        $this->getJson('/api/_test/database-unavailable')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertDontSee('private database host and credentials');
    }

    public function test_query_constraint_failure_is_not_misreported_as_a_database_outage(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/_test/database-constraint', function () {
            throw new QueryException(
                'mysql',
                'insert into payments (secret_column) values (?)',
                ['private value'],
                new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 duplicate entry'),
            );
        });

        $this->getJson('/api/_test/database-constraint')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Something went wrong on our side. Please try again later.')
            ->assertDontSee('insert into payments')
            ->assertDontSee('private value')
            ->assertDontSee('Integrity constraint violation');
    }

    public function test_wrapped_lost_connection_query_exception_is_reported_as_safe_503(): void
    {
        Route::get('/api/_test/wrapped-database-connection', function () {
            throw new QueryException(
                'mysql',
                'select * from users',
                [],
                new \PDOException('SQLSTATE[HY000] [2002] Connection refused: private database host'),
            );
        });

        $this->getJson('/api/_test/wrapped-database-connection')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'KJM Coffee Shop is temporarily unavailable. Please try again shortly.')
            ->assertDontSee('select * from users')
            ->assertDontSee('private database host');
    }

    public function test_unexpected_api_exception_hides_debug_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/_test/unexpected', function () {
            throw new \RuntimeException('private SQL details and file path');
        });

        $this->getJson('/api/_test/unexpected')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertDontSee('private SQL details and file path');
    }
}
