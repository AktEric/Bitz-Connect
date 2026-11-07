<?php

/** Endurecimento de segurança: mass assignment de role, sanitização de imagens, seeders e CORS. */

use App\Models\User;
use App\Support\ImageSanitizer;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** JPEG mínimo com APP1/EXIF (orientação 6 + GPS fictício), comentário e dados de imagem. */
function jpegWithExif(): string
{
    $tiff = "II*\x00\x08\x00\x00\x00"
        . "\x01\x00" . "\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00" . "\x00\x00\x00\x00"
        . 'GPS-LAT-23.55,-46.63';
    $exif = "Exif\0\0" . $tiff;
    $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
    $com  = "\xFF\xFE" . pack('n', 2 + 12) . 'segredo-camera';
    $app0 = "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    return "\xFF\xD8" . $app0 . $app1 . $com . "\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00" . 'PIXELS' . "\xFF\xD9";
}

it('does not let users mass-assign the platform role', function () {
    $user = User::factory()->create();
    $user->fill(['name' => 'Novo Nome', 'role' => 'platform_admin'])->save();

    expect($user->fresh()->role)->toBe('user')->and($user->fresh()->name)->toBe('Novo Nome');
});

it('strips EXIF and comments from JPEGs but keeps the orientation and the pixels', function () {
    $clean = ImageSanitizer::clean(jpegWithExif());

    expect($clean)->not->toContain('GPS-LAT')->not->toContain('segredo-camera')
        ->toContain('PIXELS')->toContain('JFIF')
        ->and(str_starts_with($clean, "\xFF\xD8"))->toBeTrue()
        ->and(str_ends_with($clean, "\xFF\xD9"))->toBeTrue();
    // orientação 6 preservada num EXIF mínimo (big-endian)
    expect($clean)->toContain("\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06");
});

it('strips textual and EXIF chunks from PNGs', function () {
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', str_repeat("\x00", 13)) . $chunk('tEXt', "Comment\0gps-secreto")
        . $chunk('eXIf', 'exif-secreto') . $chunk('IDAT', 'dados') . $chunk('IEND', '');

    $clean = ImageSanitizer::clean($png);

    expect($clean)->not->toContain('gps-secreto')->not->toContain('exif-secreto')->toContain('IDAT')->toContain('IEND');
});

it('stores sanitized uploads on the requested disk with a random name', function () {
    Storage::fake('public');
    $file = UploadedFile::fake()->createWithContent('foto.jpg', jpegWithExif());

    $path = ImageSanitizer::store($file, 'posts', 'public');

    expect($path)->toStartWith('posts/')->and(Storage::disk('public')->get($path))->not->toContain('GPS-LAT');
});

it('refuses to seed the default admin account in production', function () {
    $this->app['env'] = 'production';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');

    expect(fn () => (new AdminUserSeeder())->run())->toThrow(RuntimeException::class);
    expect(User::where('email', AdminUserSeeder::EMAIL)->exists())->toBeFalse();
});

it('only allows configured origins through CORS', function () {
    $origins = config('cors.allowed_origins');

    expect($origins)->not->toContain('*')->and($origins)->toBeArray();
});
