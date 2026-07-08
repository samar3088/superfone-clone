import { useParams } from 'react-router-dom'

/**
 * Placeholder for sidebar sections that aren't in the current build scope.
 * Route: /soon/:title
 */
export default function ComingSoon() {
  const { title } = useParams()

  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="text-3xl font-bold">{decodeURIComponent(title || 'Coming soon')}</h1>
      <div className="mt-8 grid place-items-center rounded-2xl border border-dashed border-gray-200 bg-white py-24 text-center">
        <div>
          <div className="text-5xl">🚧</div>
          <div className="mt-4 text-lg font-bold text-gray-600">Coming soon</div>
          <p className="mx-auto mt-1 max-w-md text-sm text-gray-400">
            This module is not part of the current build scope. Share a screen recording of it to have it built next.
          </p>
        </div>
      </div>
    </div>
  )
}
