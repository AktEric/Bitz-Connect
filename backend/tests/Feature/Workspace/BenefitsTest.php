<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->hr      = $mk('admin', ['access_role' => 'hr']);
    $this->finance = $mk('admin', ['access_role' => 'finance']);   // role legado admin, mas papel não é de RH
    $this->eric    = $mk('employee');
    $this->gabriel = $mk('employee');
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";

    $this->typeId = function (string $name) {
        Sanctum::actingAs($this->hr);

        return collect($this->getJson("{$this->base}/benefit-types")->json('data.types'))->firstWhere('name', $name)['uuid'];
    };
    $this->give = fn (User $to, string $type, array $extra = []) => (function () use ($to, $type, $extra) {
        Sanctum::actingAs($this->hr);

        return $this->postJson("{$this->base}/people/{$to->uuid}/benefits", $extra + ['benefit_type_uuid' => ($this->typeId)($type), 'amount' => 600, 'frequency' => 'monthly', 'deposit_day' => 5]);
    })->call($this);
});
afterEach(fn () => Carbon::setTestNow());

it('creates the suggested catalog with colors on first use', function () {
    Sanctum::actingAs($this->hr);
    $types = collect($this->getJson("{$this->base}/benefit-types")->assertOk()->json('data.types'));
    expect($types->pluck('name')->all())->toContain('Vale alimentação', 'Vale transporte', 'Plano de saúde')
        ->and($types->firstWhere('name', 'Vale alimentação')['color'])->toBe('green')->and($types->firstWhere('name', 'Vale transporte')['color'])->toBe('blue');
});

it('shows each person only their own benefits, with value, color and deposit date', function () {
    ($this->give)($this->eric, 'Vale alimentação', ['amount' => 650, 'deposit_day' => 5])->assertCreated();
    ($this->give)($this->eric, 'Vale transporte', ['amount' => 220.5, 'deposit_day' => 28])->assertCreated();
    ($this->give)($this->gabriel, 'Vale alimentação', ['amount' => 400, 'deposit_day' => 10])->assertCreated();

    Sanctum::actingAs($this->eric);
    $mine = $this->getJson("{$this->base}/me/benefits")->assertOk();
    $items = collect($mine->json('data.items'));
    expect($items)->toHaveCount(2)->and($mine->json('data.total_monthly_cents'))->toBe(87050)
        ->and($items->firstWhere('type.name', 'Vale alimentação')['type']['color'])->toBe('green')
        ->and($items->firstWhere('type.name', 'Vale alimentação')['next_deposit_on'])->toBe('2026-12-05')   // dia 5 já passou em novembro
        ->and($items->firstWhere('type.name', 'Vale transporte')['next_deposit_on'])->toBe('2026-11-28')
        ->and($items->firstWhere('type.name', 'Vale transporte')['amount_cents'])->toBe(22050);

    Sanctum::actingAs($this->gabriel);
    $g = collect($this->getJson("{$this->base}/me/benefits")->json('data.items'));
    expect($g)->toHaveCount(1)->and($g[0]['amount_cents'])->toBe(40000);
    // sem como pedir o de outra pessoa
    expect($this->getJson("{$this->base}/me/benefits?user={$this->eric->uuid}")->json('data.items'))->toHaveCount(1);
    $this->getJson("{$this->base}/people/{$this->eric->uuid}/benefits")->assertForbidden();
});

it('stores the amount encrypted and never leaks it outside the benefits endpoints', function () {
    ($this->give)($this->eric, 'Vale alimentação', ['amount' => 777.77])->assertCreated();
    $raw = DB::table('employee_benefits')->value('amount');
    expect($raw)->not->toContain('77777');

    Sanctum::actingAs($this->eric);
    expect(json_encode($this->getJson("{$this->base}/me/profile")->json()))->not->toContain('77777');
    expect(json_encode($this->getJson("{$this->base}/people/{$this->eric->uuid}/record")->json()))->not->toContain('77777');
});

it('lets only HR/owner manage, audits reading of others, and notifies the person', function () {
    ($this->give)($this->eric, 'Plano de saúde', ['amount' => null, 'deposit_day' => 1, 'note' => 'Unimed Nacional'])->assertCreated();
    $id = collect($this->getJson("{$this->base}/people/{$this->eric->uuid}/benefits")->json('data.items'))->first()['uuid'];
    expect(AuditLog::where('action', 'benefits.viewed')->exists())->toBeTrue();
    expect(DB::table('notifications')->where('user_id', $this->eric->id)->where('title', 'like', 'Seus benefícios%')->exists())->toBeTrue();

    $gym = ($this->typeId)('Gympass');   // (troca o usuário para o RH; por isso antes do laço)
    foreach ([$this->eric, $this->finance] as $u) {   // colaborador e "admin legado" com papel financeiro
        Sanctum::actingAs($u);
        $this->postJson("{$this->base}/people/{$this->gabriel->uuid}/benefits", ['benefit_type_uuid' => $gym, 'frequency' => 'monthly', 'deposit_day' => 5])->assertForbidden();
        $this->putJson("{$this->base}/people/{$this->eric->uuid}/benefits/{$id}", ['benefit_type_uuid' => $gym, 'frequency' => 'monthly', 'deposit_day' => 5])->assertForbidden();
        $this->deleteJson("{$this->base}/people/{$this->eric->uuid}/benefits/{$id}")->assertForbidden();
        $this->getJson("{$this->base}/benefit-types")->assertForbidden();
        $this->postJson("{$this->base}/benefit-types", ['name' => 'Novo', 'color' => 'blue'])->assertForbidden();
        $this->getJson("{$this->base}/benefit-packages")->assertForbidden();
    }
});

it('validates deposit rules and avoids duplicate active benefits', function () {
    ($this->give)($this->eric, 'Vale alimentação', ['deposit_day' => null])->assertUnprocessable();
    ($this->give)($this->eric, 'Vale alimentação', ['frequency' => 'one_time', 'deposit_day' => null, 'deposit_on' => null])->assertUnprocessable();
    ($this->give)($this->eric, 'Vale alimentação', ['amount' => -5])->assertUnprocessable();
    ($this->give)($this->eric, 'Vale alimentação')->assertCreated();
    ($this->give)($this->eric, 'Vale alimentação')->assertUnprocessable();                       // já vigente
    ($this->give)($this->eric, 'Vale transporte', ['frequency' => 'one_time', 'deposit_on' => '2026-12-20', 'deposit_day' => null])->assertCreated();

    Sanctum::actingAs($this->eric);
    $items = collect($this->getJson("{$this->base}/me/benefits")->json('data.items'));
    expect($items->firstWhere('type.name', 'Vale transporte')['next_deposit_on'])->toBe('2026-12-20');
});

it('clamps the deposit day in short months and hides ended benefits from the monthly total', function () {
    Carbon::setTestNow('2026-02-10 12:00:00');
    ($this->give)($this->eric, 'Vale alimentação', ['deposit_day' => 31])->assertCreated();
    ($this->give)($this->eric, 'Vale refeição', ['amount' => 100, 'ends_on' => '2026-01-31'])->assertCreated();   // encerrado

    Sanctum::actingAs($this->eric);
    $r = $this->getJson("{$this->base}/me/benefits");
    $items = collect($r->json('data.items'));
    expect($items->firstWhere('type.name', 'Vale alimentação')['next_deposit_on'])->toBe('2026-02-28')
        ->and($items->firstWhere('type.name', 'Vale refeição')['active'])->toBeFalse()->and($items->firstWhere('type.name', 'Vale refeição')['next_deposit_on'])->toBeNull()
        ->and($r->json('data.total_monthly_cents'))->toBe(60000);
});

it('keeps benefits of another company out of reach', function () {
    ($this->give)($this->eric, 'Vale alimentação')->assertCreated();
    $other = Hotel::factory()->create();
    $boss = tap(User::factory()->create(), fn ($u) => HotelUser::create(['hotel_id' => $other->id, 'user_id' => $u->id, 'role' => 'admin', 'access_role' => 'owner', 'status' => 'approved']));
    Sanctum::actingAs($boss);
    $this->getJson("/api/v1/workspaces/{$other->slug}/people/{$this->eric->uuid}/benefits")->assertNotFound();
    expect($this->getJson("/api/v1/workspaces/{$other->slug}/me/benefits")->json('data.items'))->toBe([]);
});

it('applies a package to a person and through the invite', function () {
    Sanctum::actingAs($this->hr);
    $va = ($this->typeId)('Vale alimentação');
    $vt = ($this->typeId)('Vale transporte');
    $pkg = $this->postJson("{$this->base}/benefit-packages", ['name' => 'Operacional CLT', 'items' => [
        ['benefit_type_uuid' => $va, 'amount' => 600, 'deposit_day' => 5], ['benefit_type_uuid' => $vt, 'amount' => 300, 'deposit_day' => 28],
    ]])->assertCreated()->json('data.packages.0.uuid');

    $this->postJson("{$this->base}/people/{$this->eric->uuid}/benefits/apply-package", ['package_uuid' => $pkg])->assertOk();
    expect($this->getJson("{$this->base}/people/{$this->eric->uuid}/benefits")->json('data.total_monthly_cents'))->toBe(90000);
    // reaplicar não duplica
    $this->postJson("{$this->base}/people/{$this->eric->uuid}/benefits/apply-package", ['package_uuid' => $pkg])->assertOk();
    expect($this->getJson("{$this->base}/people/{$this->eric->uuid}/benefits")->json('data.items'))->toHaveCount(2);

    // convite com pacote: quem entra já recebe os benefícios
    $token = $this->postJson("{$this->base}/invites", ['job_title' => 'Auxiliar', 'benefit_package_uuid' => $pkg])->assertCreated()->json('data.token');
    $newbie = User::factory()->create();
    Sanctum::actingAs($newbie);
    $this->postJson("/api/v1/invites/{$token}/accept")->assertOk();
    $items = collect($this->getJson("{$this->base}/me/benefits")->json('data.items'));
    expect($items->pluck('type.name')->all())->toContain('Vale alimentação', 'Vale transporte')->and($items)->toHaveCount(2);

    // pacote que não é desta empresa não vale no convite
    Sanctum::actingAs($this->hr);
    $this->postJson("{$this->base}/invites", ['benefit_package_uuid' => (string) Illuminate\Support\Str::uuid()])->assertNotFound();
});
