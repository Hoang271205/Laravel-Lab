<?php

namespace Tests\Feature;

use App\Exceptions\OrderCannotProcessException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Sanctum::actingAs(User::factory()->create(), ['*']);
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        $r = $this->get('/api/does-not-exist')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertTrue(Str::isUuid($r->json('error.request_id')));
        $r->assertHeader('X-Request-ID', $r->json('error.request_id'));
    }

    public function test_business_error_is_actionable_and_reported_with_context(): void
    {
        Log::spy();
        $r = $this->get('/api/section3/business-error')->assertStatus(409)->assertJsonPath('error.code', 'ORDER_CANNOT_PROCESS');
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $context['order_id'] === 42 && $context['request_id'] === $r->json('error.request_id'));
    }

    public function test_server_error_hides_secrets_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        $r = $this->get('/api/section3/server-error')->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $this->assertStringNotContainsString('never-expose', $r->getContent());
        $this->assertStringNotContainsString('trace', $r->getContent());
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
            $data = json_encode([$message, $context]);

            return $context['exception_type'] === \RuntimeException::class && ! str_contains($data, 'never-expose') && ! isset($context['exception']);
        });
    }

    public function test_web_error_is_html_with_tracking_id(): void
    {
        $r = $this->get('/section3/server-error')->assertStatus(500)->assertSee('INTERNAL_ERROR')->assertDontSee('never-expose');
        $this->assertTrue(Str::isUuid($r->headers->get('X-Request-ID')));
        $r->assertSee($r->headers->get('X-Request-ID'));
    }

    public function test_web_business_error_and_json_accept_negotiation(): void
    {
        $this->get('/section3/business-error')->assertStatus(409)->assertSee('ORDER_CANNOT_PROCESS');
        $this->getJson('/section3/business-error')->assertStatus(409)->assertJsonPath('error.code', 'ORDER_CANNOT_PROCESS');
    }

    public function test_validation_returns_field_names_without_submitted_secrets(): void
    {
        $this->postJson('/api/section3/validate', ['password' => 'sensitive-value'])
            ->assertUnprocessable()->assertJsonPath('error.fields', ['name'])->assertDontSee('sensitive-value');
        $this->postJson('/api/section3/validate', ['name' => 'Coffee'])->assertOk()->assertJsonPath('data.name', 'Coffee');
    }

    public function test_ids_are_generated_and_differ_across_requests(): void
    {
        $a = $this->withHeader('X-Request-ID', 'untrusted-value')->get('/api/section3/log')->assertOk();
        $b = $this->get('/api/section3/log')->assertOk();
        $this->assertTrue(Str::isUuid($a->json('request_id')));
        $this->assertNotSame($a->json('request_id'), $b->json('request_id'));
        $a->assertHeader('X-Request-ID', $a->json('request_id'));
    }

    public function test_http_exception_headers_and_status_are_preserved(): void
    {
        Route::get('/api/test-rate-limit', fn () => abort(429, 'secret', ['Retry-After' => '60']));
        $this->get('/api/test-rate-limit')->assertStatus(429)->assertHeader('Retry-After', '60')->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')->assertDontSee('secret');
        $this->postJson('/api/section3/log')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_exception_instance_is_not_reported_twice(): void
    {
        Log::spy();
        $e = new OrderCannotProcessException(42);
        report($e);
        report($e);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_demo_routes_are_disabled_in_production(): void
    {
        $this->app['env'] = 'production';
        // Evaluate route files in production, rather than reusing already-loaded testing routes.
        $routes = new RouteCollection;
        Route::setRoutes($routes);
        require base_path('routes/api.php');
        require base_path('routes/web.php');
        foreach ($routes as $route) {
            $this->assertStringNotContainsString('section3', $route->uri());
        }
    }

    public function test_telescope_gate_denies_non_local_users(): void
    {
        $this->assertFalse(Gate::allows('viewTelescope', new User));
    }
}
