// Tiny fetch wrapper for /admin/api/*. Sends session cookie automatically
// (same-origin), attaches CSRF header on mutating requests, and throws on errors.

let csrfToken = '';

export function setCsrf(token) {
  csrfToken = token || '';
}

export function getCsrf() {
  return csrfToken;
}

export class ApiError extends Error {
  constructor(message, status, body) {
    super(message);
    this.status = status;
    this.body = body;
  }
}

async function request(method, path, { body, signal } = {}) {
  const headers = { Accept: 'application/json' };
  const opts = { method, credentials: 'same-origin', headers, signal };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  const res = await fetch(`/admin/api${path}`, opts);
  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { /* non-JSON */ }

  if (!res.ok || (data && data.ok === false)) {
    const msg = (data && data.error) || text || `${res.status} ${res.statusText}`;
    throw new ApiError(msg, res.status, data);
  }
  return data;
}

export const api = {
  get:    (path, opts) => request('GET', path, opts),
  post:   (path, body, opts) => request('POST', path, { ...opts, body }),
  put:    (path, body, opts) => request('PUT', path, { ...opts, body }),
  delete: (path, opts) => request('DELETE', path, opts),
};
