import { Routes, Route, Navigate } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import PrivateRoute from './PrivateRoute'
import GuestRoute from './GuestRoute'
import HotelPrivateRoute from './HotelPrivateRoute'
import { ErrorBoundary } from '@/components/common/ErrorBoundary'
import HomeGate from './HomeGate'
import WorkspaceRoute from './WorkspaceRoute'

// Auth pages (lazy loaded) — Pessoa
const LoginPessoaPage = lazy(() => import('@/pages/auth/LoginPessoaPage'))
const RegisterSelectPage = lazy(() => import('@/pages/auth/RegisterSelectPage'))
const RegisterPessoaPage = lazy(() => import('@/pages/auth/RegisterPessoaPage'))
const ForgotPasswordPage = lazy(() => import('@/pages/auth/ForgotPasswordPage'))
const ResetPasswordPage = lazy(() => import('@/pages/auth/ResetPasswordPage'))

// Auth pages (lazy loaded) — Hotel
const LoginFuncionarioPage = lazy(() => import('@/pages/auth/LoginFuncionarioPage'))
const LoginHotelPage = lazy(() => import('@/pages/auth/LoginHotelPage'))
const RegisterHotelPage = lazy(() => import('@/pages/auth/RegisterHotelPage'))

// App pages (lazy loaded) — Pessoa
const FeedPage = lazy(() => import('@/pages/feed/FeedPage'))
const ProfilePage = lazy(() => import('@/pages/profile/ProfilePage'))
const EditProfilePage = lazy(() => import('@/pages/profile/EditProfilePage'))
const HotelProfilePage = lazy(() => import('@/pages/hotel/HotelProfilePage'))
const MyHotelsPage = lazy(() => import('@/pages/hotel/MyHotelsPage'))
const JobsPage = lazy(() => import('@/pages/jobs/JobsPage'))
const JobDetailPage = lazy(() => import('@/pages/jobs/JobDetailPage'))
const CreateJobPage = lazy(() => import('@/pages/jobs/CreateJobPage'))
const NetworkPage = lazy(() => import('@/pages/network/NetworkPage'))
const SearchPage = lazy(() => import('@/pages/search/SearchPage'))
const ChatPage = lazy(() => import('@/pages/chat/ChatPage'))
const NotificationsPage = lazy(() => import('@/pages/notifications/NotificationsPage'))
const PlansPage = lazy(() => import('@/pages/plans/PlansPage'))
const VideosPage = lazy(() => import('@/pages/videos/VideosPage'))
const CreatorPage = lazy(() => import('@/pages/creator/CreatorPage'))
const CoursesPage = lazy(() => import('@/pages/course/CoursesPage'))
const CoursePage = lazy(() => import('@/pages/course/CoursePage'))
const CourseEditorPage = lazy(() => import('@/pages/course/CourseEditorPage'))
const MentorshipsPage = lazy(() => import('@/pages/mentorship/MentorshipsPage'))
const MentorshipPage = lazy(() => import('@/pages/mentorship/MentorshipPage'))
const MentorshipEditorPage = lazy(() => import('@/pages/mentorship/MentorshipEditorPage'))

// App pages (lazy loaded) — Hotel
const HotelDashboardPage = lazy(() => import('@/pages/hotel/HotelDashboardPage'))
const HotelJobsPage = lazy(() => import('@/pages/hotel/HotelJobsPage'))
const HotelEmployeesPage = lazy(() => import('@/pages/hotel/HotelEmployeesPage'))
const HotelPostsPage = lazy(() => import('@/pages/hotel/HotelPostsPage'))
const HotelSettingsPage = lazy(() => import('@/pages/hotel/HotelSettingsPage'))
const HotelAdminsPage = lazy(() => import('@/pages/hotel/HotelAdminsPage'))
const HotelMessagesPage = lazy(() => import('@/pages/hotel/HotelMessagesPage'))

const WorkspacesPage = lazy(() => import('@/pages/workspace/WorkspacesPage'))
const WorkspaceLayout = lazy(() => import('@/pages/workspace/WorkspaceLayout'))
const WorkspaceHomePage = lazy(() => import('@/pages/workspace/WorkspaceHomePage'))
const WorkspaceFeedPage = lazy(() => import('@/pages/workspace/WorkspaceFeedPage'))
const WorkspaceTeamPage = lazy(() => import('@/pages/workspace/WorkspaceTeamPage'))
const WorkspaceRequestsPage = lazy(() => import('@/pages/workspace/WorkspaceRequestsPage'))
const WorkspacePayslipsPage = lazy(() => import('@/pages/workspace/WorkspacePayslipsPage'))
const WorkspaceGoalsPage = lazy(() => import('@/pages/workspace/WorkspaceGoalsPage'))
const WorkspaceDevelopmentPage = lazy(() => import('@/pages/workspace/WorkspaceDevelopmentPage'))
const WorkspaceAdminPage = lazy(() => import('@/pages/workspace/WorkspaceAdminPage'))
const WorkspaceCalendarPage = lazy(() => import('@/pages/workspace/WorkspaceCalendarPage'))
const WorkspaceSecurityPage = lazy(() => import('@/pages/workspace/WorkspaceSecurityPage'))
const WorkspaceAuditPage = lazy(() => import('@/pages/workspace/WorkspaceAuditPage'))
const WorkspaceDashboardPage = lazy(() => import('@/pages/workspace/WorkspaceDashboardPage'))
const WorkspaceOnboardingPage = lazy(() => import('@/pages/workspace/WorkspaceOnboardingPage'))
const WorkspaceTimePage = lazy(() => import('@/pages/workspace/WorkspaceTimePage'))
const InviteAcceptPage = lazy(() => import('@/pages/workspace/InviteAcceptPage'))

function Loading() {
  return (
    <div className="flex h-screen items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  )
}

export default function AppRoutes() {
  return (
    <ErrorBoundary>
    <Suspense fallback={<Loading />}>
      <Routes>
        {/* Guest Pessoa — redirects to /feed if already authenticated */}
        <Route element={<GuestRoute type="pessoa" />}>
          <Route path="/login/pessoa" element={<LoginPessoaPage />} />
          <Route path="/register" element={<RegisterSelectPage />} />
          <Route path="/register/pessoa" element={<RegisterPessoaPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
        </Route>

        {/* Guest Hotel — redirects to /hotel/:slug/dashboard if already hotel-authenticated */}
        <Route element={<GuestRoute type="hotel" />}>
          <Route path="/login/hotel" element={<LoginHotelPage />} />
          <Route path="/register/hotel" element={<RegisterHotelPage />} />
        </Route>

        {/* Login do funcionário: fora do GuestRoute, pois ele mesmo redireciona após o login */}
        <Route path="/entrar" element={<LoginFuncionarioPage />} />
        <Route path="/login/funcionario" element={<Navigate to="/entrar" replace />} />

        {/* Convite por link (página pública) */}
        <Route path="/convite/:token" element={<InviteAcceptPage />} />

        {/* Workspace (intranet + RH): casca própria, fora do AppLayout da rede profissional */}
        <Route element={<WorkspaceRoute />}>
          <Route path="/w" element={<WorkspacesPage />} />
          <Route path="/w/:slug" element={<WorkspaceLayout />}>
            <Route index element={<WorkspaceHomePage />} />
            <Route path="ponto" element={<WorkspaceTimePage />} />
            <Route path="mural" element={<WorkspaceFeedPage />} />
            <Route path="calendario" element={<WorkspaceCalendarPage />} />
            <Route path="seguranca" element={<WorkspaceSecurityPage />} />
            <Route path="auditoria" element={<WorkspaceAuditPage />} />
            <Route path="painel" element={<WorkspaceDashboardPage />} />
            <Route path="comecar" element={<WorkspaceOnboardingPage />} />
            <Route path="equipe" element={<WorkspaceTeamPage />} />
            <Route path="configuracoes" element={<WorkspaceAdminPage />} />
            <Route path="solicitacoes" element={<WorkspaceRequestsPage />} />
            <Route path="recebimentos" element={<WorkspacePayslipsPage />} />
            <Route path="metas" element={<WorkspaceGoalsPage />} />
            <Route path="desenvolvimento" element={<WorkspaceDevelopmentPage />} />
          </Route>
        </Route>

        {/* Protected Pessoa routes */}
        <Route element={<PrivateRoute />}>
          <Route path="/" element={<HomeGate />} />
          <Route path="/feed" element={<FeedPage />} />
          <Route path="/network" element={<NetworkPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/profile/edit" element={<EditProfilePage />} />
          <Route path="/users/:uuid" element={<ProfilePage />} />
          <Route path="/hotels" element={<MyHotelsPage />} />
          <Route path="/hotels/:slug" element={<HotelProfilePage />} />
          <Route path="/company/:slug" element={<HotelProfilePage />} />
          <Route path="/jobs" element={<JobsPage />} />
          <Route path="/jobs/new" element={<CreateJobPage />} />
          <Route path="/jobs/:uuid" element={<JobDetailPage />} />
          <Route path="/search" element={<SearchPage />} />
          <Route path="/chat" element={<ChatPage />} />
          <Route path="/notifications" element={<NotificationsPage />} />
          <Route path="/plans" element={<PlansPage />} />
          <Route path="/videos" element={<VideosPage />} />
          <Route path="/creators/:uuid" element={<CreatorPage />} />
          <Route path="/courses" element={<CoursesPage />} />
          <Route path="/courses/new" element={<CourseEditorPage />} />
          <Route path="/courses/:uuid" element={<CoursePage />} />
          <Route path="/courses/:uuid/edit" element={<CourseEditorPage />} />
          <Route path="/mentorships" element={<MentorshipsPage />} />
          <Route path="/mentorships/new" element={<MentorshipEditorPage />} />
          <Route path="/mentorships/:uuid" element={<MentorshipPage />} />
          <Route path="/mentorships/:uuid/edit" element={<MentorshipEditorPage />} />
        </Route>

        {/* Protected Hotel routes */}
        <Route element={<HotelPrivateRoute />}>
          <Route path="/hotel/:slug/dashboard" element={<HotelDashboardPage />} />
          <Route path="/hotel/:slug/jobs" element={<HotelJobsPage />} />
          <Route path="/hotel/:slug/employees" element={<HotelEmployeesPage />} />
          <Route path="/hotel/:slug/posts" element={<HotelPostsPage />} />
          <Route path="/hotel/:slug/settings" element={<HotelSettingsPage />} />
          <Route path="/hotel/:slug/admins" element={<HotelAdminsPage />} />
          <Route path="/hotel/:slug/messages" element={<HotelMessagesPage />} />
        </Route>

        {/* Legacy redirects — preserve old links */}
        <Route path="/login" element={<Navigate to="/login/pessoa" replace />} />

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
    </ErrorBoundary>
  )
}
