<?php

/** Assistente de implantação: passos deduzidos dos dados reais, só para dono e RH. */

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function onbMember(Hotel $hotel, string $role = 'employee', array $extra = []): User
{
    $user = User::factory()->create();
    HotelUser::create(array_merge(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved'], $extra));

    return $user;
}

function onbSteps($test): array
{
    return collect($test->getJson("{$test->base}/onboarding")->assertOk()->json('data.steps'))->pluck('done', 'key')->all();
}

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->rh    = onbMember($this->hotel, 'admin');
});

it('starts with nothing done and ticks steps as real data appears', function () {
    Sanctum::actingAs($this->rh);
    $res = $this->getJson("{$this->base}/onboarding")->assertOk();
    expect($res->json('data.total'))->toBe(8)->and($res->json('data.done'))->toBe(0)->and($res->json('data.percent'))->toBe(0)->and($res->json('data.dismissed'))->toBeFalse();

    $dept = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $boss = onbMember($this->hotel, 'employee', ['department_id' => $dept->id]);
    onbMember($this->hotel, 'employee', ['department_id' => $dept->id, 'manager_id' => $boss->id]);
    $dept->update(['head_id' => $boss->id]);

    $steps = onbSteps($this);
    expect($steps['structure'])->toBeTrue()->and($steps['people'])->toBeTrue()->and($steps['managers'])->toBeTrue()
        ->and($steps['schedules'])->toBeFalse()->and($steps['punch'])->toBeFalse()->and($steps['post'])->toBeFalse();

    $this->putJson("{$this->base}/time/settings", ['geo' => 'optional', 'photo' => 'off'])->assertOk();
    $this->postJson("{$this->base}/schedules", ['name' => 'Padrão', 'week_minutes' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480]])->assertCreated();
    $this->postJson("{$this->base}/feed", ['body' => 'Bem-vindos!'])->assertCreated();
    $this->postJson("{$this->base}/calendar/events", ['title' => 'Feriado local', 'kind' => 'holiday', 'starts_on' => '2026-12-08'])->assertCreated();
    $this->putJson("{$this->base}/security", ['require_2fa' => true])->assertStatus(422); // sem 2FA próprio, o dono não pode exigir da empresa
    $this->rh->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
    $this->putJson("{$this->base}/security", ['require_2fa' => true])->assertOk();

    $res = $this->getJson("{$this->base}/onboarding")->assertOk();
    expect($res->json('data.done'))->toBe(8)->and($res->json('data.percent'))->toBe(100);
});

it('lets owner and RH hide the invitation and keeps it away from everyone else', function () {
    $worker = onbMember($this->hotel);
    Sanctum::actingAs($worker);
    $this->getJson("{$this->base}/onboarding")->assertForbidden();
    $this->postJson("{$this->base}/onboarding/dismiss")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/onboarding/dismiss")->assertOk();
    expect($this->getJson("{$this->base}/onboarding")->json('data.dismissed'))->toBeTrue();
});
