import { Component, type ErrorInfo, type ReactNode } from 'react'
import { AlertTriangle } from 'lucide-react'
import { isStaleChunkError, reloadForNewVersion } from '@/utils/staleChunk'

interface Props {
  children: ReactNode
}

interface State {
  error: Error | null
}

/**
 * Captura erros de render em qualquer página e exibe uma mensagem amigável em
 * vez de uma tela em branco. Em desenvolvimento, mostra a mensagem do erro para
 * facilitar o diagnóstico.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Log para aparecer no console do navegador.
    console.error('[ErrorBoundary]', error, info.componentStack)
    // Aba aberta numa versão antiga do site: busca a versão nova em vez de mostrar o erro.
    if (isStaleChunkError(error)) reloadForNewVersion()
  }

  handleReset = () => {
    this.setState({ error: null })
  }

  render() {
    if (this.state.error) {
      return (
        <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 px-6 text-center">
          <span className="rounded-full bg-red-50 p-3 text-red-500">
            <AlertTriangle className="h-7 w-7" />
          </span>
          <h1 className="text-lg font-semibold text-gray-900">Algo deu errado nesta página</h1>
          <p className="max-w-md text-sm text-gray-500">
            Ocorreu um erro ao carregar este conteúdo. Tente novamente ou volte mais tarde.
          </p>
          {import.meta.env.DEV && (
            <pre className="mt-2 max-w-xl overflow-auto rounded-lg bg-gray-900 p-3 text-left text-xs text-red-300">
              {this.state.error.message}
            </pre>
          )}
          <div className="mt-2 flex gap-2">
            <button
              onClick={this.handleReset}
              className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
            >
              Tentar novamente
            </button>
            <a
              href="/feed"
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Voltar ao início
            </a>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}
