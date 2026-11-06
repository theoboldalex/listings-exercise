<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActAsDemoUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The README says to build against auth()->user(), which reads the guard.
     */
    public function test_authenticates_the_first_user_on_the_auth_guard(): void
    {
        $demoUser = User::factory()->create();
        User::factory()->create();

        $this->get('/')->assertOk();

        $this->assertAuthenticatedAs($demoUser);
    }

    public function test_leaves_the_request_unauthenticated_when_no_user_exists(): void
    {
        $this->get('/')->assertOk();

        $this->assertGuest();
    }
}
