<?php

/** Calendário: aniversários (privacidade), tempo de casa, feriados, férias, eventos, RSVP e assinatura ICS. */

use App\Domain\Workspace\Services\CalendarService;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function calMember(Hotel $hotel, string $role = 'employee', ?User $manager = null, ?Department $dept = null, array $extra = []): User
{
    $user = User::factory()->create();
    HotelUser::create(array_merge([
        'hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved',
        'manager_id' => $manager?->id, 'department_id' => $dept?->id,
    ], $extra));

    return $user;
}

function calItems($test, string $from, string $to, ?string $type = null): array
{
    $items = $test->getJson("{$test->base}/calendar?from={$from}&to={$to}")->assertOk()->json('data.items');

    return $type ? array_values(array_filter($items, fn ($i) => $i['type'] === $type)) : $items;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->ops   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $this->fin   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Financeiro']);

    $this->rh      = calMember($this->hotel, 'admin');
    $this->manager = calMember($this->hotel, 'employee', null, $this->ops);
    $this->worker  = calMember($this->hotel, 'employee', $this->manager, $this->ops);
    $this->finance = calMember($this->hotel, 'employee', null, $this->fin);
});

afterEach(fn () => Carbon::setTestNow());

it('computes national holidays, including the ones that move with Easter', function () {
    $svc = app(CalendarService::class);

    expect($svc->easter(2026)->toDateString())->toBe('2026-04-05')->and($svc->easter(2025)->toDateString())->toBe('2025-04-20');
    $h = $svc->nationalHolidays(2026);
    expect($h)->toHaveKey('2026-02-17')->toHaveKey('2026-04-03')->toHaveKey('2026-06-04')->toHaveKey('2026-11-20')->toHaveKey('2026-12-25');

    Sanctum::actingAs($this->worker);
    $titles = array_column(calItems($this, '2026-11-01', '2026-11-30', 'holiday'), 'title');
    expect($titles)->toContain('Finados')->toContain('Proclamação da República')->toContain('Consciência Negra');
});

it('shows birthdays without exposing the year and respects the privacy choice', function () {
    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/calendar/birthdays/{$this->worker->uuid}", ['birth_date' => '1990-11-10'])->assertOk();
    Sanctum::actingAs($this->worker);
    $this->patchJson("{$this->base}/calendar/me", ['birthday_visible' => true])->assertOk();

    Sanctum::actingAs($this->finance);
    $res = $this->getJson("{$this->base}/calendar?from=2026-11-01&to=2026-11-30")->assertOk();
    $b = array_values(array_filter($res->json('data.items'), fn ($i) => $i['type'] === 'birthday'));
    expect($b)->toHaveCount(1)->and($b[0]['date'])->toBe('2026-11-10');
    expect($res->getContent())->not->toContain('1990');

    // a pessoa esconde: colegas não veem; ela mesma e o RH continuam vendo
    Sanctum::actingAs($this->worker);
    $this->patchJson("{$this->base}/calendar/me", ['birthday_visible' => false])->assertOk();
    expect(calItems($this, '2026-11-01', '2026-11-30', 'birthday'))->toHaveCount(1);
    Sanctum::actingAs($this->finance);
    expect(calItems($this, '2026-11-01', '2026-11-30', 'birthday'))->toHaveCount(0);
    Sanctum::actingAs($this->rh);
    expect(calItems($this, '2026-11-01', '2026-11-30', 'birthday'))->toHaveCount(1);
});

it('moves 29 February birthdays to the 28th in non-leap years and validates dates', function () {
    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/calendar/birthdays/{$this->worker->uuid}", ['birth_date' => '1992-02-29'])->assertOk();
    Sanctum::actingAs($this->finance);
    $this->patchJson("{$this->base}/calendar/birthdays/{$this->worker->uuid}", ['birth_date' => '1992-02-29'])->assertForbidden();

    Sanctum::actingAs($this->worker);
    $b = calItems($this, '2026-02-01', '2026-02-28', 'birthday');
    expect($b)->toHaveCount(1)->and($b[0]['date'])->toBe('2026-02-28');

    // a pessoa não consegue mudar a própria data (é o RH que cadastra): o campo é ignorado
    $this->patchJson("{$this->base}/calendar/me", ['birth_date' => '2000-01-01'])->assertOk();
    expect(calItems($this, '2026-01-01', '2026-01-31', 'birthday'))->toHaveCount(0);
    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/calendar/birthdays/{$this->worker->uuid}", ['birth_date' => '2999-01-01'])->assertStatus(422);
    $this->getJson("{$this->base}/calendar?from=2026-01-01&to=2026-12-31")->assertStatus(422); // mais de 100 dias
});

it('lists work anniversaries only from the first year on', function () {
    HotelUser::where('user_id', $this->worker->id)->update(['hired_at' => '2021-11-12']);
    HotelUser::where('user_id', $this->finance->id)->update(['hired_at' => '2026-11-12']);

    Sanctum::actingAs($this->finance);
    $a = calItems($this, '2026-11-01', '2026-11-30', 'anniversary');
    expect($a)->toHaveCount(1)->and($a[0]['subtitle'])->toBe('5 anos de empresa')->and($a[0]['date'])->toBe('2026-11-12');
});

it('shows approved vacations only to the person, their managers and RH', function () {
    WorkspaceRequest::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->worker->id,
        'type' => 'vacation', 'title' => 'Férias', 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-27', 'status' => 'approved',
    ]);

    foreach ([[$this->worker, 1], [$this->manager, 1], [$this->rh, 1], [$this->finance, 0]] as [$who, $expected]) {
        Sanctum::actingAs($who);
        expect(calItems($this, '2026-11-01', '2026-11-30', 'vacation'))->toHaveCount($expected);
    }
    Sanctum::actingAs($this->worker);
    expect(calItems($this, '2026-11-01', '2026-11-30', 'vacation')[0]['end_date'])->toBe('2026-11-27');
});

it('lets RH and managers create events with audience by area, and RSVP counts', function () {
    Sanctum::actingAs($this->finance);
    $this->postJson("{$this->base}/calendar/events", ['title' => 'Reunião', 'starts_on' => '2026-11-12'])->assertForbidden();

    Sanctum::actingAs($this->manager);
    $uuid = $this->postJson("{$this->base}/calendar/events", [
        'title' => 'Treinamento de segurança', 'starts_on' => '2026-11-12', 'starts_time' => '09:00', 'ends_time' => '11:00',
        'location' => 'Sala 2', 'department_uuid' => $this->ops->uuid,
    ])->assertCreated()->json('data.uuid');
    $this->postJson("{$this->base}/calendar/events", ['title' => 'Feriado', 'kind' => 'holiday', 'starts_on' => '2026-11-13'])->assertForbidden(); // feriado só RH

    // quem é do setor vê e confirma presença; quem é de outro setor não vê nem confirma
    Sanctum::actingAs($this->worker);
    $e = calItems($this, '2026-11-01', '2026-11-30', 'event');
    expect($e)->toHaveCount(1)->and($e[0]['time'])->toBe('09:00–11:00')->and($e[0]['event']['can_edit'])->toBeFalse();
    $this->postJson("{$this->base}/calendar/events/{$uuid}/rsvp", ['status' => 'going'])->assertOk()->assertJsonPath('data.going', 1);
    $this->postJson("{$this->base}/calendar/events/{$uuid}/rsvp", ['status' => 'bogus'])->assertStatus(422);

    Sanctum::actingAs($this->finance);
    expect(calItems($this, '2026-11-01', '2026-11-30', 'event'))->toHaveCount(0);
    $this->postJson("{$this->base}/calendar/events/{$uuid}/rsvp", ['status' => 'going'])->assertNotFound();

    // RH vê tudo, cadastra feriado da empresa, e só autor/RH removem
    Sanctum::actingAs($this->rh);
    expect(calItems($this, '2026-11-01', '2026-11-30', 'event'))->toHaveCount(1);
    $this->postJson("{$this->base}/calendar/events", ['title' => 'Aniversário da cidade', 'kind' => 'holiday', 'starts_on' => '2026-11-25'])->assertCreated();
    expect(array_column(calItems($this, '2026-11-20', '2026-11-30', 'holiday'), 'title'))->toContain('Aniversário da cidade');

    Sanctum::actingAs($this->worker);
    $this->deleteJson("{$this->base}/calendar/events/{$uuid}")->assertNotFound();
    Sanctum::actingAs($this->manager);
    $this->deleteJson("{$this->base}/calendar/events/{$uuid}")->assertOk();
});

it('serves a personal ICS subscription that can be rotated', function () {
    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/calendar/birthdays/{$this->worker->uuid}", ['birth_date' => '1990-11-10'])->assertOk();
    Sanctum::actingAs($this->worker);
    $this->patchJson("{$this->base}/calendar/me", ['birthday_visible' => true])->assertOk();

    $url  = $this->getJson("{$this->base}/calendar/ics-link")->assertOk()->json('data.url');
    $path = parse_url($url, PHP_URL_PATH);
    expect($path)->toMatch('#/api/v1/calendar-feed/[A-Za-z0-9]{40}\.ics$#');
    expect($this->getJson("{$this->base}/calendar/ics-link")->json('data.url'))->toBe($url); // estável até renovar

    $this->app['auth']->forgetGuards();
    $ics = $this->get($path)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')->getContent();
    expect($ics)->toContain('BEGIN:VCALENDAR')->toContain('END:VCALENDAR')->toContain('Finados')->toContain('DTSTART;VALUE=DATE:20261110')->toContain("\r\n");
    $this->get('/api/v1/calendar-feed/' . str_repeat('a', 40) . '.ics')->assertNotFound();

    Sanctum::actingAs($this->worker);
    $new = $this->postJson("{$this->base}/calendar/ics-link")->assertOk()->json('data.url');
    expect($new)->not->toBe($url);
    $this->app['auth']->forgetGuards();
    $this->get($path)->assertNotFound();
    $this->get(parse_url($new, PHP_URL_PATH))->assertOk();
});

it('keeps the calendar to company members', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("{$this->base}/calendar")->assertForbidden();
    $this->getJson("{$this->base}/calendar/ics-link")->assertForbidden();
});
