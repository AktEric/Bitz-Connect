/** Tipos do Workspace (intranet da empresa). */

export interface WorkspacePerson {
  uuid: string
  name: string
  avatar_url: string | null
  job_title: string | null
}

export interface WorkspaceSummary {
  hotel: { uuid: string; slug: string; name: string; segment: string | null; avatar_url: string | null }
  role: 'admin' | 'employee'
  job_title: string | null
  department: string | null
}

export interface WorkspaceInfo {
  hotel: { uuid: string; slug: string; name: string; segment: string | null; city: string | null; state: string | null; avatar_url: string | null }
  me: {
    role: 'admin' | 'employee'
    is_admin: boolean
    access_role?: 'owner' | 'hr' | 'finance' | 'manager' | 'employee'
    is_manager: boolean
    two_factor?: boolean
    can_see_dashboard?: boolean
    needs_two_factor?: boolean
    team_size?: number
    areas_headed?: string[]
    can_post: boolean
    job_title: string | null
    hired_at: string | null
    department: string | null
    manager: WorkspacePerson | null
  }
  counts: { members: number; requests_to_review: number; my_open_requests: number; new_payslips: number; pending_acks?: number }
}

export interface WorkspaceMember {
  uuid: string
  name: string
  avatar_url: string | null
  role: 'admin' | 'employee'
  job_title: string | null
  hired_at: string | null
  department: { uuid: string; name: string } | null
  manager: { uuid: string; name: string } | null
}

export interface WorkspaceDepartment {
  uuid: string
  name: string
  members_count?: number
}

export interface WorkspaceInvite {
  uuid: string
  token: string
  path: string
  email: string | null
  department: string | null
  job_title: string | null
  max_uses: number
  uses: number | null
  expires_at: string | null
  revoked: boolean
  usable: boolean
}

export interface InvitePreview {
  hotel: { name: string; slug: string; avatar_url: string | null }
  department: string | null
  job_title: string | null
  email: string | null
  usable: boolean
}

export type ReactionType = 'like' | 'love' | 'celebrate' | 'clap'

export interface FeedMedia {
  uuid: string
  /** URL relativa assinada (expira em ~30 min); recarregar o mural gera outra. */
  url: string
  kind?: 'image' | 'video'
}

export interface FeedPost {
  uuid: string
  kind: 'post' | 'birthday'
  subject: WorkspacePerson | null
  comments_preview: FeedComment[]
  body: string
  media: FeedMedia[]
  mentions: Record<string, string>
  reactions: Record<ReactionType, number>
  my_reaction: ReactionType | null
  requires_ack: boolean
  acked_at: string | null
  /** Só para o autor e o RH. */
  ack_count: number | null
  ack_total: number | null
  pinned: boolean
  created_at: string
  author: WorkspacePerson | null
  department: string | null
  likes_count: number
  comments_count: number
  liked_by_me: boolean
  can_delete: boolean
}

export interface FeedComment {
  uuid: string
  body: string
  mentions?: Record<string, string>
  created_at: string
  author: WorkspacePerson | null
}

export type RequestType = 'vacation' | 'document' | 'reimbursement' | 'schedule' | 'other' | 'ticket'
export type RequestStatus = 'pending' | 'in_progress' | 'resolved' | 'approved' | 'rejected' | 'cancelled'
export type TicketCategory = 'time' | 'payroll' | 'benefits' | 'documents' | 'other'

export interface WorkspaceRequest {
  uuid: string
  type: RequestType
  title: string
  description: string | null
  starts_on: string | null
  ends_on: string | null
  amount_cents: number | null
  status: RequestStatus
  decision_note: string | null
  decided_at: string | null
  decided_by: WorkspacePerson | null
  requester: WorkspacePerson | null
  created_at: string
  is_mine: boolean
  category?: TicketCategory | null
  assigned_to?: WorkspacePerson | null
  due_at?: string | null
  first_response_at?: string | null
  overdue?: boolean
  comments?: { uuid: number; body: string; created_at: string; author: WorkspacePerson | null }[]
}

export interface Payslip {
  uuid: string
  kind: 'payslip' | 'receipt' | 'bonus' | 'other'
  title: string
  reference_month: string | null
  amount_cents: number | null
  original_name: string
  mime: string
  size: number
  viewed: boolean
  first_viewed_at: string | null
  owner: WorkspacePerson | null
  created_at: string
}

export type GoalStatus = 'active' | 'achieved' | 'missed' | 'cancelled'

export interface Goal {
  uuid: string
  title: string
  description: string | null
  unit: string
  target_value: number
  current_value: number
  progress: number
  period_start: string
  period_end: string
  status: GoalStatus
  owner: WorkspacePerson | null
  set_by: WorkspacePerson | null
  updates?: { value: number; note: string | null; created_at: string; author: WorkspacePerson | null }[]
}

export interface GoalSummaryRow {
  person: WorkspacePerson
  total: number
  achieved: number
  missed: number
  active: number
  avg_progress: number
}

export type PlanItemType = 'course' | 'mentoring' | 'project' | 'reading' | 'other'
export type PlanItemStatus = 'todo' | 'doing' | 'done'

export interface DevelopmentItem {
  uuid: string
  title: string
  type: PlanItemType
  due_on: string | null
  status: PlanItemStatus
  notes: string | null
}

export interface DevelopmentPlan {
  uuid: string
  title: string
  objective: string | null
  period_start: string | null
  period_end: string | null
  status: 'active' | 'completed' | 'archived'
  owner: WorkspacePerson | null
  items_total: number
  items_done: number
  progress: number
  items?: DevelopmentItem[]
  notes?: { uuid: number; body: string; created_at: string; author: WorkspacePerson | null }[]
}

export interface Paged<T> {
  items: T[]
  page: number
  last_page: number
  can_post?: boolean
}

// ── Ponto ────────────────────────────────────────────────────
export type TimeType = 'in' | 'break_start' | 'break_end' | 'out'
export type PunchMode = 'off' | 'optional' | 'required'
export type TimeState = 'out' | 'working' | 'break'

export interface TimeEntryItem {
  uuid: string
  type: TimeType
  punched_at: string
  source: 'web' | 'mobile' | 'manual'
  note: string | null
  local_time?: string
  lat?: number | null
  lng?: number | null
  accuracy_m?: number | null
  has_photo?: boolean
  outside_fence?: boolean
  ip?: string | null
  device_name?: string | null
  device_label?: string | null
  network_name?: string | null
  outside_network?: boolean
  new_device?: boolean
  nsr?: number | null
}

export interface TimeReceipt {
  company: { name: string; cnpj: string | null }
  person: { name: string | null }
  type: TimeType
  punched_at: string
  local_date: string
  local_time: string
  source: string
  device: string | null
  network: string | null
  nsr: number | null
  hash: string | null
}

export interface ReportItem { key: string; title: string; description: string; monthly: boolean }
export interface ReportData { key: string; title: string; month: string | null; generated_at: string; columns: string[]; rows: (string | number)[][] }

export interface IntegrityResult { ok: boolean; checked: number; first_broken_nsr: number | null; reason: string | null }

export interface TimeToday {
  state: TimeState
  allowed_next: TimeType[]
  worked_minutes: number
  daily_minutes: number
  server_time: string
  requirements?: { geo: PunchMode; photo: PunchMode; fence: boolean; devices?: 'off' | 'log' | 'required'; networks?: boolean }
  entries: TimeEntryItem[]
}

export interface TimesheetDay {
  date: string
  weekday: number
  worked_minutes: number
  expected_minutes: number
  holiday?: string | null
  entries: TimeEntryItem[]
}

export interface Timesheet {
  month: string
  user: WorkspacePerson | null
  worked_minutes: number
  expected_minutes: number
  balance_minutes: number
  days: TimesheetDay[]
}

export interface TimeTeamRow {
  person: WorkspacePerson | null
  state: TimeState
  worked_minutes: number
  first_in: string | null
}

export interface TimeAdjustment {
  uuid: string
  type: TimeType
  requested_at: string
  reason: string
  status: 'pending' | 'approved' | 'rejected'
  review_note: string | null
  person: WorkspacePerson | null
}

export type AccessRole = 'owner' | 'hr' | 'finance' | 'director' | 'manager' | 'employee'

export interface RoleRow {
  user: { uuid: string; name: string; avatar_url: string | null; job_title: string | null }
  email: string | null
  access_role: AccessRole
}

export interface SegmentTemplate {
  key: string
  label: string
  departments: string[]
  job_titles: string[]
}

export interface WorkSchedule {
  uuid: string
  name: string
  /** Minutos esperados por dia da semana: 0 (domingo) a 6 (sábado). */
  week_minutes: Record<string, number>
  weekly_total: number
  members_count: number
}

export interface SchedulesPayload {
  default: { name: string; week_minutes: Record<string, number> }
  schedules: WorkSchedule[]
  members: { user: { uuid: string; name: string; avatar_url: string | null; job_title: string | null }; schedule_uuid: string | null }[]
}

export interface TimeBankAdjustment {
  uuid: string
  minutes: number
  reason: string
  author: { uuid: string; name: string; avatar_url: string | null } | null
  created_at: string
}

export interface TimeBank {
  since: string | null
  worked_minutes: number
  expected_minutes: number
  entries_balance: number
  adjustments_minutes: number
  balance_minutes: number
  user: { uuid: string; name: string; avatar_url: string | null } | null
  adjustments: TimeBankAdjustment[]
}

export interface TimeBankRow {
  person: { uuid: string; name: string; avatar_url: string | null } | null
  since: string | null
  balance_minutes: number
}

export interface ClosingRow {
  user_uuid: string
  name: string
  job_title: string | null
  department: string | null
  days_worked: number
  absent_days: number
  worked_minutes: number
  expected_minutes: number
  balance_minutes: number
  bank_minutes: number
}

export interface ClosingItem {
  month: string
  status: 'open' | 'closed'
  closed_at: string | null
  closed_by: { uuid: string; name: string; avatar_url: string | null } | null
  people: number | null
  pending_adjustments?: number
}

export interface ClosingDetail extends Omit<ClosingItem, 'people'> {
  rows: ClosingRow[]
}

export interface PunchSettings {
  geo: PunchMode
  photo: PunchMode
  fence: { lat: number; lng: number; radius_m: number; block: boolean } | null
  devices?: 'off' | 'log' | 'required'
  networks?: { name: string; cidr: string }[]
  networks_block?: boolean
  /** IP que o servidor enxerga nesta conexão (só na leitura, para facilitar o cadastro de redes). */
  current_ip?: string
}

export type DeviceStatus = 'unknown' | 'pending' | 'approved' | 'blocked'

export interface MyDevice {
  mode: 'off' | 'log' | 'required'
  status: DeviceStatus
  name: string | null
}

export interface TimeDeviceItem {
  uuid: string
  name: string
  status: 'pending' | 'approved' | 'blocked'
  label: string | null
  last_ip: string | null
  last_seen_at: string | null
  registered_by: { uuid: string; name: string; avatar_url: string | null } | null
  users_count: number
}

export interface AreaPerson { uuid: string; name: string; avatar_url: string | null; job_title: string | null }

export interface AreaItem {
  uuid: string
  name: string
  members_count: number
  head: AreaPerson | null
}

export interface DelegationItem {
  uuid: string
  from: AreaPerson | null
  to: AreaPerson | null
  starts_on: string
  ends_on: string
  active: boolean
}

export interface AreasPayload {
  areas: AreaItem[]
  delegations: DelegationItem[]
  people: AreaPerson[]
}

export type CalendarItemType = 'birthday' | 'anniversary' | 'holiday' | 'event' | 'vacation'
export type RsvpStatus = 'going' | 'maybe' | 'no'

export interface CalendarItem {
  type: CalendarItemType
  date: string
  end_date: string | null
  title: string
  subtitle: string | null
  person: { uuid: string; name: string; avatar_url: string | null; job_title: string | null } | null
  all_day: boolean
  time: string | null
  hidden_from_team?: boolean
  event: null | {
    uuid: string
    kind: 'event' | 'holiday'
    description: string | null
    location: string | null
    department: string | null
    can_edit: boolean
    rsvp: { mine: RsvpStatus | null; going: number; maybe: number; no: number }
  }
}

export interface CalendarPayload {
  from: string
  to: string
  items: CalendarItem[]
  can_create: boolean
  can_holiday: boolean
  me: { birth_date: string | null; birthday_visible: boolean }
}

export interface CalendarEventInput {
  title: string
  kind: 'event' | 'holiday'
  starts_on: string
  ends_on: string | null
  starts_time: string | null
  ends_time: string | null
  location: string | null
  description: string | null
  department_uuid: string | null
}

export interface CompanySecurity {
  require_2fa: boolean
  privileged_total: number
  privileged_without_2fa: ({ uuid: string; name: string; avatar_url: string | null; job_title: string | null } | null)[]
  can_edit: boolean
}

export interface AuditItem {
  action: string
  actor: { uuid: string; name: string; avatar_url: string | null } | null
  target: { uuid: string; name: string; avatar_url: string | null } | null
  meta: Record<string, unknown> | null
  ip: string | null
  created_at: string
}

export interface AuditPage {
  items: AuditItem[]
  page: number
  last_page: number
  actions: string[]
}

export interface SearchResults {
  people: { uuid: string; name: string; avatar_url: string | null; job_title: string | null; department: string | null }[]
  posts: { uuid: string; excerpt: string; author: string | null; created_at: string | null }[]
  events: { uuid: string; title: string; kind: 'event' | 'holiday'; date: string }[]
  requests: { uuid: string; title: string; status: string; type: string; requester: string | null }[]
}

export interface AckPanel {
  total: number
  readers: { person: { uuid: string; name: string; avatar_url: string | null; job_title: string | null }; acked_at: string }[]
  pending: { uuid: string; name: string; avatar_url: string | null; job_title: string | null; department?: string | null }[]
}

export interface DashboardData {
  generated_at: string
  min_group: number
  people: { headcount: number; hires_90d: number; birthdays_month: number; avg_tenure_years: number | null }
  today: { present: number; working_now: number; on_vacation: number; holiday: string | null }
  time: {
    bank_to_receive_minutes: number; bank_to_compensate_minutes: number; people_with_heavy_debt: number
    outside_fence_7d: number; outside_network_7d: number; new_devices_7d: number; pending_devices: number; pending_adjustments: number
  }
  requests: { pending: number; pending_by_type: Record<string, number>; decided_90d: number; avg_decision_hours: number | null; approval_rate: number | null }
  engagement: { posts_30d: number; reactions_30d: number; comments_30d: number; upcoming_events: number; recent_acks: { title: string; confirmed: number; total: number }[] }
  security: { two_factor_adoption: number | null; privileged_without_2fa: number; require_2fa: boolean }
  departments: { name: string; headcount: number; bank_minutes: number | null }[]
}

export interface OnboardingData {
  steps: { key: string; title: string; hint: string; to: string; cta: string; done: boolean }[]
  done: number
  total: number
  percent: number
  dismissed: boolean
}

export interface PersonProfileFields {
  bio: string | null; favorite_book: string | null; favorite_food: string | null; favorite_movie: string | null
  hobbies: string | null; fun_fact: string | null; hometown: string | null
}
export interface PersonCard {
  person: WorkspacePerson | null
  department: string | null
  hired_at: string | null
  birthday: { day: number; month: number } | null
  profile: PersonProfileFields
}
export interface MyProfile extends PersonCard { birthday_visible: boolean; has_birth_date: boolean; email: string }
export interface PersonRecord {
  birth_date: string | null; hired_at: string | null
  phone: string | null; zip: string | null; street: string | null; number: string | null; complement: string | null; district: string | null; city: string | null; state: string | null
  emergency_name: string | null; emergency_relation: string | null; emergency_phone: string | null
  bank: string | null; agency: string | null; account: string | null; account_type: string | null; pix: string | null
  can_edit: boolean
}

export interface JobItem {
  uuid: string; title: string; description: string; requirements: string | null; location: string | null
  contract_type: string | null; contact: string | null; department: string | null; department_uuid: string | null
  status: 'open' | 'closed'; public: boolean; closes_on: string | null; created_at: string
  author: WorkspacePerson | null; applied: boolean; can_edit: boolean; applications_count: number | null; share_url: string | null
}
export interface JobApplicationItem { uuid: string; status: 'new' | 'reviewing' | 'rejected' | 'hired'; message: string | null; created_at: string; person: WorkspacePerson | null; department: string | null }
export interface PublicJob { title: string; description: string; requirements: string | null; location: string | null; contract_type: string | null; contact: string | null; department: string | null; company: string | null; closes_on: string | null }
