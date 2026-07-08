<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Review\DTOs\CreateReviewDTO;
use App\Domain\Review\Services\ReviewService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Course;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avaliações polimórficas de criadores, cursos e mentorias (§6/§11).
 * O alvo é resolvido por {type}/{uuid}: creator|course|mentorship.
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
class ReviewController extends Controller
{
    use ApiResponseTrait;

    private const TYPES = [
        'creator'    => User::class,
        'course'     => Course::class,
        'mentorship' => Mentorship::class,
    ];

    public function __construct(
        private readonly ReviewService $reviews,
    ) {}

    /** GET /reviews/{type}/{uuid} — lista + resumo (média/total). */
    public function index(Request $request, string $type, string $uuid): JsonResponse
    {
        $reviewable = $this->resolve($type, $uuid);
        if (! $reviewable) {
            return $this->notFound('Alvo de avaliação inválido.');
        }

        $paginator = $this->reviews->listFor($reviewable);
        $paginator->setCollection($paginator->getCollection()->map(fn ($r) => new ReviewResource($r)));

        $summary = $this->reviews->summaryFor($reviewable);
        $myReview = $this->reviews->myReview($reviewable, $request->user());

        return $this->success('Avaliações carregadas com sucesso.', [
            'summary'    => $summary,
            'my_review'  => $myReview ? new ReviewResource($myReview->load('user.subscriptions')) : null,
            'can_review' => $request->user() ? $this->reviews->canReview($reviewable, $request->user()) : false,
            'reviews'    => $paginator->items(),
            'meta'       => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /reviews/{type}/{uuid} — cria ou atualiza a avaliação do usuário. */
    public function store(ReviewRequest $request, string $type, string $uuid): JsonResponse
    {
        $reviewable = $this->resolve($type, $uuid);
        if (! $reviewable) {
            return $this->notFound('Alvo de avaliação inválido.');
        }

        if (! $this->reviews->canReview($reviewable, $request->user())) {
            return $this->forbidden('Você precisa ter interagido com este ' . $this->label($type) . ' para avaliá-lo.');
        }

        $review = $this->reviews->upsert($reviewable, $request->user(), CreateReviewDTO::fromArray($request->validated()));

        return $this->success('Avaliação registrada com sucesso.', new ReviewResource($review));
    }

    private function resolve(string $type, string $uuid): ?Model
    {
        $class = self::TYPES[$type] ?? null;
        if (! $class) {
            return null;
        }

        return $class::where('uuid', $uuid)->first();
    }

    private function label(string $type): string
    {
        return match ($type) {
            'course'     => 'curso',
            'mentorship' => 'mentoria',
            default      => 'criador',
        };
    }
}
