import api from './api'
import type { ApiResponse } from '@/types/api.types'

export interface HelpMessage {
  role: 'user' | 'assistant'
  content: string
}

/** Conversa com a Bianca (central de ajuda). Envia o histórico recente e o contexto (empresa e tela). */
export async function askBianca(messages: HelpMessage[], ctx: { workspace?: string; page?: string }): Promise<string> {
  const res = await api.post<ApiResponse<{ reply: string }>>('/help/chat', {
    messages: messages.slice(-12),
    workspace: ctx.workspace,
    page: ctx.page,
  })
  return res.data.data.reply
}

const helpService = { askBianca }
export default helpService
