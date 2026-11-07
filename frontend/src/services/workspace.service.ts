import api from './api'
import { getDeviceKey } from '@/pages/workspace/deviceKey'
import type { ApiResponse } from '@/types/api.types'
import type {
  DevelopmentItem, DevelopmentPlan, FeedComment, FeedPost, Goal, GoalSummaryRow, InvitePreview,
  Paged, Payslip, WorkspaceDepartment, WorkspaceInfo, WorkspaceInvite, WorkspaceMember,
  WorkspaceRequest, WorkspaceSummary, TimeToday, Timesheet, TimeTeamRow, TimeType, TimeAdjustment,
  RoleRow, SegmentTemplate, AccessRole, SchedulesPayload, WorkSchedule, TimeBank, TimeBankRow, ClosingItem, ClosingDetail, PunchSettings, AreasPayload, DelegationItem, ReactionType,
  CalendarPayload, ReportItem, ReportData, CalendarEventInput, RsvpStatus, MyDevice, TimeDeviceItem, CompanySecurity, AuditPage, SearchResults, AckPanel, DashboardData, OnboardingData, TimeReceipt, IntegrityResult, PersonCard, PersonProfileFields, MyProfile, PersonRecord, JobItem, JobApplicationItem, PublicJob,
} from '@/types/workspace.types'

const w = (slug: string) => `/workspaces/${slug}`

async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  return (await api.get<ApiResponse<T>>(url, { params })).data.data
}
async function post<T>(url: string, body?: unknown): Promise<T> {
  return (await api.post<ApiResponse<T>>(url, body)).data.data
}
async function patch<T>(url: string, body?: unknown): Promise<T> {
  return (await api.patch<ApiResponse<T>>(url, body)).data.data
}
async function del(url: string): Promise<void> {
  await api.delete(url)
}

/** Workspace — intranet da empresa. */
const workspaceService = {
  mine: () => get<WorkspaceSummary[]>('/me/workspaces'),
  info: (slug: string) => get<WorkspaceInfo>(w(slug)),

  // Ponto
  timeToday: (slug: string) => get<TimeToday>(`${w(slug)}/time/today`),
  punch: (slug: string, opts: { type?: TimeType; geo?: { lat: number; lng: number; accuracy: number } | null; photo?: Blob | null; deviceName?: string } = {}) => {
    const base = { type: opts.type, source: 'web', lat: opts.geo?.lat, lng: opts.geo?.lng, accuracy: opts.geo?.accuracy, device_name: opts.deviceName }
    // A chave do aparelho vai em cabeçalho; o servidor só a usa se a empresa ligou o cadastro de dispositivos.
    const headers: Record<string, string> = { 'X-Device-Key': getDeviceKey() }
    if (!opts.photo) {
      return api.post<ApiResponse<TimeToday>>(`${w(slug)}/time/punch`, base, { headers }).then((r) => r.data.data)
    }
    const form = new FormData()
    for (const [k, v] of Object.entries(base)) if (v !== undefined && v !== null) form.append(k, String(v))
    form.append('photo', opts.photo, 'ponto.jpg')
    return api.post<ApiResponse<TimeToday>>(`${w(slug)}/time/punch`, form, { headers: { ...headers, 'Content-Type': 'multipart/form-data' } }).then((r) => r.data.data)
  },
  myDevice: (slug: string) => api.get<ApiResponse<MyDevice>>(`${w(slug)}/time/device`, { headers: { 'X-Device-Key': getDeviceKey() } }).then((r) => r.data.data),
  timeDevices: (slug: string) => get<TimeDeviceItem[]>(`${w(slug)}/time/devices`),
  updateTimeDevice: (slug: string, uuid: string, body: { name?: string; status?: TimeDeviceItem['status'] }) => patch<unknown>(`${w(slug)}/time/devices/${uuid}`, body),
  deleteTimeDevice: (slug: string, uuid: string) => del(`${w(slug)}/time/devices/${uuid}`),
  timeSettings: (slug: string) => get<PunchSettings>(`${w(slug)}/time/settings`),
  saveTimeSettings: (slug: string, body: PunchSettings) => api.put<ApiResponse<PunchSettings>>(`${w(slug)}/time/settings`, body).then((r) => r.data.data),
  async entryPhotoUrl(slug: string, uuid: string): Promise<string> {
    const res = await api.get(`${w(slug)}/time/entries/${uuid}/photo`, { responseType: 'blob' })
    return URL.createObjectURL(res.data as Blob)
  },
  privacy: (slug: string) => get<{ punch_consent_at: string | null }>(`${w(slug)}/me/privacy`),
  givePunchConsent: (slug: string) => post<{ punch_consent_at: string }>(`${w(slug)}/me/privacy/consent`, {}),
  exportMyData: (slug: string) => get<Record<string, unknown>>(`${w(slug)}/me/export`),
  timeReceipt: (slug: string, uuid: string) => get<TimeReceipt>(`${w(slug)}/time/entries/${uuid}/receipt`),
  myProfile: (slug: string) => get<MyProfile>(`${w(slug)}/me/profile`),
  saveMyProfile: (slug: string, body: Partial<PersonProfileFields> & { birthday_visible?: boolean }) => api.put<ApiResponse<MyProfile>>(`${w(slug)}/me/profile`, body).then((r) => r.data.data),
  personCard: (slug: string, uuid: string) => get<PersonCard>(`${w(slug)}/people/${uuid}/profile`),
  personRecord: (slug: string, uuid: string) => get<PersonRecord>(`${w(slug)}/people/${uuid}/record`),
  savePersonRecord: (slug: string, uuid: string, body: Partial<PersonRecord>) => api.put<ApiResponse<PersonRecord>>(`${w(slug)}/people/${uuid}/record`, body).then((r) => r.data.data),
  jobs: (slug: string) => get<{ items: JobItem[]; can_create: boolean }>(`${w(slug)}/jobs`),
  createJob: (slug: string, body: Record<string, unknown>) => post<JobItem>(`${w(slug)}/jobs`, body),
  updateJob: (slug: string, uuid: string, body: Record<string, unknown>) => patch<JobItem>(`${w(slug)}/jobs/${uuid}`, body),
  deleteJob: (slug: string, uuid: string) => del(`${w(slug)}/jobs/${uuid}`),
  applyJob: (slug: string, uuid: string, message?: string) => post<{ applied: boolean }>(`${w(slug)}/jobs/${uuid}/apply`, { message }),
  withdrawJob: (slug: string, uuid: string) => del(`${w(slug)}/jobs/${uuid}/apply`),
  jobApplications: (slug: string, uuid: string) => get<JobApplicationItem[]>(`${w(slug)}/jobs/${uuid}/applications`),
  setJobApplication: (slug: string, uuid: string, app: string, status: JobApplicationItem['status']) => patch<unknown>(`${w(slug)}/jobs/${uuid}/applications/${app}`, { status }),
  publicJob: (token: string) => api.get<ApiResponse<PublicJob>>(`/public/jobs/${token}`).then((r) => r.data.data),
  reports: (slug: string) => get<ReportItem[]>(`${w(slug)}/reports`),
  report: (slug: string, key: string, month: string) => get<ReportData>(`${w(slug)}/reports/${key}`, month ? { month } : undefined),
  timeIntegrity: (slug: string) => get<IntegrityResult>(`${w(slug)}/time/integrity`),
  timesheet: (slug: string, params: { month?: string; user?: string }) => get<Timesheet>(`${w(slug)}/time/timesheet`, params),
  timeClosings: (slug: string) => get<ClosingItem[]>(`${w(slug)}/time/closings`),
  timeClosing: (slug: string, month: string) => get<ClosingDetail>(`${w(slug)}/time/closings/${month}`),
  closeMonth: (slug: string, month: string) => post<ClosingDetail>(`${w(slug)}/time/closings`, { month }),
  reopenMonth: (slug: string, month: string) => del(`${w(slug)}/time/closings/${month}`),
  async exportClosing(slug: string, month: string): Promise<void> {
    const res = await api.get(`${w(slug)}/time/closings/${month}/export`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `ponto-${month}.csv`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
  },
  timeTeam: (slug: string) => get<TimeTeamRow[]>(`${w(slug)}/time/team`),
  timeBank: (slug: string, user?: string) => get<TimeBank>(`${w(slug)}/time/bank`, { user }),
  timeBankTeam: (slug: string) => get<TimeBankRow[]>(`${w(slug)}/time/bank/team`),
  postBankAdjustment: (slug: string, body: { user_uuid: string; minutes: number; reason: string }) => post<unknown>(`${w(slug)}/time/bank/adjustments`, body),
  manualEntry: (slug: string, body: { user_uuid: string; type: TimeType; punched_at: string; note: string }) =>
    post<unknown>(`${w(slug)}/time/entries`, body),

  timeAdjustments: (slug: string, scope: 'mine' | 'review') => get<TimeAdjustment[]>(`${w(slug)}/time/adjustments`, { scope }),
  requestAdjustment: (slug: string, body: { type: TimeType; requested_at: string; reason: string }) =>
    post<TimeAdjustment>(`${w(slug)}/time/adjustments`, body),
  decideAdjustment: (slug: string, uuid: string, decision: 'approved' | 'rejected', note?: string) =>
    post<TimeAdjustment>(`${w(slug)}/time/adjustments/${uuid}/decision`, { decision, note }),

  // Equipe
  members: (slug: string) => get<WorkspaceMember[]>(`/hotels/${slug}/members`),
  departments: (slug: string) => get<WorkspaceDepartment[]>(`/hotels/${slug}/departments`),
  createDepartment: (slug: string, name: string) => post<WorkspaceDepartment>(`/hotels/${slug}/departments`, { name }),
  deleteDepartment: (slug: string, uuid: string) => del(`/hotels/${slug}/departments/${uuid}`),
  updateMember: (slug: string, userUuid: string, body: { job_title?: string | null; hired_at?: string | null; manager_uuid?: string | null; department_uuid?: string | null }) =>
    patch<WorkspaceMember>(`/hotels/${slug}/members/${userUuid}`, body),

  // Convites
  invites: (slug: string) => get<WorkspaceInvite[]>(`${w(slug)}/invites`),
  createInvite: (slug: string, body: { email?: string | null; department_uuid?: string | null; job_title?: string | null; max_uses?: number; expires_in_days?: number }) =>
    post<WorkspaceInvite>(`${w(slug)}/invites`, body),
  revokeInvite: (slug: string, uuid: string) => del(`${w(slug)}/invites/${uuid}`),
  invitePreview: (token: string) => get<InvitePreview>(`/invites/${token}`),
  acceptInvite: (token: string) => post<{ hotel: { slug: string; name: string } }>(`/invites/${token}/accept`),

  // Mural
  feed: (slug: string, page = 1) => get<Paged<FeedPost>>(`${w(slug)}/feed`, { page }),
  createPost: (slug: string, input: { body: string; pinned?: boolean; requires_ack?: boolean; department_uuid?: string | null; photos?: Blob[]; video?: File | null }) => {
    const form = new FormData()
    form.append('body', input.body)
    if (input.pinned) form.append('pinned', '1')
    if (input.requires_ack) form.append('requires_ack', '1')
    if (input.department_uuid) form.append('department_uuid', input.department_uuid)
    if (input.video) form.append('video', input.video)
    input.photos?.forEach((b, i) => form.append('photos[]', b, `foto-${i + 1}.jpg`))
    return api.post<ApiResponse<FeedPost>>(`${w(slug)}/feed`, form, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data.data)
  },
  ackPost: (slug: string, uuid: string) => post<{ acked_at: string }>(`${w(slug)}/feed/${uuid}/ack`),
  ackPanel: (slug: string, uuid: string) => get<AckPanel>(`${w(slug)}/feed/${uuid}/acks`),
  reactPost: (slug: string, uuid: string, reaction: ReactionType | null) =>
    post<{ reactions: Record<ReactionType, number>; my_reaction: ReactionType | null; likes_count: number }>(`${w(slug)}/feed/${uuid}/react`, { reaction }),
  deletePost: (slug: string, uuid: string) => del(`${w(slug)}/feed/${uuid}`),
  pinPost: (slug: string, uuid: string) => post<{ pinned: boolean }>(`${w(slug)}/feed/${uuid}/pin`),
  likePost: (slug: string, uuid: string) => post<{ liked: boolean; likes_count: number }>(`${w(slug)}/feed/${uuid}/like`),
  comments: (slug: string, uuid: string) => get<FeedComment[]>(`${w(slug)}/feed/${uuid}/comments`),
  addComment: (slug: string, uuid: string, body: string) => post<FeedComment>(`${w(slug)}/feed/${uuid}/comments`, { body }),

  // Solicitações
  requests: (slug: string, params: { scope?: string; status?: string }) => get<Paged<WorkspaceRequest>>(`${w(slug)}/requests`, params),
  request: (slug: string, uuid: string) => get<WorkspaceRequest>(`${w(slug)}/requests/${uuid}`),
  createRequest: (slug: string, body: Record<string, unknown>) => post<WorkspaceRequest>(`${w(slug)}/requests`, body),
  decideRequest: (slug: string, uuid: string, decision: 'approved' | 'rejected', note?: string) =>
    post<WorkspaceRequest>(`${w(slug)}/requests/${uuid}/decision`, { decision, note }),
  assignRequest: (slug: string, uuid: string) => post<WorkspaceRequest>(`${w(slug)}/requests/${uuid}/assign`),
  resolveRequest: (slug: string, uuid: string, note: string) => post<WorkspaceRequest>(`${w(slug)}/requests/${uuid}/resolve`, { note }),
  cancelRequest: (slug: string, uuid: string) => post<WorkspaceRequest>(`${w(slug)}/requests/${uuid}/cancel`),
  commentRequest: (slug: string, uuid: string, body: string) => post<unknown>(`${w(slug)}/requests/${uuid}/comments`, { body }),

  // Recebimentos
  payslips: (slug: string, params: { user?: string; all?: boolean }) =>
    get<Paged<Payslip>>(`${w(slug)}/payslips`, { user: params.user, all: params.all ? 1 : undefined }),
  uploadPayslip: async (slug: string, form: FormData) =>
    (await api.post<ApiResponse<Payslip>>(`${w(slug)}/payslips`, form, { headers: { 'Content-Type': 'multipart/form-data' } })).data.data,
  deletePayslip: (slug: string, uuid: string) => del(`${w(slug)}/payslips/${uuid}`),
  /** Baixa o arquivo com o token do usuário (o disco é privado: não há URL pública). */
  async downloadPayslip(slug: string, p: Payslip): Promise<void> {
    const res = await api.get(`${w(slug)}/payslips/${p.uuid}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = p.original_name
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
  },

  // Papéis de acesso e modelos de segmento
  roles: (slug: string) => get<{ items: RoleRow[]; can_edit: boolean }>(`${w(slug)}/roles`),
  updateRole: (slug: string, uuid: string, access_role: AccessRole) => patch<{ uuid: string; access_role: AccessRole }>(`${w(slug)}/roles/${uuid}`, { access_role }),
  segmentTemplates: () => get<SegmentTemplate[]>('/segment-templates'),
  applySegmentTemplate: (slug: string, segment: string) => post<{ created: string[] }>(`/hotels/${slug}/segment-template`, { segment }),

  // Jornadas (escalas de ponto)
  schedules: (slug: string) => get<SchedulesPayload>(`${w(slug)}/schedules`),
  createSchedule: (slug: string, body: { name: string; week_minutes: Record<string, number> }) => post<WorkSchedule>(`${w(slug)}/schedules`, body),
  updateSchedule: (slug: string, uuid: string, body: { name: string; week_minutes: Record<string, number> }) => patch<WorkSchedule>(`${w(slug)}/schedules/${uuid}`, body),
  deleteSchedule: (slug: string, uuid: string) => del(`${w(slug)}/schedules/${uuid}`),
  assignSchedule: (slug: string, user_uuid: string, schedule_uuid: string | null) => patch<{ user_uuid: string; schedule_uuid: string | null }>(`${w(slug)}/schedules/assign`, { user_uuid, schedule_uuid }),

  // Áreas, chefias e substituições
  areas: (slug: string) => get<AreasPayload>(`${w(slug)}/areas`),
  setAreaHead: (slug: string, area: string, head_uuid: string | null) => patch<unknown>(`${w(slug)}/areas/${area}`, { head_uuid }),
  delegate: (slug: string, body: { from_user_uuid: string; to_user_uuid: string; starts_on: string; ends_on: string }) => post<DelegationItem>(`${w(slug)}/delegations`, body),
  undelegate: (slug: string, uuid: string) => del(`${w(slug)}/delegations/${uuid}`),

  // Calendário
  calendar: (slug: string, from: string, to: string) => get<CalendarPayload>(`${w(slug)}/calendar`, { from, to }),
  createCalendarEvent: (slug: string, body: CalendarEventInput) => post<{ uuid: string }>(`${w(slug)}/calendar/events`, body),
  deleteCalendarEvent: (slug: string, uuid: string) => del(`${w(slug)}/calendar/events/${uuid}`),
  rsvpEvent: (slug: string, uuid: string, status: RsvpStatus | null) => post<unknown>(`${w(slug)}/calendar/events/${uuid}/rsvp`, { status }),
  updateMyBirthday: (slug: string, body: { birthday_visible: boolean }) => patch<unknown>(`${w(slug)}/calendar/me`, body),
  icsLink: (slug: string) => get<{ url: string }>(`${w(slug)}/calendar/ics-link`),
  rotateIcsLink: (slug: string) => post<{ url: string }>(`${w(slug)}/calendar/ics-link`),

  // Segurança da empresa (exigir 2FA para dono, RH e financeiro)
  security: (slug: string) => get<CompanySecurity>(`${w(slug)}/security`),
  saveSecurity: (slug: string, require_2fa: boolean) => api.put<ApiResponse<{ require_2fa: boolean }>>(`${w(slug)}/security`, { require_2fa }).then((r) => r.data.data),

  // Primeiros passos da implantação
  onboarding: (slug: string) => get<OnboardingData>(`${w(slug)}/onboarding`),
  dismissOnboarding: (slug: string) => post<unknown>(`${w(slug)}/onboarding/dismiss`),

  // Painel da diretoria
  dashboard: (slug: string) => get<DashboardData>(`${w(slug)}/dashboard`),

  // Busca global
  search: (slug: string, q: string) => get<SearchResults>(`${w(slug)}/search`, { q }),

  // Auditoria
  audit: (slug: string, params: { page?: number; action?: string }) => get<AuditPage>(`${w(slug)}/audit`, params),
  async exportAudit(slug: string): Promise<void> {
    const res = await api.get(`${w(slug)}/audit/export`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'auditoria.csv'
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
  },

  // Metas
  goals: (slug: string, params: { scope?: string; status?: string; user?: string }) => get<Goal[]>(`${w(slug)}/goals`, params),
  goal: (slug: string, uuid: string) => get<Goal>(`${w(slug)}/goals/${uuid}`),
  goalsSummary: (slug: string) => get<GoalSummaryRow[]>(`${w(slug)}/goals/summary`),
  createGoal: (slug: string, body: Record<string, unknown>) => post<Goal>(`${w(slug)}/goals`, body),
  updateGoal: (slug: string, uuid: string, body: Record<string, unknown>) => patch<Goal>(`${w(slug)}/goals/${uuid}`, body),
  addGoalProgress: (slug: string, uuid: string, value: number, note?: string) => post<Goal>(`${w(slug)}/goals/${uuid}/updates`, { value, note }),

  // PDI
  plans: (slug: string, scope: string) => get<DevelopmentPlan[]>(`${w(slug)}/development`, { scope }),
  plan: (slug: string, uuid: string) => get<DevelopmentPlan>(`${w(slug)}/development/${uuid}`),
  createPlan: (slug: string, body: Record<string, unknown>) => post<DevelopmentPlan>(`${w(slug)}/development`, body),
  updatePlan: (slug: string, uuid: string, body: Record<string, unknown>) => patch<DevelopmentPlan>(`${w(slug)}/development/${uuid}`, body),
  addPlanItem: (slug: string, uuid: string, body: Record<string, unknown>) => post<DevelopmentItem>(`${w(slug)}/development/${uuid}/items`, body),
  updatePlanItem: (slug: string, uuid: string, item: string, body: Record<string, unknown>) => patch<DevelopmentItem>(`${w(slug)}/development/${uuid}/items/${item}`, body),
  deletePlanItem: (slug: string, uuid: string, item: string) => del(`${w(slug)}/development/${uuid}/items/${item}`),
  addPlanNote: (slug: string, uuid: string, body: string) => post<unknown>(`${w(slug)}/development/${uuid}/notes`, { body }),
}

export default workspaceService
