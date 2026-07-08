import type { JobFiltersParams } from '@/services/job.service'

interface JobFiltersProps {
  filters: JobFiltersParams
  onChange: (filters: JobFiltersParams) => void
}

const CONTRACT_OPTIONS = [
  { value: '', label: 'Todos os contratos' },
  { value: 'clt', label: 'CLT' },
  { value: 'pj', label: 'PJ' },
  { value: 'freelance', label: 'Freelance' },
  { value: 'internship', label: 'Estágio' },
]

const MODALITY_OPTIONS = [
  { value: '', label: 'Todas as modalidades' },
  { value: 'on_site', label: 'Presencial' },
  { value: 'remote', label: 'Remoto' },
  { value: 'hybrid', label: 'Híbrido' },
]

/**
 * Filter bar for the jobs listing page.
 * Requirements: 5.2
 */
export default function JobFilters({ filters, onChange }: JobFiltersProps) {
  function handleChange(key: keyof JobFiltersParams, value: string) {
    onChange({ ...filters, [key]: value || undefined, page: 1 })
  }

  const selectClass =
    'rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20'

  return (
    <div className="flex flex-wrap items-center gap-3">
      {/* Contract type */}
      <select
        value={filters.contract_type ?? ''}
        onChange={(e) => handleChange('contract_type', e.target.value)}
        className={selectClass}
        aria-label="Tipo de contrato"
      >
        {CONTRACT_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>

      {/* Modality */}
      <select
        value={filters.modality ?? ''}
        onChange={(e) => handleChange('modality', e.target.value)}
        className={selectClass}
        aria-label="Modalidade"
      >
        {MODALITY_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>

      {/* City */}
      <input
        type="text"
        value={filters.city ?? ''}
        onChange={(e) => handleChange('city', e.target.value)}
        placeholder="Cidade"
        className={`${selectClass} w-36 placeholder:text-gray-400`}
        aria-label="Cidade"
      />

      {/* State */}
      <input
        type="text"
        value={filters.state ?? ''}
        onChange={(e) => handleChange('state', e.target.value)}
        placeholder="Estado"
        maxLength={2}
        className={`${selectClass} w-24 uppercase placeholder:text-gray-400`}
        aria-label="Estado (sigla)"
      />

      {/* Clear filters */}
      {(filters.contract_type || filters.modality || filters.city || filters.state) && (
        <button
          onClick={() => onChange({ page: 1 })}
          className="text-sm text-gray-400 underline hover:text-gray-600"
          type="button"
        >
          Limpar filtros
        </button>
      )}
    </div>
  )
}
