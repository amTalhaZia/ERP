/**
 * Update the query string without a Next.js navigation. `router.replace()` for a
 * search-param-only change still round-trips to the server for an RSC payload
 * and holds the old UI until it arrives (seconds on a busy dev server, and the
 * click looks ignored). The native History API is integrated with the App
 * Router: `useSearchParams()` re-renders immediately and nothing is fetched.
 */
export function replaceSearchParams(pathname: string, params: URLSearchParams | string): void {
  const qs = params.toString();
  window.history.replaceState(window.history.state, '', qs ? `${pathname}?${qs}` : pathname);
}
