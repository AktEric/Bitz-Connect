import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type {
  DevelopmentItem, DevelopmentPlan, FeedComment, FeedPost, Goal, GoalSummaryRow, InvitePreview,
  Paged, Payslip, WorkspaceDepartment, WorkspaceInfo, WorkspaceInvite, WorkspaceMember,
  WorkspaceRequest, WorkspaceSummary, TimeToday, Timesheet, TimeTeamRow, TimeType, TimeAdjustment,
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
  punch: (slug: string, type?: TimeType) => post<TimeToday>(`${w(slug)}/time/punch`, { type, source: 'web' }),
  timesheet: (slug: string, params: { month?: string; user?: string }) => get<Timesheet>(`${w(slug)}/time/timesheet`, params),
  timeTeam: (slug: string) => get<TimeTeamRow[]>(`${w(slug)}/time/team`),
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
  createPost: (slug: string, body: { body: string; pinned?: boolean; department_uuid?: string | null }) => post<FeedPost>(`${w(slug)}/feed`, body),
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
