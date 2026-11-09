<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_closes_once_an_account_exists()
    {
        User::factory()->create();

        $this->get(route('register'))->assertRedirect(route('login'));

        $this->post(route('register.store'), [
            'name' => 'Someone Else',
            'email' => 'else@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_login_page_hides_sign_up_once_an_account_exists()
    {
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', true));

        User::factory()->create();

        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', false));
    }
}
