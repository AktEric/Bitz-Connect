<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = [], array $u = []) => tap(User::factory()->create($u), fn ($x) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $x->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh     = $mk('admin', ['access_role' => 'hr']);
    $this->worker = $mk('employee', [], ['name' => 'Gabriel Rocha']);
    $this->other  = $mk('employee');
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";

    Storage::disk('local')->put("payslips/{$this->hotel->id}/a.pdf", '%PDF-1.4 holerite');
    $this->slip = Payslip::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->worker->id, 'uploaded_by' => $this->rh->id,
        'kind' => 'payslip', 'title' => 'Holerite out/2026', 'file_path' => "payslips/{$this->hotel->id}/a.pdf", 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 17]);
    // PNG 1x1 esticado: gerado com GD para ter dimensões válidas
    $im = imagecreatetruecolor(300, 100); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255)); ob_start(); imagepng($im); $this->png = 'data:image/png;base64,' . base64_encode(ob_get_clean());
});

it('lets the owner sign with a typed name that matches the registration', function () {
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'gabriel  ROCHA', 'accept' => true])
        ->assertOk()->assertJsonPath('data.signature_kind', 'typed');

    $s = $this->getJson("{$this->base}/payslips/{$this->slip->uuid}/signature")->assertOk()->json('data');
    expect($s['name'])->toBe('Gabriel Rocha')->and($s['hash'])->toHaveLength(64)->and($s['image'])->toBeNull();
    // não assina duas vezes
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'Gabriel Rocha', 'accept' => true])->assertStatus(409);
});

it('rejects a typed name that is not the person, and requires the acknowledgement', function () {
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'Outra Pessoa', 'accept' => true])->assertUnprocessable();
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'Gabriel Rocha'])->assertUnprocessable();
});

it('stores a drawn signature privately and validates the image', function () {
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'drawn', 'image' => 'data:image/png;base64,AAAA', 'accept' => true])->assertUnprocessable();
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'drawn', 'image' => $this->png, 'accept' => true])->assertOk();

    expect(Storage::disk('local')->exists("payslips/{$this->hotel->id}/signatures/{$this->slip->uuid}.png"))->toBeTrue();
    expect($this->getJson("{$this->base}/payslips/{$this->slip->uuid}/signature")->json('data.image'))->toStartWith('data:image/png;base64,');
});

it('only the owner signs; HR can verify but others cannot', function () {
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => $this->rh->name, 'accept' => true])->assertNotFound();
    Sanctum::actingAs($this->other);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => $this->other->name, 'accept' => true])->assertNotFound();

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/payslips/{$this->slip->uuid}/sign", ['kind' => 'typed', 'name' => 'Gabriel Rocha', 'accept' => true])->assertOk();

    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/payslips/{$this->slip->uuid}/signature")->assertOk();
    $list = $this->getJson("{$this->base}/payslips?all=1")->json('data.items.0');
    expect($list['signed_at'])->not->toBeNull();
    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/payslips/{$this->slip->uuid}/signature")->assertNotFound();
});
