<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /notifications?category=feed&unread=1&per_page=20
     * Returns paginated notifications for the authenticated user.
     * Accepts optional filters: category, unread (boolean).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('actor.subscriptions')
            ->where('user_id', $request->user()->id);

        // Filter by category: connection|message|feed|job|hotel|profile|system
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        // Filter unread only
        if ($request->boolean('unread')) {
            $query->where('read', false);
        }

        $perPage       = min((int) ($request->query('per_page', 20)), 50);
        $paginator     = $query->latest()->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($n) => new NotificationResource($n))
        );

        return $this->paginated('Notificações carregadas.', $paginator);
    }

    /**
     * GET /notifications/unread-count
     * Returns total unread count and per-category breakdown.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $total = Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        $byCategory = Notification::where('user_id', $userId)
            ->where('read', false)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return $this->success('OK', [
            'count'       => $total,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * PATCH /notifications/{notification}/read
     * Marks a single notification as read.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $notification->update(['read' => true]);

        return $this->success('Notificação marcada como lida.');
    }

    /**
     * PATCH /notifications/read-all?category=feed
     * Marks all (or all in a category) as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->where('read', false);

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $query->update(['read' => true]);

        return $this->success('Notificações marcadas como lidas.');
    }

    /**
     * DELETE /notifications/{notification}
     * Permanently deletes a single notification (owner only).
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $notification->delete();

        return $this->success('Notificação excluída.');
    }
}
