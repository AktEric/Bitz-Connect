interface LogoProps {
  iconClassName?: string
  textClassName?: string
  showText?: boolean
  variant?: 'default' | 'white'
}

/**
 * Marca oficial ConnectHotel (ícone + wordmark), conforme manual da marca.
 */
export default function Logo({
  iconClassName = 'h-8 w-8',
  textClassName = 'text-lg',
  showText = true,
  variant = 'default',
}: LogoProps) {
  const iconSrc = variant === 'white' ? '/logo-mark-white.svg' : '/logo-mark.svg'

  return (
    <>
      <img src={iconSrc} alt="ConnectHotel" className={iconClassName} />
      {showText && (
        <span className={`font-bold tracking-tight ${textClassName}`}>
          {variant === 'white' ? (
            <span className="text-white">ConnectHotel</span>
          ) : (
            <>
              <span className="bg-gradient-to-r from-[#5B21E0] to-[#4D14D9] bg-clip-text text-transparent">
                Connect
              </span>
              <span className="text-[#0E1327]">Hotel</span>
            </>
          )}
        </span>
      )}
    </>
  )
}
