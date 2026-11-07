import api from './api'
import type {
  LoginCredentials,
  RegisterData,
  AuthTokenResponse,
  ForgotPasswordData,
  ResetPasswordData,
  ChangePasswordData,
} from '@/types/auth.types'
import type { ApiResponse } from '@/types/api.types'

/**
 * Auth service — wraps all /auth API calls.
 * Requirements: 1.1, 1.3, 1.5, 1.6, 1.7, 1.8
 */
const authService = {
  async register(data: RegisterData): Promise<ApiResponse<AuthTokenResponse>> {
    const res = await api.post<ApiResponse<AuthTokenResponse>>('/auth/register', data)
    return res.data
  },

  async login(data: LoginCredentials): Promise<ApiResponse<AuthTokenResponse>> {
    const res = await api.post<ApiResponse<AuthTokenResponse>>('/auth/login', data)
    return res.data
  },

  async providers(): Promise<{ google_client_id: string | null }> {
    const res = await api.get<ApiResponse<{ google_client_id: string | null }>>('/auth/providers')
    return res.data.data
  },

  async google(credential: string): Promise<ApiResponse<AuthTokenResponse>> {
    const res = await api.post<ApiResponse<AuthTokenResponse>>('/auth/google', { credential })
    return res.data
  },

  async logout(): Promise<void> {
    await api.post('/auth/logout')
  },

  async forgotPassword(data: ForgotPasswordData): Promise<ApiResponse<null>> {
    const res = await api.post<ApiResponse<null>>('/auth/forgot-password', data)
    return res.data
  },

  async resetPassword(data: ResetPasswordData): Promise<ApiResponse<null>> {
    const res = await api.post<ApiResponse<null>>('/auth/reset-password', data)
    return res.data
  },

  async changePassword(data: ChangePasswordData): Promise<ApiResponse<null>> {
    const res = await api.put<ApiResponse<null>>('/auth/password', data)
    return res.data
  },
}

export default authService
