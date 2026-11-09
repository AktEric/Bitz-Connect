<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Marketplace\Services\ListingPresenter;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\MarketplaceImage;
use App\Models\MarketplaceLike;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceRepost;
use App\Models\WorkspacePost;
use App\Services\NotificationService;
use App\Support\ImageSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Marketplace interno: colaboradores anunciam itens para os colegas da mesma empresa.
 * Dono (e RH, que modera) controlam o anúncio; todo mundo da empresa vê, curte, reposta e chama no privado.
 */
class MarketplaceController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly ListingPresenter $presenter,
    ) {
        parent::__construct($access, $notifications);
    }

    private function canModerate(?HotelUser $m): bool
    {
        return $m !== null && $this->access->isAdmin($m);
    }

    private function find(Hotel $hotel, string $uuid): ?MarketplaceListing
    {
        return MarketplaceListing::with(['images', 'seller'])->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
    }

    /** Anúncio oculto só aparece para o vendedor e para quem modera. */
    private function visibleTo(MarketplaceListing $l, Request $request, ?HotelUser $m): bool
    {
        return $l->status !== 'hidden' || $l->seller_id === $request->user()->id || $this->canModerate($m);
    }

    private function rules(bool $creating): array
    {
        $cats  = array_keys(config('marketplace.categories'));
        $conds = array_keys(config('marketplace.conditions'));

        return [
            'title'       => [$creating ? 'required' : 'sometimes', 'string', 'min:3', 'max:120'],
            'description' => [$creating ? 'required' : 'sometimes', 'string', 'min:5', 'max:3000'],
            'price'       => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'category'    => [$creating ? 'required' : 'sometimes', Rule::in($cats)],
            'condition'   => [$creating ? 'required' : 'sometimes', Rule::in($conds)],
            'location'    => ['nullable', 'string', 'max:80'],
        ];
    }

    /** GET /marketplace — grade com busca, filtros e ordenação. */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $meId = $request->user()->id;

        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:80'], 'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'condition' => ['nullable', Rule::in(array_keys(config('marketplace.conditions')))],
            'min' => ['nullable', 'numeric', 'min:0'], 'max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(['recent', 'price_asc', 'price_desc', 'popular'])],
            'scope' => ['nullable', Rule::in(['all', 'mine', 'liked'])], 'seller' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['active', 'sold'])],
        ]);
        $scope = $f['scope'] ?? 'all';

        $q = MarketplaceListing::with(['images', 'seller'])->where('hotel_id', $hotel->id);
        $scope === 'mine'
            ? $q->where('seller_id', $meId)
            : $q->where(fn (Builder $w) => $w->where('status', '!=', 'hidden')->orWhere('seller_id', $meId)->when($this->canModerate($m), fn ($x) => $x->orWhereRaw('true')));
        if ($scope === 'liked') {
            $q->whereHas('likes', fn ($l) => $l->where('user_id', $meId));
        }
        // por padrão só o que está à venda; "Meus anúncios" mostra tudo (inclusive vendidos)
        if (isset($f['status'])) {
            $q->where('status', $f['status']);
        } elseif ($scope === 'all') {
            $q->where('status', 'active');
        }
        if (! empty($f['seller'])) {
            $q->whereHas('seller', fn ($s) => $s->where('uuid', $f['seller']));
        }
        if (! empty($f['category'])) {
            $q->where('category', $f['category']);
        }
        if (! empty($f['condition'])) {
            $q->where('condition', $f['condition']);
        }
        if (isset($f['min'])) {
            $q->where('price_cents', '>=', (int) round($f['min'] * 100));
        }
        if (isset($f['max'])) {
            $q->where('price_cents', '<=', (int) round($f['max'] * 100));
        }
        if (! empty($f['q'])) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($f['q'])) . '%';
            $q->where(fn (Builder $w) => $w->whereRaw('unaccent(title) ILIKE unaccent(?)', [$like])->orWhereRaw('unaccent(description) ILIKE unaccent(?)', [$like]));
        }

        match ($f['sort'] ?? 'recent') {
            'price_asc'  => $q->orderByRaw('price_cents asc nulls last')->orderByDesc('id'),
            'price_desc' => $q->orderByRaw('price_cents desc nulls last')->orderByDesc('id'),
            'popular'    => $q->orderByDesc('likes_count')->orderByDesc('id'),
            default      => $q->orderByDesc('created_at')->orderByDesc('id'),
        };

        $page  = $q->paginate((int) config('marketplace.per_page'));
        $liked = MarketplaceLike::where('user_id', $meId)->whereIn('listing_id', $page->pluck('id'))->pluck('listing_id')->all();

        return $this->success('Anúncios carregados.', [
            'items'      => $page->getCollection()->map(fn ($l) => $this->presenter->summary($l, $meId, $liked))->values(),
            'page'       => $page->currentPage(),
            'last_page'  => $page->lastPage(),
            'total'      => $page->total(),
            'categories' => config('marketplace.categories'),
            'conditions' => config('marketplace.conditions'),
            'max_images' => (int) config('marketplace.max_images'),
        ]);
    }

    /** GET /marketplace/{uuid} */
    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l || ! $this->visibleTo($l, $request, $m)) {
            return $this->notFound('Anúncio não encontrado.');
        }
        if ($l->seller_id !== $request->user()->id) {
            MarketplaceListing::whereKey($l->id)->increment('views_count');
            $l->views_count++;
        }

        return $this->success('Anúncio carregado.', $this->presenter->detail($l, $request->user()->id, $this->canModerate($m)));
    }

    /** POST /marketplace (multipart: images[]) */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $max = (int) config('marketplace.max_images');
        $data = $request->validate($this->rules(true) + [
            'images'   => ['required', 'array', 'min:1', "max:{$max}"],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], ['images.required' => 'Adicione pelo menos uma foto.', 'images.min' => 'Adicione pelo menos uma foto.']);

        $active = MarketplaceListing::where('hotel_id', $hotel->id)->where('seller_id', $request->user()->id)->where('status', 'active')->count();
        if ($active >= (int) config('marketplace.max_active_per_user')) {
            return $this->error('Você atingiu o limite de anúncios ativos. Marque algum como vendido ou remova.', [], 422);
        }

        $listing = DB::transaction(function () use ($request, $hotel, $data) {
            $l = MarketplaceListing::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'seller_id' => $request->user()->id,
                'title' => trim($data['title']), 'description' => trim($data['description']),
                'price_cents' => isset($data['price']) ? (int) round($data['price'] * 100) : null,
                'category' => $data['category'], 'condition' => $data['condition'],
                'location' => ($data['location'] ?? null) ? trim($data['location']) : null, 'status' => 'active',
            ]);
            foreach (array_values($request->file('images', [])) as $i => $file) {
                $this->storeImage($l, $hotel, $file, $i);
            }

            return $l;
        });

        return $this->created('Anúncio publicado.', $this->presenter->detail($this->find($hotel, $listing->uuid), $request->user()->id, $this->canModerate($m)));
    }

    private function storeImage(MarketplaceListing $l, Hotel $hotel, $file, int $position): void
    {
        MarketplaceImage::create([
            'uuid' => (string) Str::uuid(), 'listing_id' => $l->id,
            // EXIF/GPS removidos; disco privado.
            'path' => ImageSanitizer::store($file, "marketplace/{$hotel->id}", 'local'),
            'mime' => $file->getMimeType() ?: 'image/jpeg', 'size' => $file->getSize(), 'position' => $position,
        ]);
    }

    /** PATCH /marketplace/{uuid} — dono edita; moderador só oculta/reativa. */
    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l) {
            return $this->notFound('Anúncio não encontrado.');
        }
        $mine = $l->seller_id === $request->user()->id;
        $mod  = $this->canModerate($m);
        if (! $mine && ! $mod) {
            return $this->forbidden('Só quem anunciou pode editar.');
        }

        $data = $request->validate($this->rules(false) + ['status' => ['sometimes', Rule::in($mine ? ['active', 'sold'] : ['active', 'hidden'])]]);
        if (! $mine) {
            $data = array_intersect_key($data, ['status' => 1]); // moderador não reescreve o anúncio dos outros
        }

        $changes = array_intersect_key($data, array_flip(['title', 'description', 'category', 'condition', 'location']));
        if (array_key_exists('price', $data)) {
            $changes['price_cents'] = $data['price'] === null ? null : (int) round($data['price'] * 100);
        }
        if (isset($data['status'])) {
            $changes['status']  = $data['status'];
            $changes['sold_at'] = $data['status'] === 'sold' ? now() : null;
        }
        $l->update($changes);

        if (! $mine && isset($data['status'])) {
            AuditLog::record($request, $hotel, 'marketplace.moderated', $l->seller, ['listing' => $l->uuid, 'status' => $data['status']]);
        }

        return $this->success('Anúncio atualizado.', $this->presenter->detail($this->find($hotel, $uuid), $request->user()->id, $mod));
    }

    /** DELETE /marketplace/{uuid} */
    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l) {
            return $this->notFound('Anúncio não encontrado.');
        }
        $mine = $l->seller_id === $request->user()->id;
        if (! $mine && ! $this->canModerate($m)) {
            return $this->forbidden('Só quem anunciou pode remover.');
        }
        if (! $mine) {
            AuditLog::record($request, $hotel, 'marketplace.removed', $l->seller, ['listing' => $l->uuid, 'title' => $l->title]);
        }
        $l->delete();

        return $this->success('Anúncio removido.');
    }

    /** POST /marketplace/{uuid}/images — o dono acrescenta fotos (respeitando o limite). */
    public function addImages(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l || $l->seller_id !== $request->user()->id) {
            return $this->notFound('Anúncio não encontrado.');
        }
        $room = (int) config('marketplace.max_images') - $l->images->count();
        $request->validate(['images' => ['required', 'array', 'min:1', 'max:' . max(1, $room)], 'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        if ($room < 1) {
            return $this->error('O anúncio já tem o máximo de fotos.', [], 422);
        }
        $pos = (int) $l->images->max('position') + 1;
        foreach (array_values($request->file('images')) as $i => $file) {
            $this->storeImage($l, $hotel, $file, $pos + $i);
        }

        return $this->success('Fotos adicionadas.', $this->presenter->detail($this->find($hotel, $uuid), $request->user()->id, $this->canModerate($m)));
    }

    /** DELETE /marketplace/{uuid}/images/{img} — sempre sobra pelo menos uma foto. */
    public function removeImage(Request $request, Hotel $hotel, string $uuid, string $img): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l || $l->seller_id !== $request->user()->id) {
            return $this->notFound('Anúncio não encontrado.');
        }
        $image = $l->images->firstWhere('uuid', $img);
        if (! $image) {
            return $this->notFound('Foto não encontrada.');
        }
        if ($l->images->count() <= 1) {
            return $this->error('O anúncio precisa de pelo menos uma foto.', [], 422);
        }
        Storage::disk('local')->delete($image->path);
        $image->delete();

        return $this->success('Foto removida.', $this->presenter->detail($this->find($hotel, $uuid), $request->user()->id, $this->canModerate($m)));
    }

    /** POST /marketplace/{uuid}/like — alterna a curtida. */
    public function like(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l || ! $this->visibleTo($l, $request, $m)) {
            return $this->notFound('Anúncio não encontrado.');
        }
        $meId = $request->user()->id;

        $liked = DB::transaction(function () use ($l, $meId) {
            $existing = MarketplaceLike::where('listing_id', $l->id)->where('user_id', $meId)->first();
            if ($existing) {
                $existing->delete();
                $l->decrement('likes_count');

                return false;
            }
            MarketplaceLike::create(['listing_id' => $l->id, 'user_id' => $meId]);
            $l->increment('likes_count');

            return true;
        });

        if ($liked && $l->seller_id !== $meId && $l->seller) {
            $this->notifications->workspaceNotice($l->seller, $request->user(), 'workspace_reaction', "{$request->user()->name} curtiu seu anúncio", Str::limit($l->title, 100), $this->link($hotel, "marketplace?anuncio={$l->uuid}"));
        }

        return $this->success('Curtida atualizada.', ['liked' => $liked, 'likes_count' => $l->refresh()->likes_count]);
    }

    /** POST /marketplace/{uuid}/repost { comment? } — publica o anúncio no mural (uma vez por pessoa). */
    public function repost(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $l = $m ? $this->find($hotel, $uuid) : null;
        if (! $l || $l->status !== 'active') {
            return $this->notFound('Anúncio não encontrado ou já vendido.');
        }
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        $meId = $request->user()->id;

        if (MarketplaceRepost::where('listing_id', $l->id)->where('user_id', $meId)->exists()) {
            return $this->error('Você já repostou este anúncio.', [], 422);
        }

        $post = DB::transaction(function () use ($l, $hotel, $meId, $data) {
            $post = WorkspacePost::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'author_id' => $meId, 'kind' => 'listing',
                'listing_id' => $l->id, 'body' => trim((string) ($data['comment'] ?? '')),
            ]);
            MarketplaceRepost::create(['listing_id' => $l->id, 'user_id' => $meId, 'post_id' => $post->id]);
            $l->increment('reposts_count');

            return $post;
        });

        return $this->created('Anúncio repostado no mural.', ['post_uuid' => $post->uuid, 'reposts_count' => $l->refresh()->reposts_count]);
    }

    /** GET /marketplace-media/{uuid} (rota pública, mas só com URL assinada e válida). */
    public function media(string $uuid): BinaryFileResponse|JsonResponse
    {
        $img = MarketplaceImage::with('listing')->where('uuid', $uuid)->first();
        if (! $img || ! $img->listing || ! Storage::disk('local')->exists($img->path)) {
            return $this->notFound('Foto não encontrada.');
        }

        return response()->file(Storage::disk('local')->path($img->path), [
            'Content-Type' => $img->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=1500',
        ]);
    }
}
