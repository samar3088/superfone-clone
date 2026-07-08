export default function ComingSoon({ title, icon = '🚧', note }) {
  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="text-3xl font-bold">{title}</h1>
      <div className="mt-8 grid place-items-center rounded-2xl border border-dashed border-gray-200 bg-white py-24 text-center">
        <div>
          <div className="text-5xl">{icon}</div>
          <div className="mt-4 text-lg font-bold text-gray-600">Coming soon</div>
          <p className="mx-auto mt-1 max-w-md text-sm text-gray-400">
            {note || 'This module is on the roadmap — tell us which screen to build next and share a screenshot to match.'}
          </p>
        </div>
      </div>
    </div>
  )
}
