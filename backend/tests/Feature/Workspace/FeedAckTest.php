<?php

/** Comunicado com confirmação de leitura: público, aviso, "li e estou ciente", painel de quem falta. */

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function ackMember(Hotel $hotel, string $role = 'employee', ?Department $dept = null): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved', 'department_id' => $dept?->id]);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->ops   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $this->fin   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Financeiro']);

    $this->rh     = ackMember($this->hotel, 'admin');
    $this->ana    = ackMember($this->hotel, 'employee', $this->ops);
    $this->bruno  = ackMember($this->hotel, 'employee', $this->ops);
    $this->carla  = ackMember($this->hotel, 'employee', $this->fin);
});

function ackPublish($test, array $fields)
{
    return $test->postJson("{$test->base}/feed", $fields);
}

it('notifies the whole audience and tracks who confirmed the reading', function () {
    Sanctum::actingAs($this->rh);
    $uuid = ackPublish($this, ['body' => 'Nova política de segurança', 'requires_ack' => true])->assertCreated()
        ->assertJsonPath('data.requires_ack', true)->assertJsonPath('data.ack_total', 3)->assertJsonPath('data.ack_count', 0)->json('data.uuid');

    foreach ([$this->ana, $this->bruno, $this->carla] as $u) {
        expect(Notification::where('user_id', $u->id)->where('type', 'workspace_ack_required')->count())->toBe(1);
    }
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_ack_required')->count())->toBe(0);

    // a pessoa vê o comunicado pendente e confirma; repetir não duplica
    Sanctum::actingAs($this->ana);
    expect($this->getJson($this->base)->json('data.counts.pending_acks'))->toBe(1);
    $item = $this->getJson("{$this->base}/feed")->json('data.items.0');
    expect($item['requires_ack'])->toBeTrue()->and($item['acked_at'])->toBeNull()->and($item['ack_count'])->toBeNull(); // contagem só para autor/RH

    $first = $this->postJson("{$this->base}/feed/{$uuid}/ack")->assertOk()->json('data.acked_at');
    $this->postJson("{$this->base}/feed/{$uuid}/ack")->assertOk()->assertJsonPath('data.acked_at', $first);
    expect($this->getJson($this->base)->json('data.counts.pending_acks'))->toBe(0);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.acked_at'))->not->toBeNull();

    // quem não é autor/RH não vê o painel; RH vê quem leu e quem falta
    $this->getJson("{$this->base}/feed/{$uuid}/acks")->assertNotFound();
    Sanctum::actingAs($this->rh);
    $panel = $this->getJson("{$this->base}/feed/{$uuid}/acks")->assertOk();
    expect($panel->json('data.total'))->toBe(3)
        ->and($panel->json('data.readers'))->toHaveCount(1)->and($panel->json('data.readers.0.person.uuid'))->toBe($this->ana->uuid)
        ->and(array_column($panel->json('data.pending'), 'uuid'))->toEqualCanonicalizing([$this->bruno->uuid, $this->carla->uuid]);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.ack_count'))->toBe(1);
});

it('limits the audience to the chosen area', function () {
    Sanctum::actingAs($this->rh);
    $uuid = ackPublish($this, ['body' => 'Escala de Operações', 'requires_ack' => true, 'department_uuid' => $this->ops->uuid])->assertCreated()
        ->assertJsonPath('data.ack_total', 2)->json('data.uuid');

    expect(Notification::where('user_id', $this->carla->id)->where('type', 'workspace_ack_required')->count())->toBe(0);
    expect(Notification::where('user_id', $this->ana->id)->where('type', 'workspace_ack_required')->count())->toBe(1);

    Sanctum::actingAs($this->carla); // do Financeiro: não vê nem confirma
    expect($this->getJson($this->base)->json('data.counts.pending_acks'))->toBe(0);
    $this->postJson("{$this->base}/feed/{$uuid}/ack")->assertNotFound();
});

it('does not require anything on regular posts and rejects ack on them', function () {
    Sanctum::actingAs($this->rh);
    $uuid = ackPublish($this, ['body' => 'Bom dia, time!'])->assertCreated()->assertJsonPath('data.requires_ack', false)->assertJsonPath('data.ack_total', null)->json('data.uuid');
    expect(Notification::where('type', 'workspace_ack_required')->count())->toBe(0);

    Sanctum::actingAs($this->ana);
    $this->postJson("{$this->base}/feed/{$uuid}/ack")->assertNotFound();
    expect($this->getJson($this->base)->json('data.counts.pending_acks'))->toBe(0);
});

it('lets the author, not only RH, follow the confirmations of a comunicado', function () {
    $manager = ackMember($this->hotel, 'employee', $this->ops);
    HotelUser::where('user_id', $this->bruno->id)->update(['manager_id' => $manager->id]); // o gestor passa a poder publicar

    Sanctum::actingAs($manager);
    $uuid = ackPublish($this, ['body' => 'Aviso da equipe', 'requires_ack' => true, 'department_uuid' => $this->ops->uuid])->assertCreated()->json('data.uuid');
    $this->getJson("{$this->base}/feed/{$uuid}/acks")->assertOk()->assertJsonPath('data.total', 2);
});
