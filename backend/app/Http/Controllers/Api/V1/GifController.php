<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Busca de GIFs (Giphy) pelo servidor: a chave nunca vai para o navegador. Sem chave, o recurso fica desligado. */
class GifController extends Controller
{
    use ApiResponseTrait;

    /** GET /chat/gifs?q= — sem q, traz os GIFs em alta. */
    public function search(Request $request): JsonResponse
    {
        $key = config('services.giphy.key');
        if (! $key) {
            return $this->success('GIFs desativados.', ['enabled' => false, 'items' => []]);
        }
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 60));

        $items = Cache::remember('giphy:' . md5($q), 600, function () use ($key, $q) {
            $res = Http::timeout(6)->get('https://api.giphy.com/v1/gifs/' . ($q === '' ? 'trending' : 'search'), array_filter([
                'api_key' => $key, 'q' => $q ?: null, 'limit' => 24, 'rating' => 'pg-13', 'lang' => 'pt',
            ]));
            if (! $res->ok()) {
                return null;
            }

            return collect($res->json('data', []))->map(fn ($g) => [
                'id'      => $g['id'] ?? null,
                'title'   => $g['title'] ?? 'GIF',
                'url'     => $g['images']['fixed_height']['url'] ?? null,
                'preview' => $g['images']['fixed_height_small']['url'] ?? ($g['images']['fixed_height']['url'] ?? null),
            ])->filter(fn ($g) => $g['url'] && preg_match('#^https://media[0-9]*\.giphy\.com/#', $g['url']))->values()->all();
        });

        return $items === null
            ? $this->error('Não foi possível buscar GIFs agora.', [], 502)
            : $this->success('GIFs carregados.', ['enabled' => true, 'items' => $items]);
    }
}
