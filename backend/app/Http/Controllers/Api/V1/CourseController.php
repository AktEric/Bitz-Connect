<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Course\DTOs\CreateCourseDTO;
use App\Domain\Course\Services\CourseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Course\CourseRequest;
use App\Http\Requests\Course\LessonRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\CourseLessonResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cursos (§11). Criação/edição exclusiva de Consultores PRO (create_courses);
 * matrícula gratuita no MVP.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CourseService $courses,
    ) {}

    /** GET /courses — catálogo de cursos publicados. */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->courses->catalog(12, $request->query('category'), $request->query('q'));
        $paginator->setCollection($paginator->getCollection()->map(fn (Course $c) => new CourseResource($c)));

        return $this->paginated('Cursos carregados com sucesso.', $paginator);
    }

    /** GET /courses/{course} — detalhe + aulas (conteúdo bloqueado se não matriculado). */
    public function show(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $course->load(['lessons', 'user.subscriptions'])->loadCount('reviews')->loadAvg('reviews', 'rating');

        $isOwner    = $user && $course->user_id === $user->id;
        $isEnrolled = $this->courses->isEnrolled($course, $user);
        $unlocked   = $isOwner || $isEnrolled;

        // Bloqueia conteúdo das aulas para quem não tem acesso.
        foreach ($course->lessons as $lesson) {
            $lesson->locked = ! $unlocked; // (each() pararia na 1ª aula quando o callback devolve false)
        }
        $course->setAttribute('is_enrolled', $isEnrolled);
        $course->setAttribute('is_owner', $isOwner);

        return $this->success('Curso carregado com sucesso.', new CourseResource($course));
    }

    /** POST /courses — cria curso (Consultor PRO). */
    public function store(CourseRequest $request): JsonResponse
    {
        if ($request->user()->cannot('create_courses')) {
            return $this->forbidden('A criação de cursos é exclusiva do ConnectHotel PRO - Consultor.');
        }

        $course = $this->courses->create(CreateCourseDTO::fromArray($request->validated(), $request->user()->id));

        return $this->created('Curso criado com sucesso.', new CourseResource($course->load('user.subscriptions')));
    }

    /** PUT /courses/{course} — edita (dono). */
    public function update(CourseRequest $request, Course $course): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $course)) {
            return $resp;
        }

        $course = $this->courses->update($course, $request->validated());

        return $this->success('Curso atualizado com sucesso.', new CourseResource($course->load('user.subscriptions')));
    }

    /** PATCH /courses/{course}/publish — publica (dono). */
    public function publish(Request $request, Course $course): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $course)) {
            return $resp;
        }

        $course = $this->courses->publish($course);

        return $this->success('Curso publicado com sucesso.', new CourseResource($course->load('user.subscriptions')));
    }

    // ── Aulas ────────────────────────────────────────────────

    public function addLesson(LessonRequest $request, Course $course): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $course)) {
            return $resp;
        }

        $lesson = $this->courses->addLesson($course, $request->validated());

        return $this->created('Aula adicionada com sucesso.', new CourseLessonResource($lesson));
    }

    public function updateLesson(LessonRequest $request, Course $course, CourseLesson $lesson): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $course)) {
            return $resp;
        }
        if ($lesson->course_id !== $course->id) {
            return $this->notFound('Aula não encontrada.');
        }

        $lesson = $this->courses->updateLesson($lesson, $request->validated());

        return $this->success('Aula atualizada com sucesso.', new CourseLessonResource($lesson));
    }

    public function deleteLesson(Request $request, Course $course, CourseLesson $lesson): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $course)) {
            return $resp;
        }
        if ($lesson->course_id !== $course->id) {
            return $this->notFound('Aula não encontrada.');
        }

        $this->courses->deleteLesson($lesson);

        return $this->success('Aula removida com sucesso.');
    }

    // ── Matrícula / listagens ────────────────────────────────

    /** POST /courses/{course}/enroll — matrícula gratuita (idempotente). */
    public function enroll(Request $request, Course $course): JsonResponse
    {
        if ($course->status !== 'published') {
            return $this->error('Este curso ainda não está disponível.', [], 422);
        }

        $created = $this->courses->enroll($course, $request->user());

        return $this->success(
            $created ? 'Matrícula realizada com sucesso.' : 'Você já está matriculado neste curso.',
            ['is_enrolled' => true, 'enrollments_count' => (int) $course->fresh()->enrollments_count],
        );
    }

    /** GET /me/courses — cursos em que o usuário está matriculado. */
    public function myCourses(Request $request): JsonResponse
    {
        $paginator = $this->courses->enrolledFor($request->user());
        $paginator->setCollection($paginator->getCollection()->map(fn (Course $c) => new CourseResource($c)));

        return $this->paginated('Seus cursos carregados com sucesso.', $paginator);
    }

    /** GET /users/{user}/courses — cursos publicados de um criador. */
    public function creatorCourses(User $user): JsonResponse
    {
        $paginator = $this->courses->byCreator($user);
        $paginator->setCollection($paginator->getCollection()->map(fn (Course $c) => new CourseResource($c)));

        return $this->paginated('Cursos do criador carregados com sucesso.', $paginator);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function denyIfNotOwner(Request $request, Course $course): ?JsonResponse
    {
        return $course->user_id === $request->user()->id
            ? null
            : $this->forbidden('Você não é o autor deste curso.');
    }
}
