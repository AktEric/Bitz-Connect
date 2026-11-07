export interface TwoFactorAnswer { code?: string; recovery_code?: string }

type Requester = (message?: string) => Promise<TwoFactorAnswer | null>
let requester: Requester | null = null

/** Chamado pelo componente <TwoFactorPrompt/> para receber os pedidos de código. */
export function registerTwoFactorRequester(fn: Requester | null) { requester = fn }

type ApiErr = { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
const errors = (e: unknown) => (e as ApiErr)?.response?.data?.errors

export const needsTwoFactor = (e: unknown) => (e as ApiErr)?.response?.status === 422 && !!errors(e)?.two_factor_required

/**
 * Executa um login; se o servidor pedir a segunda etapa, pergunta o código ao usuário e repete a chamada
 * (inclusive quando o código estiver errado). Cancelar devolve o erro original.
 */
export async function withTwoFactor<T>(call: (extra: TwoFactorAnswer) => Promise<T>): Promise<T> {
  try {
    return await call({})
  } catch (first) {
    if (!needsTwoFactor(first) || !requester) throw first

    let message: string | undefined
    for (let attempt = 0; attempt < 8; attempt++) {
      const answer = await requester(message)
      if (!answer) throw first
      try {
        return await call(answer)
      } catch (err) {
        const msg = errors(err)?.code?.[0]
        if (!msg) throw err
        message = msg
      }
    }
    throw first
  }
}
