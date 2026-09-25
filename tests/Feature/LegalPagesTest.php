<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.privacy'));

        $response->assertOk();
        $response->assertSee('Privacy Policy', false);
        $response->assertSee('Data We Collect', false);
    }

    public function test_terms_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertSee('Terms of Service', false);
        $response->assertSee('Acceptable Use', false);
    }

    public function test_about_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.about'));

        $response->assertOk();
        $response->assertSee('About WeatherNode', false);
        $response->assertSee('Project Principles', false);
    }

    public function test_license_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.license'));

        $response->assertOk();
        $response->assertSee('License', false);
        $response->assertSee('GNU AFFERO GENERAL PUBLIC LICENSE', false);
    }

    public function test_disclaimer_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.disclaimer'));

        $response->assertOk();
        $response->assertSee('Disclaimer', false);
        $response->assertSee('Do Not Use for Safety-Critical Decisions', false);
    }

    public function test_notices_page_is_public_and_renders_content(): void
    {
        $response = $this->get(route('legal.notices'));

        $response->assertOk();
        $response->assertSee('Notices', false);
        $response->assertSee('Third-Party', false);
    }
}
