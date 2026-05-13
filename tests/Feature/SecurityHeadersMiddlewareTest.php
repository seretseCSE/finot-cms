<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_sets_content_security_policy_header()
    {
        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy');
        $csp = $response->headers->get('Content-Security-Policy');
        
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
    }

    /** @test */
    public function it_sets_x_frame_options_header()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function it_sets_additional_security_headers()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    /** @test */
    public function it_sets_hsts_header_in_production_with_https()
    {
        // Mock production environment and HTTPS
        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->withHeaders([
            'HTTPS' => 'on',
        ])->get('/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    /** @test */
    public function it_does_not_set_hsts_header_in_non_production()
    {
        // Mock non-production environment
        app()->detectEnvironment(function () {
            return 'local';
        });

        $response = $this->get('/');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    /** @test */
    public function it_does_not_set_hsts_header_without_https()
    {
        // Mock production environment but no HTTPS
        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->get('/');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    /** @test */
    public function security_headers_are_applied_to_all_routes()
    {
        // Test different routes
        $routes = ['/', '/login', '/register', '/dashboard'];

        foreach ($routes as $route) {
            $response = $this->get($route);
            
            $response->assertHeader('Content-Security-Policy');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }
}
