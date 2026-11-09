import { useNavigate } from 'react-router-dom'
import { User, Building2 } from 'lucide-react'

/**
 * Página de seleção de tipo de cadastro.
 * Apresenta dois cards: "Criar conta de Pessoa" e "Criar conta de Hotel".
 * Requirements: 2.1
 */
export default function RegisterSelectPage() {
  const navigate = useNavigate()

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 p-4">
      <div className="w-full max-w-lg">
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-bold text-brand-900">Criar conta</h1>
          <p className="mt-2 text-sm text-brand-500">
            Escolha o tipo de conta que deseja criar
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          {/* Card: Pessoa */}
          <button
            type="button"
            onClick={() => navigate('/register/pessoa')}
            className="group flex flex-col items-center gap-4 rounded-xl border-2 border-brand-100 bg-white p-8 text-center shadow transition-all duration-200 hover:border-brand-400 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          >
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 transition-colors duration-200 group-hover:bg-brand-200">
              <User className="h-8 w-8 text-brand-600" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">Criar conta de Pessoa</h2>
              <p className="mt-1 text-sm text-gray-500">
                Para profissionais do setor hoteleiro que buscam oportunidades e conexões.
              </p>
            </div>
          </button>

          {/* Card: Hotel */}
          <button
            type="button"
            onClick={() => navigate('/register/hotel')}
            className="group flex flex-col items-center gap-4 rounded-xl border-2 border-amber-100 bg-white p-8 text-center shadow transition-all duration-200 hover:border-amber-400 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
          >
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 transition-colors duration-200 group-hover:bg-amber-200">
              <Building2 className="h-8 w-8 text-amber-600" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">Criar conta de Hotel</h2>
              <p className="mt-1 text-sm text-gray-500">
                Para empresas de qualquer segmento que desejam publicar vagas, gerenciar equipes e conectar profissionais.
              </p>
            </div>
          </button>
        </div>

        <p className="mt-6 text-center text-sm text-gray-600">
          Já tem conta?{' '}
          <button
            type="button"
            onClick={() => navigate('/login/pessoa')}
            className="text-brand-600 hover:underline"
          >
            Entrar
          </button>
        </p>
      </div>
    </div>
  )
}
