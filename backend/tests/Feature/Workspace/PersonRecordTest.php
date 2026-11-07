<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, ?string $access = null) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'access_role' => $access, 'status' => 'approved']));
    $this->rh     = $mk('admin', 'hr');
    $this->person = $mk('employee');
    $this->peer   = $mk('employee');
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
});

it('lets only RH fill the record, keeps it encrypted, and lets the person read their own', function () {
    $body = ['birth_date' => '1990-11-10', 'phone' => '(11) 99999-0000', 'emergency_name' => 'Maria', 'emergency_phone' => '(11) 98888-7777', 'bank' => 'Banco X', 'agency' => '0001', 'account' => '12345-6', 'pix' => 'pessoa@exemplo.com'];

    Sanctum::actingAs($this->person);
    $this->putJson("{$this->base}/people/{$this->person->uuid}/record", $body)->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/people/{$this->person->uuid}/record", $body)->assertOk()->assertJsonPath('data.bank', 'Banco X');
    $this->getJson("{$this->base}/people/{$this->person->uuid}/record")->assertOk()->assertJsonPath('data.emergency_phone', '(11) 98888-7777');

    // no banco fica criptografado e o log não guarda os valores
    $raw = DB::table('hotel_users')->where('user_id', $this->person->id)->value('private_data');
    expect($raw)->not->toContain('Banco X')->and($raw)->not->toContain('12345-6');
    expect(json_encode(AuditLog::where('action', 'person.record_updated')->first()->meta))->not->toContain('Banco X');
    expect(AuditLog::where('action', 'person.record_viewed')->count())->toBe(1);

    Sanctum::actingAs($this->person);
    $this->getJson("{$this->base}/people/{$this->person->uuid}/record")->assertOk()->assertJsonPath('data.can_edit', false);
    Sanctum::actingAs($this->peer);
    $this->getJson("{$this->base}/people/{$this->person->uuid}/record")->assertNotFound();
});

it('lets the person fill the social profile but not their own birth date or record', function () {
    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/people/{$this->person->uuid}/record", ['birth_date' => '1990-11-10'])->assertOk();

    Sanctum::actingAs($this->person);
    $this->putJson("{$this->base}/me/profile", ['bio' => 'Gosto de café', 'favorite_book' => 'Dom Casmurro', 'favorite_food' => 'Feijoada', 'birth_date' => '2000-01-01'])->assertOk();
    $me = $this->getJson("{$this->base}/me/profile")->assertOk()->json('data');
    expect($me['profile']['favorite_book'])->toBe('Dom Casmurro')->and($me['has_birth_date'])->toBeTrue()->and($me['birthday'])->toBe(['day' => 10, 'month' => 11]);

    // colegas veem o cartão público, sem ficha cadastral
    Sanctum::actingAs($this->peer);
    $card = $this->getJson("{$this->base}/people/{$this->person->uuid}/profile")->assertOk()->json('data');
    expect($card['profile']['favorite_food'])->toBe('Feijoada')->and(json_encode($card))->not->toContain('1990');

    // esconder o aniversário some com o dia/mês no cartão
    Sanctum::actingAs($this->person);
    $this->putJson("{$this->base}/me/profile", ['birthday_visible' => false])->assertOk();
    Sanctum::actingAs($this->peer);
    expect($this->getJson("{$this->base}/people/{$this->person->uuid}/profile")->json('data.birthday'))->toBeNull();
});
