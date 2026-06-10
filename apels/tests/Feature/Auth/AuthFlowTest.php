<?php

/**
 * Feature tests: Auth flow + Role access (Req 1.1-1.5, 2.2-2.10, 34.3).
 *
 * Register/Login via Livewire component testing (Volt single-file components).
 * Route-level access control tested via HTTP GET.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

// ---- Registration via Livewire Volt (Req 1.1) ----

test('mahasiswa can register with NIM (Req 1.1)', function () {
    Volt::test('auth.register')
        ->set('name', 'Test Mahasiswa')
        ->set('email', 'mhs@test.com')
        ->set('nim', '2024001')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', ['email' => 'mhs@test.com', 'nim' => '2024001']);
    $user = User::where('email', 'mhs@test.com')->first();
    expect($user->hasRole('mahasiswa'))->toBeTrue();
});

test('registration rejects duplicate NIM (Req 1.2)', function () {
    User::factory()->create(['nim' => '2024001']);

    Volt::test('auth.register')
        ->set('name', 'Another User')
        ->set('email', 'other@test.com')
        ->set('nim', '2024001')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['nim']);
});

test('registration rejects duplicate email (Req 1.2)', function () {
    User::factory()->create(['email' => 'mhs@test.com']);

    Volt::test('auth.register')
        ->set('name', 'Another User')
        ->set('email', 'mhs@test.com')
        ->set('nim', '2024999')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email']);
});

// ---- Login via Livewire Volt (Req 1.3, 1.4) ----

test('valid credentials log user in (Req 1.3)', function () {
    $user = User::factory()->create();
    $user->assignRole('mahasiswa');

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials return error without revealing which field (Req 1.4)', function () {
    Volt::test('auth.login')
        ->set('email', 'notexist@test.com')
        ->set('password', 'wrongpassword')
        ->call('login')
        ->assertHasErrors(['email']); // generic 'auth.failed' message on email field
});

// ---- Logout (Req 1.5) ----

test('logout clears session (Req 1.5)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});

// ---- Role-based route access ----

test('mahasiswa can access /dashboard (Req 2.2)', function () {
    $user = User::factory()->create();
    $user->assignRole('mahasiswa');
    $this->actingAs($user);

    $this->get('/dashboard')->assertOk();
});

test('admin blocked from /dashboard with 403 (Req 2.3)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $this->get('/dashboard')->assertStatus(403);
});

test('dosen blocked from /dashboard with 403 (Req 2.3)', function () {
    $dosen = User::factory()->create();
    $dosen->assignRole('dosen');
    $this->actingAs($dosen);

    $this->get('/dashboard')->assertStatus(403);
});

test('guest redirected from /dashboard to /login (Req 2.10)', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('guest gets 401 from API diagnostic submit (Req 2.10, 34.3)', function () {
    $this->postJson('/api/diagnostic/submit', [])->assertUnauthorized();
});

test('dosen can access /reports (Req 2.4)', function () {
    $dosen = User::factory()->create();
    $dosen->assignRole('dosen');
    $this->actingAs($dosen);

    $this->get('/reports')->assertOk();
});

test('mahasiswa blocked from /reports with 403 (Req 2.3)', function () {
    $mhs = User::factory()->create();
    $mhs->assignRole('mahasiswa');
    $this->actingAs($mhs);

    $this->get('/reports')->assertStatus(403);
});

test('mahasiswa can access /learning-path (Req 2.2)', function () {
    $mhs = User::factory()->create();
    $mhs->assignRole('mahasiswa');
    $this->actingAs($mhs);

    $this->get('/learning-path')->assertOk();
});
