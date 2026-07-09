<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CompanyAnalyticsController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CreatorController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\MentorshipController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VideoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /api/v1 (configured in bootstrap/app.php)
*/

Route::get('/health', fn () => response()->json(['success' => true, 'message' => 'OK', 'data' => []]));

// ──────────────────────────────────────────────────────────────
// Auth — public
// ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('/hotel-login',     [AuthController::class, 'hotelLogin']);
    Route::post('/register-hotel',  [AuthController::class, 'registerHotel']);
});

// ──────────────────────────────────────────────────────────────
// Public profiles (no auth required — Sanctum resolves optional user identity)
// ──────────────────────────────────────────────────────────────
// Public profiles (no auth required)
// is_following is resolved inside the controller via Auth::id() if token present
// ──────────────────────────────────────────────────────────────
Route::get('/users/{user}',                    [UserController::class, 'show']);
// Diretório de hotéis (aba Hotéis do feed) — deve vir ANTES de /hotels/{hotel}
Route::get('/hotels/directory',                [HotelController::class, 'directory']);
Route::get('/hotels/{hotel}',                  [HotelController::class, 'show']);

// Subscription plan catalog (public — for the pricing page)
Route::get('/plans',                           [SubscriptionController::class, 'plans']);

// Página pública do criador
Route::get('/creators/{user}',                 [CreatorController::class, 'show']);
Route::get('/hotels/{hotel}/jobs',             [HotelController::class, 'hotelJobs']);
Route::get('/hotels/{hotel}/posts',            [HotelController::class, 'hotelPosts']);
Route::get('/hotels/{hotel}/admins',           [HotelController::class, 'hotelAdmins']);
Route::get('/hotels/{hotel}/employees',        [HotelController::class, 'employees']);

// Jobs listing and detail are accessible to authenticated users (see auth group below)

// ──────────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout',   [AuthController::class, 'logout']);
        Route::put('/password',  [AuthController::class, 'changePassword']);
    });

    // User profile
    Route::get('/me',          [UserController::class, 'me']);
    Route::put('/me',          [UserController::class, 'update']);
    Route::post('/me/avatar',  [UserController::class, 'uploadAvatar']);
    Route::post('/me/banner',  [UserController::class, 'uploadBanner']);

    // Experiences
    Route::post('/me/experiences',          [UserController::class, 'addExperience']);
    Route::put('/me/experiences/{id}',      [UserController::class, 'updateExperience']);
    Route::delete('/me/experiences/{id}',   [UserController::class, 'deleteExperience']);

    // Subscriptions & entitlements
    Route::get('/me/subscription',          [SubscriptionController::class, 'mySubscription']);
    Route::post('/subscriptions',           [SubscriptionController::class, 'subscribe']);
    Route::delete('/subscriptions',         [SubscriptionController::class, 'cancel']);
    Route::patch('/admin/subscriptions',    [SubscriptionController::class, 'adminSetPlan']);

    // Hotels
    Route::post('/hotels',                    [HotelController::class, 'store']);
    Route::put('/hotels/{hotel}',             [HotelController::class, 'update']);
    Route::post('/hotels/{hotel}/logo',       [HotelController::class, 'uploadLogo']);
    Route::post('/hotels/{hotel}/cover',      [HotelController::class, 'uploadCover']);
    Route::get('/me/hotels',                  [HotelController::class, 'myHotels']);

    // Hotel — follow, employees, claim, roles
    Route::prefix('hotels/{hotel:slug}')->group(function () {
        Route::post('/follow',                          [HotelController::class, 'follow']);
        Route::delete('/follow',                        [HotelController::class, 'unfollow']);
        Route::post('/claim',                           [HotelController::class, 'claim']);
        Route::get('/pending-employees',                [HotelController::class, 'pendingEmployees']);
        Route::patch('/employees/{userId}/approve',     [HotelController::class, 'approveEmployee']);
        Route::patch('/employees/{userId}/reject',      [HotelController::class, 'rejectEmployee']);
        Route::patch('/promote/{userId}',               [HotelController::class, 'promote']);
        Route::patch('/demote/{userId}',                [HotelController::class, 'demote']);
        Route::get('/subscription',                     [SubscriptionController::class, 'hotelSubscription']);
        Route::get('/analytics',                         [CompanyAnalyticsController::class, 'show']);
        Route::get('/analytics/export',                  [CompanyAnalyticsController::class, 'export']);
        Route::get('/jobs/manage',                       [HotelController::class, 'manageJobs']);

        // Caixa de entrada compartilhada do hotel (qualquer admin aprovado)
        Route::get('/chat/unread-count',                 [ChatController::class, 'hotelUnreadCount']);
        Route::get('/chat/conversations',                [ChatController::class, 'hotelConversations']);
        Route::get('/chat/{user}/messages',              [ChatController::class, 'hotelAdminMessages']);
        Route::post('/chat/{user}/messages',             [ChatController::class, 'hotelAdminSend']);
        Route::patch('/chat/{user}/read',                [ChatController::class, 'hotelAdminMarkRead']);
    });

    // Feed
    Route::get('/feed',                                         [FeedController::class, 'getFeed']);
    Route::post('/posts',                                       [FeedController::class, 'createPost']);
    Route::get('/posts/{post}',                                 [FeedController::class, 'getPost']);
    Route::put('/posts/{post}',                                 [FeedController::class, 'updatePost']);
    Route::delete('/posts/{post}',                              [FeedController::class, 'deletePost']);
    Route::post('/posts/{post}/like',                           [FeedController::class, 'likePost']);
    Route::delete('/posts/{post}/like',                         [FeedController::class, 'unlikePost']);
    Route::post('/posts/{post}/image',                          [FeedController::class, 'uploadPostImage']);
    Route::get('/posts/{post}/comments',                        [FeedController::class, 'getComments']);
    Route::post('/posts/{post}/comments',                       [FeedController::class, 'addComment']);
    Route::delete('/posts/{post}/comments/{comment}',           [FeedController::class, 'deleteComment']);

    // Jobs (all routes require authentication)
    Route::get('/jobs',                               [JobController::class, 'index']);
    Route::get('/jobs/{job}',                         [JobController::class, 'show']);
    Route::post('/jobs',                              [JobController::class, 'store']);
    Route::put('/jobs/{job}',                         [JobController::class, 'update']);
    Route::patch('/jobs/{job}/status',                [JobController::class, 'updateStatus']);
    Route::post('/jobs/{job}/apply',                  [JobController::class, 'apply']);
    Route::get('/jobs/{job}/applications',            [JobController::class, 'applications']);
    Route::patch('/jobs/{job}/applications/{application}/status', [JobController::class, 'updateApplicationStatus']);
    Route::get('/me/applications',                    [JobController::class, 'myApplications']);

    // Search
    Route::prefix('search')->group(function () {
        Route::get('/hotels', [SearchController::class, 'searchHotels']);
        Route::get('/jobs',   [SearchController::class, 'searchJobs']);
        Route::get('/users',  [SearchController::class, 'searchUsers']);
    });

    // Chat — literal routes MUST come before parameterized {user} routes
    Route::prefix('chat')->group(function () {
        Route::get('/unread-count',           [ChatController::class, 'unreadCount']);
        Route::get('/conversations',          [ChatController::class, 'conversations']);
        // Conversa pessoal com a caixa compartilhada de um hotel
        Route::get('/hotels/{hotel}/messages',  [ChatController::class, 'hotelThreadMessages']);
        Route::post('/hotels/{hotel}/messages', [ChatController::class, 'sendToHotel']);
        Route::patch('/hotels/{hotel}/read',    [ChatController::class, 'markHotelThreadRead']);
        Route::get('/{user}/messages',        [ChatController::class, 'messages']);
        Route::post('/{user}/messages',       [ChatController::class, 'send']);
        Route::patch('/{user}/read',          [ChatController::class, 'markRead']);
    });

    // Connections — literal routes MUST come before parameterized {user} routes
    Route::get('/connections',                       [ConnectionController::class, 'index']);
    Route::get('/connections/pending',               [ConnectionController::class, 'pending']);
    Route::post('/connections/{user}',               [ConnectionController::class, 'send']);
    Route::delete('/connections/{user}',             [ConnectionController::class, 'remove']);
    Route::patch('/connections/{user}/accept',       [ConnectionController::class, 'accept']);
    Route::patch('/connections/{user}/reject',       [ConnectionController::class, 'reject']);

    // Notifications — literal routes before parameterized {notification}
    Route::get('/notifications',                         [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',            [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all',              [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read',   [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}',       [NotificationController::class, 'destroy']);

    // User posts (for profile page)
    Route::get('/users/{user}/posts',   [FeedController::class, 'userPosts']);

    // Videos (feed de vídeos profissionais; upload restrito a Consultor PRO)
    Route::get('/videos',                 [VideoController::class, 'index']);
    Route::post('/videos',                [VideoController::class, 'store']);
    Route::get('/videos/{video}',         [VideoController::class, 'show']);
    Route::post('/videos/{video}/view',   [VideoController::class, 'registerView']);
    Route::get('/users/{user}/videos',    [VideoController::class, 'creatorVideos']);

    // Seguir/deixar de seguir criador
    Route::post('/users/{user}/follow',    [CreatorController::class, 'follow']);
    Route::delete('/users/{user}/follow',  [CreatorController::class, 'unfollow']);

    // Cursos (§11) — criação exclusiva de Consultor PRO; matrícula gratuita no MVP
    Route::get('/courses',                              [CourseController::class, 'index']);
    Route::post('/courses',                             [CourseController::class, 'store']);
    Route::get('/me/courses',                           [CourseController::class, 'myCourses']);
    Route::get('/courses/{course}',                     [CourseController::class, 'show']);
    Route::put('/courses/{course}',                     [CourseController::class, 'update']);
    Route::patch('/courses/{course}/publish',           [CourseController::class, 'publish']);
    Route::post('/courses/{course}/enroll',             [CourseController::class, 'enroll']);
    Route::post('/courses/{course}/lessons',            [CourseController::class, 'addLesson']);
    Route::put('/courses/{course}/lessons/{lesson}',    [CourseController::class, 'updateLesson']);
    Route::delete('/courses/{course}/lessons/{lesson}', [CourseController::class, 'deleteLesson']);
    Route::get('/users/{user}/courses',                 [CourseController::class, 'creatorCourses']);

    // Mentorias (§11) — oferta exclusiva de Consultor PRO; solicitação gratuita no MVP
    Route::get('/mentorships',                                     [MentorshipController::class, 'index']);
    Route::post('/mentorships',                                    [MentorshipController::class, 'store']);
    Route::get('/me/mentorship-bookings',                          [MentorshipController::class, 'myBookings']);
    Route::get('/mentorships/{mentorship}',                        [MentorshipController::class, 'show']);
    Route::put('/mentorships/{mentorship}',                        [MentorshipController::class, 'update']);
    Route::patch('/mentorships/{mentorship}/publish',              [MentorshipController::class, 'publish']);
    Route::post('/mentorships/{mentorship}/book',                  [MentorshipController::class, 'book']);
    Route::get('/mentorships/{mentorship}/bookings',               [MentorshipController::class, 'bookings']);
    Route::patch('/mentorships/{mentorship}/bookings/{booking}',   [MentorshipController::class, 'updateBooking']);
    Route::get('/users/{user}/mentorships',                        [MentorshipController::class, 'mentorMentorships']);

    // Avaliações polimórficas (§6/§11) — {type}: creator | course | mentorship
    Route::get('/reviews/{type}/{uuid}',    [ReviewController::class, 'index']);
    Route::post('/reviews/{type}/{uuid}',   [ReviewController::class, 'store']);

    // Interações de vídeo (curtidas, comentários, compartilhamentos)
    Route::post('/videos/{video}/like',                    [VideoController::class, 'toggleLike']);
    Route::get('/videos/{video}/comments',                 [VideoController::class, 'comments']);
    Route::post('/videos/{video}/comments',                [VideoController::class, 'addComment']);
    Route::delete('/videos/{video}/comments/{comment}',    [VideoController::class, 'deleteComment']);
    Route::post('/videos/{video}/share',                   [VideoController::class, 'share']);
});
