// Small shared presentational pieces.

const BADGE_COLORS = {
  active: 'bg-green-100 text-green-700',
  trial: 'bg-blue-100 text-blue-700',
  suspended: 'bg-red-100 text-red-700',
  churned: 'bg-gray-100 text-gray-600',
}

export function Badge({ value }) {
  const color = BADGE_COLORS[value] || 'bg-gray-100 text-gray-600'
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${color}`}
    >
      <span className="h-1.5 w-1.5 rounded-full bg-current" />
      {value}
    </span>
  )
}

export function Toggle({ checked, onChange }) {
  return (
    <button
      type="button"
      onClick={onChange}
      className={`relative h-6 w-11 flex-shrink-0 rounded-full transition ${checked ? 'bg-green-500' : 'bg-gray-300'}`}
    >
      <span
        className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${checked ? 'left-[22px]' : 'left-0.5'}`}
      />
    </button>
  )
}

export const inputClass =
  'w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15'
