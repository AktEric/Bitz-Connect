<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('records consent and exports only the requester own data', function () {
    $hotel = Hotel::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    foreach ([$a, $b] as $u) {
        HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $u->id, 'role' => 'employee', 'status' => 'approved']);
    }
    $base = "/api/v1/workspaces/{$hotel->slug}";

    Sanctum::actingAs($a);
    $this->postJson("$base/time/punch")->assertCreated();
    Sanctum::actingAs($b);
    $this->postJson("$base/time/punch")->assertCreated();

    Sanctum::actingAs($a);
    $this->getJson("$base/me/privacy")->assertOk()->assertJsonPath('data.punch_consent_at', null);
    $this->postJson("$base/me/privacy/consent")->assertOk();
    $this->getJson("$base/me/privacy")->assertJsonPath('data.punch_consent_at', fn ($v) => $v !== null);

    $r = $this->getJson("$base/me/export")->assertOk()->json('data');
    expect($r['pessoa']['email'])->toBe($a->email)->and($r['marcacoes'])->toHaveCount(1)->and($r['vinculo']['aceite_foto_localizacao'])->not->toBeNull();
    expect(AuditLog::where('hotel_id', $hotel->id)->whereIn('action', ['privacy.consent_given', 'privacy.data_exported'])->count())->toBe(2);

    // quem não é da empresa não exporta nada
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("$base/me/export")->assertNotFound();
});
