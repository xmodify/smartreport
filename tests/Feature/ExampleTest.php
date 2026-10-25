<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the root login page returns successful response.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    /**
     * Test index.html alias returns successful response.
     */
    public function test_index_html_alias_works(): void
    {
        $response = $this->get('/index.html');

        $response->assertStatus(200);
    }

    /**
     * Test homepage returns successful response.
     */
    public function test_homepage_returns_successful_response(): void
    {
        $response = $this->get('/bms-homepage');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('BmsHomepage/Index'));
    }

    /**
     * Test admin routes are protected by auth.
     */
    public function test_admin_routes_require_authentication(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated admin can view dashboard.
     */
    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }
}
