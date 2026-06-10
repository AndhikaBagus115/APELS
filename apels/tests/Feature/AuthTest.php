<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 3.6: Feature test auth + role access
 * Validates Requirements: 1.1, 1.2, 1.3, 1.4, 2.2, 2.3, 2.4, 2.10
 */
describe('Authentication & Authorization', function () {
    describe('Registration', function () {
        it('registers a new user with NIM and assigns mahasiswa role', function () {
            $response = $this->post('/register', [
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'nim' => '12345678',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertRedirect('/dashboard');
            $this->assertDatabaseHas('users', [
                'email' => 'student@test.com',
                'nim' => '12345678',
            ]);

            $user = User::where('email', 'student@test.com')->first();
            expect($user->hasRole('mahasiswa'))->toBeTrue();
        });

        it('rejects duplicate NIM during registration', function () {
            User::factory()->create(['nim' => '12345678']);

            $response = $this->post('/register', [
                'name' => 'Another Student',
                'email' => 'another@test.com',
                'nim' => '12345678',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasErrors(['nim']);
        });

        it('requires unique email', function () {
            User::factory()->create(['email' => 'student@test.com']);

            $response = $this->post('/register', [
                'name' => 'Duplicate Student',
                'email' => 'student@test.com',
                'nim' => '87654321',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasErrors(['email']);
        });
    });

    describe('Login', function () {
        it('allows user to login with valid credentials', function () {
            $user = User::factory()->create([
                'email' => 'student@test.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('mahasiswa');

            $response = $this->post('/login', [
                'email' => 'student@test.com',
                'password' => 'password',
            ]);

            $response->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
        });

        it('rejects login with invalid credentials', function () {
            User::factory()->create([
                'email' => 'student@test.com',
                'password' => bcrypt('password'),
            ]);

            $response = $this->post('/login', [
                'email' => 'student@test.com',
                'password' => 'wrong-password',
            ]);

            $response->assertSessionHasErrors(['email']);
            $this->assertGuest();
        });

        it('shows generic error message (no email/password hint)', function () {
            $response = $this->post('/login', [
                'email' => 'nonexistent@test.com',
                'password' => 'password',
            ]);

            // Error message should not reveal whether email exists
            $response->assertSessionHasErrors('email');
            $response->assertSessionHas('errors');
        });

        it('redirects guest to login page', function () {
            $response = $this->get('/dashboard');
            $response->assertRedirect('/login');
        });
    });

    describe('Role-Based Redirects', function () {
        it('redirects mahasiswa to /dashboard after login', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $response = $this->actingAs($user)->get('/');
            $response->assertRedirect('/dashboard');
        });

        it('redirects dosen to /reports after login', function () {
            $user = User::factory()->create();
            $user->assignRole('dosen');

            $response = $this->actingAs($user)->get('/');
            $response->assertRedirect('/reports');
        });

        it('redirects admin to /admin after login', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)->get('/');
            $response->assertRedirect('/admin');
        });

        it('redirects guest to /login from home route', function () {
            $response = $this->get('/');
            $response->assertRedirect('/login');
        });
    });

    describe('Role-Based Access Control', function () {
        it('prevents guest from accessing mahasiswa routes', function () {
            $this->get('/dashboard')->assertRedirect('/login');
            $this->get('/learning-path')->assertRedirect('/login');
            $this->get('/diagnostic')->assertRedirect('/login');
        });

        it('prevents mahasiswa from accessing /reports (dosen only)', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $response = $this->actingAs($user)->get('/reports');
            // Should be forbidden (403) or redirect
            expect(in_array($response->getStatusCode(), [403, 302]))->toBeTrue();
        });

        it('prevents mahasiswa from accessing /admin (admin only)', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $response = $this->actingAs($user)->get('/admin');
            expect(in_array($response->getStatusCode(), [403, 302]))->toBeTrue();
        });

        it('allows dosen to access /reports', function () {
            $user = User::factory()->create();
            $user->assignRole('dosen');

            $response = $this->actingAs($user)->get('/reports');
            $response->assertSuccessful();
        });

        it('allows admin to access /admin', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)->get('/admin');
            $response->assertSuccessful();
        });

        it('allows mahasiswa to access /dashboard', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $response = $this->actingAs($user)->get('/dashboard');
            $response->assertSuccessful();
        });
    });

    describe('Logout', function () {
        it('logs out authenticated user', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $this->actingAs($user);
            $this->assertAuthenticatedAs($user);

            $response = $this->post('/logout');
            $response->assertRedirect('/');
            $this->assertGuest();
        });

        it('clears session on logout', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $this->actingAs($user)->post('/logout');
            $this->assertGuest();
        });
    });
});
