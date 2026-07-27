interface LogoProps {
  iconClassName?: string
  textClassName?: string
  showText?: boolean
  variant?: 'default' | 'white'
}

/**
 * Marca oficial HotelConnect (ícone + wordmark), conforme manual da marca.
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
      <img src={iconSrc} alt="HotelConnect" className={iconClassName} />
      {showText && (
        <span className={`font-bold tracking-tight ${textClassName}`}>
          {variant === 'white' ? (
            <span className="text-white">HotelConnect</span>
          ) : (
            <>
              <span className="text-[#1B1240]">Hotel</span>
              <span className="bg-gradient-to-r from-[#4D14D9] to-[#7C3AED] bg-clip-text text-transparent">
                Connect
              </span>
            </>
          )}
        </span>
      )}
    </>
  )
}
