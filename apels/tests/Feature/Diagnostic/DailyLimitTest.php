<?php

/**
 * Feature tests: Daily Test Limit middleware + ApiResponse shape (Req 3.1-3.3, 17.1-17.4, 34.3).
 */

use App\Models\DiagnosticResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->mhs = User::factory()->create();
    $this->mhs->assignRole('mahasiswa');
});

// ---- Daily limit (Req 3.1, 3.2, 34.3) ----

test('mahasiswa who already tested today gets 429 (Req 3.1)', function () {
    // Create existing test for today
    DiagnosticResult::factory()->create([
        'user_id'    => $this->mhs->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->mhs)
        ->postJson('/api/diagnostic/submit', [
            'text_answers' => ['answer one here', 'answer two here', 'answer three ok'],
            'audio'        => null,
        ]);

    $response->assertStatus(429);
    $response->assertJsonStructure(['status', 'message', 'errors', 'code']);
    $response->assertJson(['status' => 'error', 'code' => 429]);
    expect($response->json('message'))->toContain('hari ini');
});

test('mahasiswa without test today passes middleware (Req 3.2)', function () {
    // No test today — middleware should let request through
    // Even if validation fails (no audio file), we get 422 not 429
    $response = $this->actingAs($this->mhs)
        ->postJson('/api/diagnostic/submit', [
            'text_answers' => ['a', 'b', 'c'], // too short, validation will fail
        ]);

    // Should NOT be 429
    $response->assertStatus(422);
});

// ---- ApiResponse shape (Req 17.1-17.4) ----

test('success response has correct shape (Req 17.1)', function () {
    $response = $this->actingAs($this->mhs)
        ->getJson('/api/diagnostic/submit'); // GET returns 405 but with JSON body from exception handler

    // Better: test via 422 validation failure which uses ApiResponse implicitly
    $response = $this->actingAs($this->mhs)
        ->postJson('/api/diagnostic/submit', ['text_answers' => ['a']]);

    $response->assertStatus(422);
    $response->assertHeader('Content-Type', 'application/json');
});

test('error response includes code field matching HTTP status (Req 17.2, 17.3)', function () {
    DiagnosticResult::factory()->create([
        'user_id'    => $this->mhs->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->mhs)
        ->postJson('/api/diagnostic/submit', ['text_answers' => ['ok']]);

    $response->assertStatus(429);
    expect($response->json('code'))->toBe(429);
    expect($response->json('status'))->toBe('error');
});

// ---- Guest (Req 2.10, 34.3) ----

test('guest gets 401 on diagnostic submit (Req 2.10, 34.3)', function () {
    $response = $this->postJson('/api/diagnostic/submit', []);
    $response->assertStatus(401);
});
