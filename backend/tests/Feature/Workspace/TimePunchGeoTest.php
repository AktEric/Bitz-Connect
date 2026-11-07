<?php

/** Localização, foto e cerca geográfica na marcação de ponto. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

function geoMember(Hotel $hotel, string $role = 'employee', ?User $manager = null): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved', 'manager_id' => $manager?->id]);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00');
    $this->hotel   = Hotel::factory()->create();
    $this->rh      = geoMember($this->hotel, 'admin');
    $this->manager = geoMember($this->hotel);
    $this->worker  = geoMember($this->hotel, 'employee', $this->manager);
    $this->other   = geoMember($this->hotel);
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});

afterEach(fn () => Carbon::setTestNow());

function geoConfigure($test, array $settings): void
{
    Sanctum::actingAs($test->rh);
    $test->putJson("{$test->base}/time/settings", $settings)->assertOk();
}

it('does not ask for location or photo by default', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/today")->assertJsonPath('data.requirements.geo', 'off')->assertJsonPath('data.requirements.photo', 'off');
    $this->postJson("{$this->base}/time/punch")->assertCreated();
});

it('requires location and photo when the company demands them', function () {
    geoConfigure($this, ['geo' => 'required', 'photo' => 'required']);

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/time/punch")->assertStatus(422);
    $this->postJson("{$this->base}/time/punch", ['lat' => -23.55, 'lng' => -46.63])->assertStatus(422);

    $this->post("{$this->base}/time/punch", [
        'lat' => -23.55, 'lng' => -46.63, 'accuracy' => 12.4,
        'photo' => UploadedFile::fake()->image('selfie.png', 200, 200),
    ], ['Accept' => 'application/json'])->assertCreated();

    $entry = TimeEntry::where('user_id', $this->worker->id)->first();
    expect($entry->lat)->toBe(-23.55)->and($entry->accuracy_m)->toBe(12)->and($entry->photo_path)->not->toBeNull();
});

it('flags or blocks punches outside the geofence', function () {
    geoConfigure($this, ['geo' => 'required', 'photo' => 'off', 'fence' => ['lat' => -23.5505, 'lng' => -46.6333, 'radius_m' => 200, 'block' => false]]);

    Sanctum::actingAs($this->worker);
    // ~1,1 km ao norte: fora da cerca, mas só sinaliza
    $this->postJson("{$this->base}/time/punch", ['lat' => -23.5405, 'lng' => -46.6333])->assertCreated();
    expect(TimeEntry::where('user_id', $this->worker->id)->first()->outside_fence)->toBeTrue();

    geoConfigure($this, ['geo' => 'required', 'photo' => 'off', 'fence' => ['lat' => -23.5505, 'lng' => -46.6333, 'radius_m' => 200, 'block' => true]]);
    Carbon::setTestNow('2026-11-06 13:00:00');
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/time/punch", ['lat' => -23.5405, 'lng' => -46.6333])->assertStatus(422);
    $this->postJson("{$this->base}/time/punch", ['lat' => -23.5506, 'lng' => -46.6334, 'type' => 'break_start'])->assertCreated();
});

it('rejects a geofence without location and keeps settings to RH', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/settings")->assertForbidden();
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'fence' => ['lat' => 1, 'lng' => 1, 'radius_m' => 100]])->assertStatus(422);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'bogus', 'photo' => 'off'])->assertStatus(422);
});

it('serves punch photos only to the owner, the manager and RH, and audits third-party views', function () {
    geoConfigure($this, ['geo' => 'off', 'photo' => 'required']);
    Sanctum::actingAs($this->worker);
    $this->post("{$this->base}/time/punch", ['photo' => UploadedFile::fake()->image('s.png', 100, 100)], ['Accept' => 'application/json'])->assertCreated();
    $uuid = TimeEntry::where('user_id', $this->worker->id)->first()->uuid;

    $this->get("{$this->base}/time/entries/{$uuid}/photo")->assertOk();

    Sanctum::actingAs($this->other);
    $this->get("{$this->base}/time/entries/{$uuid}/photo")->assertNotFound();

    Sanctum::actingAs($this->manager);
    $this->get("{$this->base}/time/entries/{$uuid}/photo")->assertOk();

    Sanctum::actingAs($this->rh);
    $this->get("{$this->base}/time/entries/{$uuid}/photo")->assertOk();
    $actions = collect($this->getJson("{$this->base}/audit")->json('data.items'))->pluck('action');
    expect($actions->filter(fn ($a) => $a === 'time.photo_viewed'))->toHaveCount(2);
});
