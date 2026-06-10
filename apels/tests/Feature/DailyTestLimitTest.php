<?php

use App\Models\DiagnosticResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 4.3: Feature test EnsureDailyTestLimit + ApiResponse format
 * Validates Requirements: 3.1, 3.2, 17.1, 17.2
 */
describe('Daily Test Limit Middleware', function () {
    describe('Middleware Behavior', function () {
        it('allows first diagnostic test on a day', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            $response = $this->actingAs($user)->get('/diagnostic');
            $response->assertSuccessful();
        });

        it('blocks second diagnostic test on the same day (429)', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            // Create a diagnostic result for today
            DiagnosticResult::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            $response = $this->actingAs($user)->get('/diagnostic');
            $response->assertStatus(429);
        });

        it('allows new test after midnight (next day)', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            // Create diagnostic result for yesterday
            DiagnosticResult::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subDay(),
            ]);

            $response = $this->actingAs($user)->get('/diagnostic');
            $response->assertSuccessful();
        });

        it('respects application timezone for daily limit check', function () {
            $user = User::factory()->create();
            $user->assignRole('mahasiswa');

            // Create result at current time
            DiagnosticResult::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(config('app.timezone')),
            ]);

            $response = $this->actingAs($user)->get('/diagnostic');
            $response->assertStatus(429);
        });
    });

    describe('Guest Access', function () {
        it('redirects guest to login instead of checking limit', function () {
            $response = $this->get('/diagnostic');
            $response->assertRedirect('/login');
        });
    });

    describe('Role-Based Access', function () {
        it('prevents dosen from accessing diagnostic', function () {
            $user = User::factory()->create();
            $user->assignRole('dosen');

            $response = $this->actingAs($user)->get('/diagnostic');
            expect(in_array($response->getStatusCode(), [403, 302]))->toBeTrue();
        });

        it('prevents admin from accessing diagnostic', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)->get('/diagnostic');
            expect(in_array($response->getStatusCode(), [403, 302]))->toBeTrue();
        });
    });
});

describe('ApiResponse Format', function () {
    describe('Success Response', function () {
        it('returns correct success JSON structure', function () {
            // This would be tested via an endpoint that uses ApiResponse::success
            // For now, we test the helper method directly
            $response = \App\Support\ApiResponse::success('Test message', ['key' => 'value']);

            expect($response->getStatusCode())->toBe(200);
            $content = json_decode($response->getContent(), true);
            expect($content)->toHaveKey('status', 'success');
            expect($content)->toHaveKey('message', 'Test message');
            expect($content)->toHaveKey('data', ['key' => 'value']);
        });

        it('returns empty data array when not provided', function () {
            $response = \App\Support\ApiResponse::success('Message only');
            $content = json_decode($response->getContent(), true);

            expect($content['data'])->toBe([]);
        });
    });

    describe('Error Response', function () {
        it('returns correct error JSON structure', function () {
            $response = \App\Support\ApiResponse::error('Error message', 400, ['field' => 'error']);

            expect($response->getStatusCode())->toBe(400);
            $content = json_decode($response->getContent(), true);
            expect($content)->toHaveKey('status', 'error');
            expect($content)->toHaveKey('message', 'Error message');
            expect($content)->toHaveKey('code', 400);
            expect($content)->toHaveKey('errors', ['field' => 'error']);
        });

        it('returns correct HTTP status code', function () {
            $response429 = \App\Support\ApiResponse::error('Too many requests', 429);
            expect($response429->getStatusCode())->toBe(429);

            $response404 = \App\Support\ApiResponse::error('Not found', 404);
            expect($response404->getStatusCode())->toBe(404);

            $response500 = \App\Support\ApiResponse::error('Server error', 500);
            expect($response500->getStatusCode())->toBe(500);
        });

        it('returns empty errors array when not provided', function () {
            $response = \App\Support\ApiResponse::error('Message only', 400);
            $content = json_decode($response->getContent(), true);

            expect($content['errors'])->toBe([]);
        });
    });

    describe('Content-Type Header', function () {
        it('sets Content-Type to application/json for success', function () {
            $response = \App\Support\ApiResponse::success('Test');
            expect($response->headers->get('Content-Type'))->toBe('application/json');
        });

        it('sets Content-Type to application/json for error', function () {
            $response = \App\Support\ApiResponse::error('Test', 400);
            expect($response->headers->get('Content-Type'))->toBe('application/json');
        });
    });
});
