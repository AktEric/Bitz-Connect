<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatGroupController;
use App\Http\Controllers\Api\V1\CompanyDirectoryController;
use App\Http\Controllers\Api\V1\CompanyInviteController;
use App\Http\Controllers\Api\V1\BenefitController;
use App\Http\Controllers\Api\V1\CareerController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\SalaryController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceSearchController;
use App\Http\Controllers\Api\V1\AccountSecurityController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\ClimateController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\WorkspaceJobController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\TimeBankController;
use App\Http\Controllers\Api\V1\TimeClosingController;
use App\Http\Controllers\Api\V1\TimeDeviceController;
use App\Http\Controllers\Api\V1\TimeSettingsController;
use App\Http\Controllers\Api\V1\TimeClockController;
use App\Http\Controllers\Api\V1\WorkScheduleController;
use App\Http\Controllers\Api\V1\WorkspaceFeedController;
use App\Http\Controllers\Api\V1\WorkspaceHomeController;
use App\Http\Controllers\Api\V1\WorkspaceRequestController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\DevelopmentPlanController;
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
use App\Http\Controllers\Api\V1\StoryController;
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
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::get('/providers',        [AuthController::class, 'providers']);
    Route::post('/google',          [AuthController::class, 'google']);
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

// Convite para a empresa — pré-visualização pública (por token, com rate limit)
Route::get('/invites/{token}', [CompanyInviteController::class, 'preview'])->middleware('throttle:30,1');
Route::get('/public/jobs/{token}', [WorkspaceJobController::class, 'publicShow'])->middleware('throttle:60,1');

// Assinatura do calendário (Google/Outlook/Apple): pública, protegida por segredo pessoal e rate limit
Route::get('/calendar-feed/{token}.ics', [CalendarController::class, 'feed'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1');

// Fotos do mural do Workspace: privadas, entregues só com URL assinada (relativa) de curta duração
Route::get('/marketplace-media/{uuid}', [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'media'])->name('marketplace.media')->middleware(['signed:relative', 'throttle:240,1']);
Route::get('/workspace-media/{uuid}', [WorkspaceFeedController::class, 'media'])->name('workspace.media')->middleware(['signed:relative', 'throttle:120,1']);

// Subscription plan catalog (public — for the pricing page)
Route::get('/plans',                           [SubscriptionController::class, 'plans']);

// Página pública do criador
Route::get('/creators/{user}',                 [CreatorController::class, 'show']);
Route::get('/hotels/{hotel}/jobs',             [HotelController::class, 'hotelJobs']);
Route::get('/hotels/{hotel}/posts',            [HotelController::class, 'hotelPosts']);

// Jobs listing and detail are accessible to authenticated users (see auth group below)

// ──────────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Pessoas da empresa (LGPD): só para quem está logado — nunca públicas
    Route::get('/hotels/{hotel}/admins',    [HotelController::class, 'hotelAdmins']);
    Route::get('/hotels/{hotel}/employees', [HotelController::class, 'employees']);

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout',   [AuthController::class, 'logout']);
        Route::put('/password',  [AuthController::class, 'changePassword']);

        // Segurança da conta: verificação em duas etapas e sessões
        Route::get('/security',                  [AccountSecurityController::class, 'overview']);
        Route::post('/2fa/setup',                [AccountSecurityController::class, 'setup'])->middleware('throttle:auth');
        Route::post('/2fa/enable',               [AccountSecurityController::class, 'enable'])->middleware('throttle:auth');
        Route::post('/2fa/disable',              [AccountSecurityController::class, 'disable'])->middleware('throttle:auth');
        Route::post('/2fa/recovery-codes',       [AccountSecurityController::class, 'regenerate'])->middleware('throttle:auth');
        Route::delete('/sessions/{id}',          [AccountSecurityController::class, 'revoke'])->whereNumber('id');
        Route::post('/sessions/revoke-others',   [AccountSecurityController::class, 'revokeOthers']);
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
    Route::get('/segment-templates',          [CompanyDirectoryController::class, 'segmentTemplates']);

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

        // Gestão interna da empresa — setores, membros e grupos de chat
        Route::get('/departments',                       [CompanyDirectoryController::class, 'departments']);
        Route::post('/segment-template',                 [CompanyDirectoryController::class, 'applySegmentTemplate']);
        Route::post('/departments',                      [CompanyDirectoryController::class, 'storeDepartment']);
        Route::put('/departments/{department}',          [CompanyDirectoryController::class, 'updateDepartment']);
        Route::delete('/departments/{department}',       [CompanyDirectoryController::class, 'destroyDepartment']);
        Route::get('/members',                           [CompanyDirectoryController::class, 'members']);
        Route::patch('/members/{user}/department',       [CompanyDirectoryController::class, 'setMemberDepartment']);
        Route::get('/members/{user}',                    [CompanyDirectoryController::class, 'memberProfile']);
        Route::patch('/members/{user}',                  [CompanyDirectoryController::class, 'updateMemberProfile']);
        Route::post('/chat-groups',                      [ChatGroupController::class, 'store']);

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
        Route::get('/attachments/{uuid}',     [ChatController::class, 'attachment']);
        Route::get('/gifs',                   [\App\Http\Controllers\Api\V1\GifController::class, 'search'])->middleware('throttle:60,1');
        Route::get('/conversations',          [ChatController::class, 'conversations']);
        // Conversa pessoal com a caixa compartilhada de um hotel
        Route::get('/hotels/{hotel}/messages',  [ChatController::class, 'hotelThreadMessages']);
        Route::post('/hotels/{hotel}/messages', [ChatController::class, 'sendToHotel']);
        Route::patch('/hotels/{hotel}/read',    [ChatController::class, 'markHotelThreadRead']);
        Route::get('/{user}/messages',        [ChatController::class, 'messages']);
        Route::post('/{user}/messages',       [ChatController::class, 'send']);
        Route::patch('/{user}/read',          [ChatController::class, 'markRead']);
    });

    // Central de ajuda (Bianca)
    Route::post('/help/chat', [\App\Http\Controllers\Api\V1\HelpChatController::class, 'chat'])->middleware('throttle:20,1');

    // ── Workspace (intranet da empresa) ───────────────────────
    Route::get('/me/workspaces',               [WorkspaceController::class, 'mine']);
    Route::post('/invites/{token}/accept',      [CompanyInviteController::class, 'accept']);

    Route::prefix('workspaces/{hotel:slug}')->middleware('workspace.2fa')->group(function () {

        // Pesquisa de clima (anônima)
        Route::get('/climate',                       [ClimateController::class, 'index']);
        Route::post('/climate',                      [ClimateController::class, 'store']);
        Route::post('/climate/{uuid}/close',         [ClimateController::class, 'close']);
        Route::post('/climate/{uuid}/answer',        [ClimateController::class, 'answer']);
        Route::get('/climate/{uuid}/results',        [ClimateController::class, 'results']);

        // Vagas
        Route::get('/jobs',                              [WorkspaceJobController::class, 'index']);
        Route::post('/jobs',                             [WorkspaceJobController::class, 'store']);
        Route::patch('/jobs/{uuid}',                     [WorkspaceJobController::class, 'update']);
        Route::delete('/jobs/{uuid}',                    [WorkspaceJobController::class, 'destroy']);
        Route::post('/jobs/{uuid}/apply',                [WorkspaceJobController::class, 'apply']);
        Route::delete('/jobs/{uuid}/apply',              [WorkspaceJobController::class, 'withdraw']);
        Route::get('/jobs/{uuid}/applications',          [WorkspaceJobController::class, 'applications']);
        Route::patch('/jobs/{uuid}/applications/{app}',  [WorkspaceJobController::class, 'setApplicationStatus']);

        // Perfil e ficha
        Route::get('/me/profile',                [PersonController::class, 'mine']);
        Route::put('/me/profile',                [PersonController::class, 'updateMine']);
        Route::get('/people/{uuid}/profile',     [PersonController::class, 'show']);
        Route::get('/me/salary',                 [SalaryController::class, 'status']);
        Route::post('/me/salary/reveal',         [SalaryController::class, 'reveal']);
        Route::put('/people/{uuid}/salary',      [SalaryController::class, 'set']);
        Route::get('/me/benefits',                          [BenefitController::class, 'mine']);
        Route::get('/people/{uuid}/benefits',               [BenefitController::class, 'index']);
        Route::post('/people/{uuid}/benefits',              [BenefitController::class, 'store']);
        Route::post('/people/{uuid}/benefits/apply-package', [BenefitController::class, 'applyPackage']);
        Route::put('/people/{uuid}/benefits/{id}',          [BenefitController::class, 'update']);
        Route::delete('/people/{uuid}/benefits/{id}',       [BenefitController::class, 'destroy']);
        Route::get('/benefit-types',                        [BenefitController::class, 'types']);
        Route::post('/benefit-types',                       [BenefitController::class, 'storeType']);
        Route::patch('/benefit-types/{uuid}',               [BenefitController::class, 'updateType']);
        Route::get('/benefit-packages',                     [BenefitController::class, 'packages']);
        Route::post('/benefit-packages',                    [BenefitController::class, 'storePackage']);
        Route::put('/benefit-packages/{uuid}',              [BenefitController::class, 'updatePackage']);
        Route::delete('/benefit-packages/{uuid}',           [BenefitController::class, 'destroyPackage']);
        Route::get('/people/{uuid}/org',         [PersonController::class, 'org']);
        Route::get('/people/{uuid}/career',                [CareerController::class, 'index']);
        Route::post('/people/{uuid}/career',               [CareerController::class, 'store']);
        Route::put('/people/{uuid}/career/{entry}',        [CareerController::class, 'update']);
        Route::delete('/people/{uuid}/career/{entry}',     [CareerController::class, 'destroy']);
        Route::get('/people/{uuid}/record',      [PersonController::class, 'record']);
        Route::put('/people/{uuid}/record',      [PersonController::class, 'updateRecord']);

        // LGPD
        Route::get('/me/privacy',          [PrivacyController::class, 'status']);
        Route::post('/me/privacy/consent', [PrivacyController::class, 'consent']);
        Route::get('/me/export',           [PrivacyController::class, 'export']);

        // Ponto
        Route::get('/time/today',      [TimeClockController::class, 'today_']);
        Route::post('/time/punch',     [TimeClockController::class, 'punch']);
        Route::get('/time/timesheet',  [TimeClockController::class, 'timesheet']);
        Route::get('/time/team',       [TimeClockController::class, 'team']);
        Route::get('/time/device',                         [TimeDeviceController::class, 'mine']);
        Route::get('/time/devices',                        [TimeDeviceController::class, 'index']);
        Route::patch('/time/devices/{uuid}',               [TimeDeviceController::class, 'update']);
        Route::delete('/time/devices/{uuid}',              [TimeDeviceController::class, 'destroy']);
        Route::get('/time/settings',                       [TimeSettingsController::class, 'show']);
        Route::put('/time/settings',                       [TimeSettingsController::class, 'update']);
        Route::get('/time/entries/{uuid}/receipt',         [TimeClockController::class, 'receipt']);
        Route::get('/time/integrity',                      [TimeClockController::class, 'integrity']);
        Route::get('/time/entries/{uuid}/photo',           [TimeClockController::class, 'photo']);
        Route::get('/time/closings',                       [TimeClosingController::class, 'index']);
        Route::post('/time/closings',                      [TimeClosingController::class, 'store']);
        Route::get('/time/closings/{month}',               [TimeClosingController::class, 'show']);
        Route::delete('/time/closings/{month}',            [TimeClosingController::class, 'destroy']);
        Route::get('/time/closings/{month}/export',        [TimeClosingController::class, 'export']);
        Route::get('/time/bank',                  [TimeBankController::class, 'show']);
        Route::get('/time/bank/team',             [TimeBankController::class, 'team']);
        Route::post('/time/bank/adjustments',     [TimeBankController::class, 'adjust']);
        Route::post('/time/entries',   [TimeClockController::class, 'manual']);
        Route::get('/time/adjustments',                          [TimeClockController::class, 'adjustments']);
        Route::post('/time/adjustments',                         [TimeClockController::class, 'requestAdjustment']);
        Route::post('/time/adjustments/{adjustment}/decision',   [TimeClockController::class, 'decideAdjustment']);
        Route::get('/',                         [WorkspaceController::class, 'show']);
        Route::get('/invites',                  [CompanyInviteController::class, 'index']);
        Route::post('/invites',                 [CompanyInviteController::class, 'store']);
        Route::delete('/invites/{invite}',      [CompanyInviteController::class, 'destroy']);
        Route::get('/search',                            [WorkspaceSearchController::class, 'index'])->middleware('throttle:60,1');
        // Início: destaques da coluna direita
        Route::get('/home/highlights',                   [WorkspaceHomeController::class, 'highlights']);

        Route::get('/calendar',                          [CalendarController::class, 'index']);
        Route::post('/calendar/events',                  [CalendarController::class, 'storeEvent']);
        Route::patch('/calendar/events/{uuid}',          [CalendarController::class, 'updateEvent']);
        Route::delete('/calendar/events/{uuid}',         [CalendarController::class, 'destroyEvent']);
        Route::post('/calendar/events/{uuid}/rsvp',      [CalendarController::class, 'rsvp']);
        Route::patch('/calendar/me',                     [CalendarController::class, 'updateMyBirthday']);
        Route::patch('/calendar/birthdays/{uuid}',       [CalendarController::class, 'setBirthday']);
        Route::get('/calendar/ics-link',                 [CalendarController::class, 'icsLink']);
        Route::post('/calendar/ics-link',                [CalendarController::class, 'rotateIcsLink']);
        Route::get('/areas',                    [AreaController::class, 'index']);
        Route::patch('/areas/{uuid}',           [AreaController::class, 'setHead']);
        Route::post('/delegations',             [AreaController::class, 'delegate']);
        Route::delete('/delegations/{uuid}',    [AreaController::class, 'undelegate']);
        Route::get('/schedules',                [WorkScheduleController::class, 'index']);
        Route::post('/schedules',               [WorkScheduleController::class, 'store']);
        Route::patch('/schedules/assign',       [WorkScheduleController::class, 'assign']);
        Route::patch('/schedules/{uuid}',       [WorkScheduleController::class, 'update']);
        Route::delete('/schedules/{uuid}',      [WorkScheduleController::class, 'destroy']);
        Route::get('/reports',                  [ReportController::class, 'index']);
        Route::get('/reports/{key}',            [ReportController::class, 'show']);
        Route::get('/dashboard',                [WorkspaceController::class, 'dashboard']);
        Route::get('/onboarding',               [OnboardingController::class, 'show']);
        Route::post('/onboarding/dismiss',      [OnboardingController::class, 'dismiss']);
        Route::get('/security',                 [WorkspaceController::class, 'security']);
        Route::put('/security',                 [WorkspaceController::class, 'updateSecurity']);
        Route::get('/audit/export',             [WorkspaceController::class, 'auditExport']);
        Route::get('/audit',                    [WorkspaceController::class, 'audit']);
        Route::get('/roles',                    [WorkspaceController::class, 'roles']);
        Route::patch('/roles/{uuid}',           [WorkspaceController::class, 'updateRole']);
        // Marketplace interno
        Route::get('/marketplace',                          [MarketplaceController::class, 'index']);
        Route::post('/marketplace',                         [MarketplaceController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/marketplace/{uuid}',                   [MarketplaceController::class, 'show']);
        Route::patch('/marketplace/{uuid}',                 [MarketplaceController::class, 'update']);
        Route::delete('/marketplace/{uuid}',                [MarketplaceController::class, 'destroy']);
        Route::post('/marketplace/{uuid}/images',           [MarketplaceController::class, 'addImages'])->middleware('throttle:30,1');
        Route::delete('/marketplace/{uuid}/images/{img}',   [MarketplaceController::class, 'removeImage']);
        Route::post('/marketplace/{uuid}/like',             [MarketplaceController::class, 'like']);
        Route::post('/marketplace/{uuid}/repost',           [MarketplaceController::class, 'repost']);
        //WORKSPACE_ROUTES
        // Mural
        Route::get('/feed',                          [WorkspaceFeedController::class, 'index']);
        Route::post('/feed',                         [WorkspaceFeedController::class, 'store']);
        Route::post('/kudos',                        [WorkspaceFeedController::class, 'kudos']);
        Route::post('/polls',                        [WorkspaceFeedController::class, 'createPoll']);
        Route::post('/feed/{post}/vote',             [WorkspaceFeedController::class, 'vote']);
        Route::delete('/feed/{post}',                [WorkspaceFeedController::class, 'destroy']);
        Route::post('/feed/{post}/pin',              [WorkspaceFeedController::class, 'pin']);
        Route::post('/feed/{post}/ack',              [WorkspaceFeedController::class, 'ack']);
        Route::get('/feed/{post}/acks',              [WorkspaceFeedController::class, 'acks']);
        Route::post('/feed/{post}/react',            [WorkspaceFeedController::class, 'react']);
        Route::get('/feed/{post}/reactions',         [WorkspaceFeedController::class, 'reactions']);
        Route::post('/feed/{post}/like',             [WorkspaceFeedController::class, 'like']);
        Route::get('/feed/{post}/comments',          [WorkspaceFeedController::class, 'comments']);
        Route::post('/feed/{post}/comments',         [WorkspaceFeedController::class, 'comment']);

        // Solicitações
        Route::get('/request-flows',                 [\App\Http\Controllers\Api\V1\RequestFlowController::class, 'index']);
        Route::put('/request-flows/{type}',          [\App\Http\Controllers\Api\V1\RequestFlowController::class, 'update']);
        Route::get('/requests',                      [WorkspaceRequestController::class, 'index']);
        Route::post('/requests',                     [WorkspaceRequestController::class, 'store']);
        Route::get('/requests/{uuid}',               [WorkspaceRequestController::class, 'show']);
        Route::post('/requests/{uuid}/decision',     [WorkspaceRequestController::class, 'decide']);
        Route::post('/requests/{uuid}/assign',       [WorkspaceRequestController::class, 'assign']);
        Route::post('/requests/{uuid}/resolve',      [WorkspaceRequestController::class, 'resolve']);
        Route::post('/requests/{uuid}/cancel',       [WorkspaceRequestController::class, 'cancel']);
        Route::post('/requests/{uuid}/comments',     [WorkspaceRequestController::class, 'comment']);

        // Recebimentos
        Route::get('/payslips',                      [PayslipController::class, 'index']);
        Route::post('/payslips',                     [PayslipController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/payslips/{uuid}/download',      [PayslipController::class, 'download']);
        Route::post('/payslips/{uuid}/sign',         [PayslipController::class, 'sign'])->middleware('throttle:20,1');
        Route::get('/payslips/{uuid}/signature',     [PayslipController::class, 'signature']);
        Route::delete('/payslips/{uuid}',            [PayslipController::class, 'destroy']);

        // Metas
        Route::get('/goals',                         [GoalController::class, 'index']);
        Route::get('/goals/summary',                 [GoalController::class, 'summary']);
        Route::post('/goals',                        [GoalController::class, 'store']);
        Route::get('/goals/{uuid}',                  [GoalController::class, 'show']);
        Route::patch('/goals/{uuid}',                [GoalController::class, 'update']);
        Route::post('/goals/{uuid}/updates',         [GoalController::class, 'addUpdate']);

        // PDI
        Route::get('/development',                   [DevelopmentPlanController::class, 'index']);
        Route::post('/development',                  [DevelopmentPlanController::class, 'store']);
        Route::get('/development/{uuid}',            [DevelopmentPlanController::class, 'show']);
        Route::patch('/development/{uuid}',          [DevelopmentPlanController::class, 'update']);
        Route::post('/development/{uuid}/items',     [DevelopmentPlanController::class, 'addItem']);
        Route::patch('/development/{uuid}/items/{item}',  [DevelopmentPlanController::class, 'updateItem']);
        Route::delete('/development/{uuid}/items/{item}', [DevelopmentPlanController::class, 'destroyItem']);
        Route::post('/development/{uuid}/notes',     [DevelopmentPlanController::class, 'addNote']);
    });

    // Grupos de chat corporativo — literal routes MUST come before {group}
    Route::get('/chat-groups',                               [ChatGroupController::class, 'index']);
    Route::get('/chat-groups/unread-count',                  [ChatGroupController::class, 'unreadCount']);
    Route::get('/chat-groups/{group}',                       [ChatGroupController::class, 'show']);
    Route::patch('/chat-groups/{group}',                     [ChatGroupController::class, 'update']);
    Route::delete('/chat-groups/{group}',                    [ChatGroupController::class, 'destroy']);
    Route::post('/chat-groups/{group}/members',              [ChatGroupController::class, 'addMembers']);
    Route::delete('/chat-groups/{group}/members/{user}',     [ChatGroupController::class, 'removeMember']);
    Route::patch('/chat-groups/{group}/members/{user}/role', [ChatGroupController::class, 'setMemberRole']);
    Route::delete('/chat-groups/{group}/membership',         [ChatGroupController::class, 'leave']);
    Route::patch('/chat-groups/{group}/mute',                [ChatGroupController::class, 'mute']);
    Route::get('/chat-groups/{group}/messages',              [ChatGroupController::class, 'messages']);
    Route::post('/chat-groups/{group}/messages',             [ChatGroupController::class, 'send']);
    Route::patch('/chat-groups/{group}/read',                [ChatGroupController::class, 'markRead']);

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

    // Stories (destaque de vaga/mentoria; publicação restrita a contas PRO)
    Route::get('/stories',                [StoryController::class, 'index']);
    Route::post('/stories',               [StoryController::class, 'store']);
    Route::delete('/stories/{story}',     [StoryController::class, 'destroy']);
    Route::post('/stories/{story}/view',  [StoryController::class, 'registerView']);
    Route::post('/stories/{story}/like',  [StoryController::class, 'toggleLike']);
    Route::post('/stories/{story}/share', [StoryController::class, 'share']);
});
