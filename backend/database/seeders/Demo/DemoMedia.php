<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Catálogo de mídia real (fotos Unsplash validadas manualmente por tema) e
 * utilitários de download/geração usados pelo DemoDataSeeder.
 *
 * Cada pool aponta para fotos que realmente retratam o tema — nada de imagem
 * aleatória: um post de A&B recebe foto de cozinha, um resort recebe foto de
 * piscina, e assim por diante.
 */
class DemoMedia
{
    /** @var array<string, array<int, string>> */
    private const POOLS = [
        // Fachadas, piscinas e áreas externas de resort/hotel
        'resort' => [
            '1566073771259-6a8506099945', '1571896349842-33c89424de2d', '1551882547-ff40c63fe5fa',
            '1520250497591-112f2f40a3f4', '1564501049412-61c2a3083791', '1542314831-068cd1dbfeeb',
            '1596436889106-be35e843f974', '1540541338287-41700207dee6', '1571003123894-1f0594d2b5d9',
            '1615460549969-36fa19521a4f',
        ],
        // Fachada urbana / lodge
        'facade' => ['1517840901100-8179e982acb7', '1600585154340-be6161a56a0c'],
        // Apartamentos e suítes
        'room' => [
            '1590490360182-c33d57733427', '1618773928121-c32242e63f39', '1582719478250-c89cae4dc85b',
            '1611892440504-42a792e24d32', '1578683010236-d716f9a3f461', '1631049307264-da0ec9d70304',
            '1584132915807-fd1f5fbc078f',
        ],
        // Restaurantes, salões e rooftops
        'restaurant' => [
            '1414235077428-338989a2e8c0', '1517248135467-4c7edcad34c4', '1552566626-52f8b828add9',
            '1550966871-3ed3cdb5ed0c', '1559339352-11d035aa65de',
        ],
        // Cozinha e pratos
        'food' => ['1577219491135-ce391730fb2c', '1600891964092-4316c288032e', '1504674900247-0877df9cc836'],
        'bar' => ['1470337458703-46ad1756a187'],
        'spa' => ['1544161515-4ab6ce6db874'],
        // Times, reuniões e escritório
        'team' => [
            '1521737604893-d14cc237f11d', '1522071820081-009f0129c71c', '1600880292203-757bb62b4baf',
            '1552664730-d307ca884978', '1556761175-b413da4baf72', '1497366754035-f200968a6e72',
        ],
        'analytics' => ['1560472354-b33ff0c44a43'],
        // Destinos (praia, montanha, deck)
        'destination' => [
            '1445019980597-93fa8acb246c', '1584132967334-10e028bd69f7', '1507525428034-b723cf961d3e',
            '1506929562872-bb421503ef21', '1464822759023-fed622ff2c3b', '1518602164578-cd0074062767',
        ],
    ];

    /** @var array<string, int> ponteiro rotativo por pool, evita repetir a mesma foto seguidamente */
    private array $cursor = [];

    /** @var array<string, string> cache url => path já salvo */
    private array $cache = [];

    private ?bool $hasFfmpeg = null;

    // ──────────────────────────────────────────────────────────
    // URLs
    // ──────────────────────────────────────────────────────────

    /** Próxima foto do pool, em tamanho arbitrário. */
    public function url(string $pool, int $w = 1200, int $h = 800): string
    {
        $ids = self::POOLS[$pool] ?? self::POOLS['resort'];
        $i = ($this->cursor[$pool] ?? 0) % count($ids);
        $this->cursor[$pool] = $i + 1;

        return "https://images.unsplash.com/photo-{$ids[$i]}?w={$w}&h={$h}&fit=crop&q=80";
    }

    public function avatarUrl(int $seed): string
    {
        return 'https://i.pravatar.cc/400?img=' . (($seed % 70) + 1);
    }

    public function logoUrl(string $name): string
    {
        $bg = substr(md5($name), 0, 6);

        return 'https://ui-avatars.com/api/?name=' . urlencode($name)
            . "&background={$bg}&color=fff&size=256&bold=true&length=2";
    }

    // ──────────────────────────────────────────────────────────
    // Download / storage
    // ──────────────────────────────────────────────────────────

    public function fetch(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 25, 'user_agent' => 'HotelConnectSeeder/1.0'],
            'https' => ['timeout' => 25],
        ]);
        $data = @file_get_contents($url, false, $ctx);

        return ($data === false || $data === '') ? null : $data;
    }

    /** Baixa e guarda no disco public; devolve o path relativo (ou null se falhar). */
    public function store(string $url, string $dir, string $ext = 'jpg'): ?string
    {
        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        $data = $this->fetch($url);
        if ($data === null) {
            return null;
        }

        $path = $dir . '/' . Str::random(24) . '.' . $ext;
        Storage::disk('public')->put($path, $data);
        $this->cache[$url] = $path;

        return $path;
    }

    /** Atalho: pega a próxima foto do pool e já salva. */
    public function storeFrom(string $pool, string $dir, int $w = 1200, int $h = 800): ?string
    {
        return $this->store($this->url($pool, $w, $h), $dir);
    }

    // ──────────────────────────────────────────────────────────
    // Vídeo
    // ──────────────────────────────────────────────────────────

    public function ffmpegAvailable(): bool
    {
        if ($this->hasFfmpeg === null) {
            $this->hasFfmpeg = (bool) trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        }

        return $this->hasFfmpeg;
    }

    /**
     * Monta um vídeo real (slideshow 1280x720) a partir de fotos do tema.
     * Devolve [video_path, duration_seconds] ou null quando o ffmpeg não existe.
     *
     * @param  array<int, string>  $pools  pools de onde tirar cada cena
     * @return array{0: string, 1: int}|null
     */
    public function makeSlideshow(array $pools, int $secondsPerScene = 4): ?array
    {
        if (! $this->ffmpegAvailable()) {
            return null;
        }

        $tmpDir = sys_get_temp_dir() . '/demo-video-' . Str::random(8);
        @mkdir($tmpDir, 0777, true);

        $files = [];
        foreach ($pools as $i => $pool) {
            $bytes = $this->fetch($this->url($pool, 1280, 720));
            if ($bytes === null) {
                continue;
            }
            $file = "{$tmpDir}/{$i}.jpg";
            file_put_contents($file, $bytes);
            $files[] = $file;
        }

        if (count($files) < 2) {
            $this->rmdir($tmpDir);

            return null;
        }

        $inputs = '';
        $filters = '';
        $labels = '';
        foreach ($files as $i => $file) {
            $inputs .= ' -loop 1 -t ' . $secondsPerScene . ' -i ' . escapeshellarg($file);
            $filters .= "[{$i}:v]scale=1280:720:force_original_aspect_ratio=increase,"
                . "crop=1280:720,setsar=1,fps=25[v{$i}];";
            $labels .= "[v{$i}]";
        }
        $filters .= $labels . 'concat=n=' . count($files) . ':v=1:a=0[out]';

        $relative = 'videos/' . Str::random(24) . '.mp4';
        $absolute = Storage::disk('public')->path($relative);
        @mkdir(dirname($absolute), 0777, true);

        $cmd = 'ffmpeg -y' . $inputs
            . ' -filter_complex ' . escapeshellarg($filters)
            . ' -map "[out]" -c:v libx264 -preset veryfast -crf 26 -pix_fmt yuv420p -movflags +faststart '
            . escapeshellarg($absolute) . ' 2>/dev/null';

        @shell_exec($cmd);
        $this->rmdir($tmpDir);

        if (! is_file($absolute) || filesize($absolute) < 1000) {
            return null;
        }

        return [$relative, count($files) * $secondsPerScene];
    }

    private function rmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
