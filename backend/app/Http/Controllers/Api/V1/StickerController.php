<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Services\Stickers;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Sticker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Figurinhas próprias: minha coleção, criar, salvar a de outra pessoa, remover da coleção e entregar a imagem. */
class StickerController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly Stickers $stickers) {}

    /** GET /stickers — minha coleção. */
    public function index(Request $request): JsonResponse
    {
        return $this->success('Figurinhas carregadas.', ['items' => array_map(fn ($u) => ['uuid' => $u], $this->stickers->collectionOf($request->user())), 'max' => Stickers::MAX_PER_USER]);
    }

    /** POST /stickers (multipart: image) */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:1024']]);
        $result = $this->stickers->create($request->user(), $request->file('image'));
        if (is_string($result)) {
            return $this->error($result, ['image' => [$result]], 422);
        }

        return $this->created('Figurinha adicionada.', ['uuid' => $result->uuid]);
    }

    /** POST /stickers/{uuid}/save — guarda na minha coleção uma figurinha que recebi. */
    public function save(Request $request, string $uuid): JsonResponse
    {
        $sticker = Sticker::where('uuid', $uuid)->first();
        if (! $sticker || ! $this->stickers->seenIn($request->user(), $sticker)) {
            return $this->notFound('Figurinha não encontrada.');
        }
        if (! $this->stickers->holds($request->user(), $sticker)
            && DB::table('user_stickers')->where('user_id', $request->user()->id)->count() >= Stickers::MAX_PER_USER) {
            return $this->error('Sua coleção está cheia. Remova alguma figurinha antes de salvar.', [], 422);
        }
        $this->stickers->hold($request->user(), $sticker);

        return $this->success('Figurinha salva na sua coleção.', ['uuid' => $sticker->uuid]);
    }

    /** DELETE /stickers/{uuid} — tira da MINHA coleção (as conversas que já a usam continuam mostrando). */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $sticker = Sticker::where('uuid', $uuid)->first();
        if (! $sticker || ! $this->stickers->holds($request->user(), $sticker)) {
            return $this->notFound('Figurinha não encontrada.');
        }
        DB::table('user_stickers')->where('user_id', $request->user()->id)->where('sticker_id', $sticker->id)->delete();

        return $this->success('Figurinha removida da sua coleção.');
    }

    /** GET /stickers/{uuid}/image — só para quem tem a figurinha ou a recebeu numa conversa. */
    public function image(Request $request, string $uuid): BinaryFileResponse|JsonResponse
    {
        $sticker = Sticker::where('uuid', $uuid)->first();
        if (! $sticker || ! $this->stickers->canView($request->user(), $sticker) || ! Storage::disk('local')->exists($sticker->path)) {
            return $this->notFound('Figurinha não encontrada.');
        }

        return response()->file(Storage::disk('local')->path($sticker->path), [
            'Content-Type' => $sticker->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
