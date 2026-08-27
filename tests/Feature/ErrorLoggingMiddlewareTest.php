<?php

namespace Tests\Feature;

use App\Http\Middleware\ErrorLoggingMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorLoggingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function five_hundred_logs_omit_passwords_and_tokens(): void
    {
        $middleware = new ErrorLoggingMiddleware;
        $request = Request::create('/__test-500', 'POST', [
            'password' => 'super-secret',
            'name' => 'visitor',
            '_token' => 'abc',
        ]);

        $context = $middleware->context($request);
        $payload = json_encode($context);

        $this->assertStringContainsString('visitor', $payload);
        $this->assertStringNotContainsString('super-secret', $payload);
        $this->assertStringNotContainsString('abc', $payload);
        $this->assertSame('POST', $context['method']);
    }

    #[Test]
    public function attendance_api_write_requires_auth(): void
    {
        $this->postJson('/api/v1/attendance/sync', [])
            ->assertUnauthorized();
    }

    #[Test]
    public function unknown_public_page_shows_safe_404(): void
    {
        $this->get('/this-page-does-not-exist-xyz')
            ->assertNotFound()
            ->assertSee('Page not found', false)
            ->assertDontSee('super-secret');
    }
}
