export type BenefitColor = 'green' | 'blue' | 'amber' | 'rose' | 'purple' | 'teal' | 'orange' | 'gray'

export interface BenefitItem {
  uuid: string
  type: { uuid: string; name: string; color: BenefitColor }
  amount_cents: number | null
  frequency: 'monthly' | 'one_time'
  deposit_day: number | null
  deposit_on: string | null
  next_deposit_on: string | null
  starts_on: string | null
  ends_on: string | null
  active: boolean
  note: string | null
}
export interface BenefitList { items: BenefitItem[]; total_monthly_cents: number }

export interface BenefitTypeRow { uuid: string; name: string; color: BenefitColor; active: boolean }
export interface BenefitTypes { types: BenefitTypeRow[]; colors: BenefitColor[] }

export interface BenefitPackageItem { benefit_type_uuid: string; name: string; color: BenefitColor; amount_cents: number | null; deposit_day: number | null; note: string | null }
export interface BenefitPackage { uuid: string; name: string; items: BenefitPackageItem[] }

export interface BenefitInput {
  benefit_type_uuid: string
  amount: number | null
  frequency: 'monthly' | 'one_time'
  deposit_day: number | null
  deposit_on: string | null
  starts_on: string | null
  ends_on: string | null
  note: string | null
}
