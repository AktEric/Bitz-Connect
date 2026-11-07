<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use App\Models\User;
use App\Domain\Workspace\Services\TimeEntryRecorder;
use Illuminate\Support\Facades\Storage;

it('purges old punch photos and coordinates but keeps the record and a valid chain', function () {
    Storage::fake('local');
    $hotel = Hotel::factory()->create();
    $user  = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => 'employee', 'status' => 'approved']);

    $rec = app(TimeEntryRecorder::class);
    $mk = function (string $at, string $photo) use ($rec, $hotel, $user) {
        Storage::disk('local')->put($photo, 'x');

        return $rec->record(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $user->id, 'type' => 'in',
            'punched_at' => $at, 'source' => 'web', 'lat' => -23.5, 'lng' => -46.6, 'accuracy_m' => 10, 'photo_path' => $photo]);
    };
    $old = $mk(now()->subMonths(14), 'punch/old.jpg');
    $new = $mk(now()->subDays(2), 'punch/new.jpg');

    $this->artisan('ponto:purge-sensitive')->assertSuccessful();

    expect($old->fresh()->photo_path)->toBeNull()->and($old->fresh()->lat)->toBeNull()->and($new->fresh()->photo_path)->toBe('punch/new.jpg');
    Storage::disk('local')->assertMissing('punch/old.jpg');
    Storage::disk('local')->assertExists('punch/new.jpg');
    expect(app(TimeEntryRecorder::class)->verify($hotel)['ok'])->toBeTrue();
});
