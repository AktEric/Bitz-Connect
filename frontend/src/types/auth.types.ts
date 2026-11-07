export interface User {
  uuid: string
  name: string
  email: string
  avatar_url: string | null
  banner_url: string | null
  bio: string | null
  specialty: string | null
  followers_count?: number
  videos_count?: number
  city: string | null
  state: string | null
  website: string | null
  social_links: Record<string, string> | null
  role: 'user' | 'platform_admin'
  created_at: string
  experiences?: import('./user.types').Experience[]
  connection_status?: import('./api.types').ConnectionStatus
  // Subscription info — present when the backend eager-loads it (profile/me).
  plan?: import('./subscription.types').PlanTier
  is_pro?: boolean
  is_creator?: boolean
  is_following?: boolean
  rating_avg?: number
  reviews_count?: number
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthTokenResponse {
  token: string
  user: User
}

export interface ForgotPasswordData {
  email: string
}

export interface ResetPasswordData {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface ChangePasswordData {
  current_password: string
  password: string
  password_confirmation: string
}

export interface SecurityOverview {
  two_factor: { enabled: boolean; confirmed_at: string | null; recovery_left: number }
  sessions: { id: number; label: string; ip: string | null; created_at: string | null; last_used_at: string | null; current: boolean }[]
}
