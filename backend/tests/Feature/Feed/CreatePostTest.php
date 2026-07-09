<?php

/**
 * POST /api/v1/posts authorization tests.
 * Ensures a user cannot attach a post to a hotel they don't administer.
 */

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows a hotel admin to create a post attached to their hotel', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    $hotel->hotelUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'content'    => 'Post oficial do hotel',
        'hotel_uuid' => $hotel->uuid,
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});

it('rejects post creation with hotel_uuid when the user is not an admin of that hotel', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'content'    => 'Tentando publicar em nome de outro hotel',
        'hotel_uuid' => $hotel->uuid,
    ]);

    $response->assertStatus(403)->assertJson(['success' => false]);
});

it('allows creating a post without hotel_uuid (personal post)', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'content' => 'Post pessoal sem hotel',
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});
