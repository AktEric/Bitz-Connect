import { Link } from 'react-router-dom'
import { FileText, Briefcase, Building2, Settings, CheckCircle2, Circle, User } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { useMyConnections } from '@/hooks/useConnections'
import { Avatar } from '@/components/ui/Avatar'
import { Card } from '@/components/ui/Card'

const QUICK_LINKS = [
  { label: 'Meu Perfil',           to: '/profile',      Icon: User },
  { label: 'Minhas Publicações',   to: '/profile',      Icon: FileText },
  { label: 'Minhas Candidaturas',  to: '/jobs',         Icon: Briefcase },
  { label: 'Empresas Vinculadas',    to: '/network',      Icon: Building2 },
  { label: 'Configurações',        to: '/profile/edit', Icon: Settings },
]

function ProfileCompletion() {
  const { user } = useAuth()
  if (!user) return null

  const steps = [
    { label: 'Foto de perfil',          done: !!user.avatar_url, pts: 20 },
    { label: 'Biografia',               done: !!user.bio,        pts: 20 },
    { label: 'Cidade',                  done: !!user.city,       pts: 15 },
    { label: 'Experiência profissional', done: (user.experiences?.length ?? 0) > 0, pts: 25 },
    { label: 'Website',                 done: !!user.website,    pts: 20 },
  ]
  const percent = steps.reduce((a, s) => (s.done ? a + s.pts : a), 0)

  return (
    <Card className="mb-3">
      <h3 className="text-sm font-semibold text-gray-900 mb-3">Complete seu perfil</h3>
      <div className="mb-3">
        <div className="flex items-center justify-between mb-1.5">
          <span className="text-xs text-gray-500">{percent}% completo</span>
          <span className="text-xs font-medium text-violet-600">{percent < 100 ? 'Em andamento' : 'Completo!'}</span>
        </div>
        <div className="h-1.5 w-full rounded-full bg-gray-100">
          <div className="h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-violet-600 transition-all duration-700"
            style={{ width: `${percent}%` }} />
        </div>
      </div>
      <ul className="space-y-1.5">
        {steps.map((s) => (
          <li key={s.label} className="flex items-center gap-2 text-xs">
            {s.done
              ? <CheckCircle2 className="h-3.5 w-3.5 flex-shrink-0 text-violet-500" />
              : <Circle       className="h-3.5 w-3.5 flex-shrink-0 text-gray-200" />}
            <span className={s.done ? 'text-gray-400 line-through' : 'text-gray-600'}>{s.label}</span>
          </li>
        ))}
      </ul>
    </Card>
  )
}

export default function SidebarLeft() {
  const { user } = useAuth()
  const { data: connections } = useMyConnections()
  if (!user) return null

  const subtitle = user.experiences?.length ? user.experiences[0].title : 'Profissional'
  const connectionsCount = Array.isArray(connections) ? connections.length : 0

  return (
    <div className="w-full">
      {/* User card */}
      <Card noPad className="mb-3 overflow-hidden">
        <div className="h-16 w-full bg-gradient-to-r from-violet-600 to-violet-800"
          style={user.banner_url ? { backgroundImage: `url(${user.banner_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined} />
        <div className="px-4 pb-4">
          <div className="-mt-7 mb-2">
            <Avatar src={user.avatar_url} name={user.name} size="lg" ring />
          </div>
          <Link to="/profile" className="block text-sm font-semibold text-gray-900 hover:text-violet-600 transition-colors leading-tight">
            {user.name}
          </Link>
          <p className="mt-0.5 text-xs text-gray-500 leading-snug truncate">{subtitle}</p>
          {user.city && (
            <p className="mt-0.5 text-xs text-gray-400">{user.city}{user.state ? `, ${user.state}` : ''}</p>
          )}
          <div className="mt-3 border-t border-gray-100 pt-3">
            <Link to="/network" className="flex items-center justify-between rounded-lg px-1 py-0.5 hover:bg-gray-50 transition-colors">
              <span className="text-xs text-gray-500">Conexões</span>
              <span className="text-xs font-semibold text-violet-600">{connectionsCount}</span>
            </Link>
          </div>
        </div>
      </Card>

      {/* Quick links */}
      <Card className="mb-3">
        <h3 className="text-sm font-semibold text-gray-900 mb-2">Atalhos</h3>
        <ul className="space-y-0.5">
          {QUICK_LINKS.map(({ label, to, Icon }) => (
            <li key={label}>
              <Link to={to}
                className="flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm text-gray-600 hover:bg-violet-50 hover:text-violet-700 transition-colors">
                <Icon className="h-4 w-4 flex-shrink-0" />
                {label}
              </Link>
            </li>
          ))}
        </ul>
      </Card>

      <ProfileCompletion />
    </div>
  )
}
