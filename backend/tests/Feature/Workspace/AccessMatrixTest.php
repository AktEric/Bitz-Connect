<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Payslip;
use App\Models\User;
use App\Models\WorkspacePost;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Matriz de acesso do Workspace: quem NÃO deve conseguir agir não consegue.
 * - sem login: 401 em tudo;
 * - de outra empresa: nunca 2xx em nenhuma rota da empresa;
 * - colaborador comum: ações de RH, gestão e diretoria negadas (e nada muda no banco).
 */
beforeEach(function () {
    Storage::fake('local');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->owner  = $mk('admin', ['access_role' => 'owner']);
    $this->hr     = $mk('admin', ['access_role' => 'hr']);
    $this->lead   = $mk('employee', ['job_title' => 'Gerente']);
    $this->ana    = $mk('employee', ['job_title' => 'Analista', 'manager_id' => $this->lead->id]);
    $this->bruno  = $mk('employee', ['job_title' => 'Auxiliar', 'manager_id' => $this->lead->id]);
    // pegadinha: role=admin legado, mas papel de acesso de colaborador/financeiro/diretoria NÃO administra pessoas
    $this->fakeAdmin = $mk('admin', ['access_role' => 'finance', 'job_title' => 'Financeiro']);
    $this->dept   = $this->hotel->departments()->create(['uuid' => (string) Str::uuid(), 'name' => 'Recepção']);
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->hbase  = "/api/v1/hotels/{$this->hotel->slug}";

    Storage::disk('local')->put('payslips/x/a.pdf', '%PDF-1.4');
    $this->slip = Payslip::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->bruno->id, 'uploaded_by' => $this->hr->id,
        'kind' => 'payslip', 'title' => 'Holerite', 'file_path' => 'payslips/x/a.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 8]);
    $this->post1 = WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'author_id' => $this->hr->id, 'body' => 'Aviso']);
});

/** Preenche {hotel} e os demais parâmetros das rotas com valores de teste. */
function fillUri(string $uri, string $slug): string
{
    $uri = str_replace('{hotel}', $slug, $uri);

    return (string) preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => match ($m[1]) {
        'month' => '2026-10', 'key' => 'overview', default => (string) Str::uuid(),
    }, $uri);
}

function companyRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/workspaces/{hotel}'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => [$m, $r->uri()]))->values()->all();
}

it('requires login on every company route', function () {
    foreach (companyRoutes() as [$method, $uri]) {
        $status = test()->json($method, '/' . fillUri($uri, $this->hotel->slug))->status();
        expect($status)->toBe(401, "{$method} {$uri} deveria exigir login");
    }
});

it('never lets a person from another company do anything on this company', function () {
    $otherHotel = Hotel::factory()->create();
    $outsider = tap(User::factory()->create(), fn ($u) => HotelUser::create(['hotel_id' => $otherHotel->id, 'user_id' => $u->id, 'role' => 'admin', 'access_role' => 'owner', 'status' => 'approved']));
    Sanctum::actingAs($outsider);

    foreach (companyRoutes() as [$method, $uri]) {
        $url = '/' . fillUri($uri, $this->hotel->slug);
        $status = test()->json($method, $url)->status();
        // "me/workspaces"-like rotas globais não estão neste prefixo; aqui 2xx/5xx significa vazamento ou falha
        expect($status)->toBeIn([401, 403, 404, 405, 422], "{$method} {$uri} respondeu {$status} para alguém de fora");
    }
});

it('denies HR, management and board actions to a regular employee, and changes nothing', function () {
    Sanctum::actingAs($this->bruno);
    $other = $this->ana->uuid;
    $denied = fn ($r) => expect($r->status())->toBeIn([403, 404, 422], 'linha ' . debug_backtrace()[0]['line'] . ' respondeu ' . $r->status());

    // gestão de pessoas e estrutura
    $denied($this->patchJson("{$this->hbase}/members/{$other}", ['job_title' => 'Diretor']));
    $denied($this->patchJson("{$this->hbase}/members/{$other}/department", ['department_uuid' => $this->dept->uuid]));
    $denied($this->postJson("{$this->hbase}/departments", ['name' => 'Novo']));
    $denied($this->putJson("{$this->hbase}/departments/{$this->dept->uuid}", ['name' => 'Hackeado']));
    $denied($this->deleteJson("{$this->hbase}/departments/{$this->dept->uuid}"));
    $denied($this->postJson("{$this->hbase}/chat-groups", ['name' => 'Grupo']));
    $denied($this->postJson("{$this->hbase}/segment-template", ['segment' => 'hotel']));
    // papéis, segurança, auditoria, diretoria
    $denied($this->getJson("{$this->base}/roles"));
    $denied($this->patchJson("{$this->base}/roles/{$this->bruno->uuid}", ['access_role' => 'owner']));
    $denied($this->putJson("{$this->base}/security", ['require_2fa' => true]));
    $denied($this->getJson("{$this->base}/audit"));
    $denied($this->getJson("{$this->base}/audit/export"));
    $denied($this->getJson("{$this->base}/dashboard"));
    expect($this->getJson("{$this->base}/reports")->json('data'))->toBe([]); // lista só o que a pessoa pode gerar
    $denied($this->getJson("{$this->base}/reports/bank"));
    $denied($this->getJson("{$this->base}/invites"));
    $denied($this->postJson("{$this->base}/invites", ['email' => 'x@y.com']));
    // ficha, salário, carreira de terceiros
    $denied($this->getJson("{$this->base}/people/{$other}/record"));
    $denied($this->putJson("{$this->base}/people/{$other}/record", ['phone' => '1']));
    $denied($this->putJson("{$this->base}/people/{$other}/salary", ['salary' => 99999]));
    $denied($this->postJson("{$this->base}/people/{$other}/career", ['title' => 'CEO', 'started_on' => '2020-01-01']));
    // conteúdo e gestão do dia a dia
    $denied($this->postJson("{$this->base}/jobs", ['title' => 'Vaga', 'description' => 'x']));
    $denied($this->postJson("{$this->base}/feed", ['body' => 'Comunicado']));
    $denied($this->postJson("{$this->base}/feed/{$this->post1->uuid}/pin"));
    $denied($this->deleteJson("{$this->base}/feed/{$this->post1->uuid}"));
    $denied($this->getJson("{$this->base}/feed/{$this->post1->uuid}/acks"));
    $denied($this->postJson("{$this->base}/calendar/events", ['title' => 'Evento', 'date' => '2026-12-01']));
    $denied($this->postJson("{$this->base}/climate", ['title' => 'Pesquisa']));
    // folha, ponto, escalas, áreas
    $denied($this->postJson("{$this->base}/payslips", ['user_uuid' => $other, 'kind' => 'payslip', 'title' => 'x']));
    $denied($this->deleteJson("{$this->base}/payslips/{$this->slip->uuid}"));
    $denied($this->getJson("{$this->base}/time/team"));
    $denied($this->getJson("{$this->base}/time/bank/team"));
    $denied($this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $other, 'minutes' => 600, 'reason' => 'x']));
    $denied($this->postJson("{$this->base}/time/entries", ['user_uuid' => $other, 'type' => 'in', 'at' => '2026-11-01 08:00']));
    $denied($this->getJson("{$this->base}/time/devices"));
    $denied($this->putJson("{$this->base}/time/settings", ['geofence_required' => false]));
    $denied($this->postJson("{$this->base}/time/closings", ['month' => '2026-10']));
    $denied($this->postJson("{$this->base}/schedules", ['name' => 'Escala']));
    $denied($this->patchJson("{$this->base}/areas/{$this->dept->uuid}", ['head_uuid' => $this->bruno->uuid]));
    $denied($this->postJson("{$this->base}/delegations", ['to_uuid' => $other]));

    // nada mudou
    expect(HotelUser::where('user_id', $this->ana->id)->value('job_title'))->toBe('Analista')
        ->and($this->hotel->departments()->count())->toBe(1)->and($this->hotel->departments()->value('name'))->toBe('Recepção')
        ->and(HotelUser::where('user_id', $this->bruno->id)->where('hotel_id', $this->hotel->id)->first()->access_role)->toBeNull()
        ->and(Payslip::whereKey($this->slip->id)->exists())->toBeTrue()
        ->and(WorkspacePost::whereKey($this->post1->id)->exists())->toBeTrue();
});

it('does not hand out someone else payslip, signature or record', function () {
    Sanctum::actingAs($this->ana); // não é o dono do holerite
    $this->get("{$this->base}/payslips/{$this->slip->uuid}/download")->assertNotFound();
    $this->getJson("{$this->base}/payslips/{$this->slip->uuid}/signature")->assertNotFound();
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'x', 'accept' => true])->assertNotFound();
    expect($this->getJson("{$this->base}/payslips?user={$this->bruno->uuid}&all=1")->json('data.items'))->toBe([]);
});

it('does not treat a legacy role=admin with a non-admin access role as company admin', function () {
    Sanctum::actingAs($this->fakeAdmin); // role=admin, access_role=finance
    $uuid = $this->ana->uuid;
    expect($this->patchJson("{$this->hbase}/members/{$uuid}", ['job_title' => 'Promovido'])->status())->toBeIn([403, 404]);
    expect($this->postJson("{$this->hbase}/departments", ['name' => 'Fantasma'])->status())->toBeIn([403, 404]);
    expect($this->postJson("{$this->hbase}/chat-groups", ['name' => 'G'])->status())->toBeIn([403, 404, 422]);
    expect(HotelUser::where('user_id', $this->ana->id)->value('job_title'))->toBe('Analista');
});

it('keeps goals, development plans and requests of a person away from peers who are not their manager', function () {
    $goal = App\Models\Goal::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'owner_id' => $this->ana->id, 'set_by' => $this->lead->id,
        'title' => 'Meta da Ana', 'unit' => '%', 'target_value' => 10, 'current_value' => 1, 'status' => 'active', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    $plan = App\Models\DevelopmentPlan::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->ana->id, 'created_by' => $this->lead->id,
        'title' => 'PDI da Ana', 'objective' => 'x', 'period_start' => '2026-11-01', 'period_end' => '2027-11-01']);
    $req = App\Models\WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->ana->id, 'type' => 'vacation',
        'title' => 'Férias da Ana', 'description' => 'x', 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-20', 'status' => 'pending']);

    // colega sem relação hierárquica: não vê nem altera
    Sanctum::actingAs($this->bruno);
    expect($this->getJson("{$this->base}/goals/{$goal->uuid}")->status())->toBeIn([403, 404]);
    expect($this->patchJson("{$this->base}/goals/{$goal->uuid}", ['current_value' => 10])->status())->toBeIn([403, 404]);
    expect($this->postJson("{$this->base}/goals/{$goal->uuid}/updates", ['value' => 10])->status())->toBeIn([403, 404, 422]);
    expect($this->getJson("{$this->base}/development/{$plan->uuid}")->status())->toBeIn([403, 404]);
    expect($this->postJson("{$this->base}/development/{$plan->uuid}/notes", ['body' => 'oi'])->status())->toBeIn([403, 404, 422]);
    expect($this->getJson("{$this->base}/requests/{$req->uuid}")->status())->toBeIn([403, 404]);
    expect($this->postJson("{$this->base}/requests/{$req->uuid}/decision", ['decision' => 'approved'])->status())->toBeIn([403, 404, 422]);
    expect($this->postJson("{$this->base}/requests/{$req->uuid}/comments", ['body' => 'oi'])->status())->toBeIn([403, 404, 422]);
    expect($req->refresh()->status)->toBe('pending')->and($goal->refresh()->current_value)->toEqual(1);
    // listagens também não vazam
    expect(collect($this->getJson("{$this->base}/goals?scope=all")->json('data') ?? [])->pluck('uuid')->all())->not->toContain($goal->uuid);

    // quem é gestor da Ana vê e decide
    Sanctum::actingAs($this->lead);
    $this->getJson("{$this->base}/goals/{$goal->uuid}")->assertOk();
    $this->getJson("{$this->base}/requests/{$req->uuid}")->assertOk();
});
