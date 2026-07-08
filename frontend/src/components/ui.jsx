// Small shared presentational components.
import { Link } from 'react-router-dom'

const BADGE_COLORS = {
  active: 'bg-green-100 text-green-700',
  completed: 'bg-green-100 text-green-700',
  assigned: 'bg-green-100 text-green-700',
  inbound: 'bg-green-100 text-green-700',
  trial: 'bg-blue-100 text-blue-700',
  porting: 'bg-blue-100 text-blue-700',
  voicemail: 'bg-blue-100 text-blue-700',
  outbound: 'bg-blue-100 text-blue-700',
  suspended: 'bg-red-100 text-red-700',
  missed: 'bg-red-100 text-red-700',
  rejected: 'bg-red-100 text-red-700',
  churned: 'bg-gray-100 text-gray-600',
  released: 'bg-gray-100 text-gray-600',
  available: 'bg-gray-100 text-gray-600',
  mobile: 'bg-indigo-100 text-indigo-700',
  landline: 'bg-amber-100 text-amber-700',
  tollfree: 'bg-teal-100 text-teal-700',
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

export function StatCard({ label, value, sub, icon, to }) {
  const body = (
    <>
      <div className="flex items-center justify-between">
        <span className="text-sm font-semibold text-gray-500">{label}</span>
        <span className="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-lg text-brand">
          {icon}
        </span>
      </div>
      <div className="mt-3 text-3xl font-extrabold tracking-tight">{value}</div>
      {sub && <div className="mt-1 text-xs text-gray-400">{sub}</div>}
    </>
  )
  const base = 'block rounded-2xl border border-gray-100 bg-white p-5 shadow-sm'
  if (to) {
    return (
      <Link to={to} className={`${base} transition hover:border-brand/40 hover:shadow-md`}>
        {body}
      </Link>
    )
  }
  return <div className={base}>{body}</div>
}

export function Panel({ title, action, children, padded }) {
  return (
    <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      {(title || action) && (
        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
          <h2 className="text-base font-bold">{title}</h2>
          {action}
        </div>
      )}
      <div className={padded ? 'p-5' : ''}>{children}</div>
    </div>
  )
}

export function Button({ children, variant = 'primary', className = '', ...props }) {
  const styles = {
    primary: 'bg-brand text-white hover:bg-brand-dark',
    light: 'bg-gray-100 text-gray-800 hover:bg-gray-200',
    danger: 'bg-red-50 text-red-600 hover:bg-red-100',
  }
  return (
    <button
      className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-50 ${styles[variant]} ${className}`}
      {...props}
    >
      {children}
    </button>
  )
}

export function Field({ label, children }) {
  return (
    <label className="mb-4 block">
      <span className="mb-1.5 block text-sm font-semibold text-gray-700">{label}</span>
      {children}
    </label>
  )
}

export const inputClass =
  'w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/15'

export function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm text-gray-500">
      <span>
        {meta.from || 0}–{meta.to || 0} of {meta.total}
      </span>
      <div className="flex gap-2">
        <Button
          variant="light"
          className="px-3 py-1.5"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          Prev
        </Button>
        <Button
          variant="light"
          className="px-3 py-1.5"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}

export function Modal({ title, onClose, children }) {
  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-bold">{title}</h3>
          <button onClick={onClose} className="text-2xl leading-none text-gray-400 hover:text-gray-600">
            ×
          </button>
        </div>
        {children}
      </div>
    </div>
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

export function inr(amount) {
  return '₹' + Number(amount || 0).toLocaleString('en-IN')
}

export function duration(sec) {
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${m}:${String(s).padStart(2, '0')}`
}
