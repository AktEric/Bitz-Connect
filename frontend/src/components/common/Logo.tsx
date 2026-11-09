import { cn } from '@/utils/cn'

interface LogoProps {
  /** Altura da marca (a largura acompanha a proporção). */
  iconClassName?: string
  textClassName?: string
  showText?: boolean
  variant?: 'default' | 'white'
}

/**
 * Marca Bitz Connect: logotipo "bitz" (preto e azul; branco e azul em fundo escuro) + "Connect".
 * Sem texto, mostra só o ícone (o "b" da Bitz).
 */
export default function Logo({
  iconClassName = 'h-8 w-8',
  textClassName = 'text-lg',
  showText = true,
  variant = 'default',
}: LogoProps) {
  if (!showText) {
    return <img src="/logo-icon.png" alt="Bitz Connect" className={cn('rounded-lg', iconClassName)} />
  }

  return (
    <>
      <img
        src={variant === 'white' ? '/logo-bitz-word-white.png' : '/logo-bitz-word.png'}
        alt="Bitz"
        className={cn(iconClassName, 'w-auto')}
      />
      <span className={cn('font-semibold tracking-tight', textClassName, variant === 'white' ? 'text-white' : 'text-brand-600')}>
        Connect
      </span>
    </>
  )
}
