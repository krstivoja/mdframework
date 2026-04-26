import { useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { cap, encodePath } from '../lib/utils.js';
import { Badge, Button, Input, Select } from '../components/ui/index.js';
import { IconSearch } from '../components/icons.jsx';

// Mirrors dsystem ui_kit `PagesList.jsx` — card-wrapped, header with count
// pill + filter toolbar, inline Draft badge, Edit + Delete row actions.
// Mounted both at `/` (All Content) and at `/:folder` (per-folder list).
export default function PagesList() {
  const { folder = '' } = useParams();
  const qc = useQueryClient();
  const navigate = useNavigate();

  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const { data, isLoading, error } = useQuery({
    queryKey: ['pages'],
    queryFn: () => api.get('/pages'),
  });

  const del = useMutation({
    mutationFn: (path) => api.delete(`/pages/${encodePath(path)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pages'] }),
  });

  const filtered = useMemo(() => {
    let list = data?.pages || [];
    if (folder)       list = list.filter(p => (p.folder || '') === folder);
    if (statusFilter) list = list.filter(p => (statusFilter === 'draft') === !!p.draft);
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter(p =>
        (p.title || '').toLowerCase().includes(q) ||
        (p.path  || '').toLowerCase().includes(q)
      );
    }
    return list;
  }, [data, folder, statusFilter, query]);

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;
  if (error) return <div className="text-sm text-red-600">Failed to load: {error.message}</div>;

  const title = folder ? cap(folder) : 'All Content';

  return (
    <div className="rounded-lg border border-zinc-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-center gap-3 border-b border-zinc-100 px-6 py-5">
        <h1 className="flex items-center gap-2 text-[20px] font-semibold tracking-tight">
          {title}
          <span className="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-[11px] font-semibold text-zinc-600">
            {filtered.length}
          </span>
        </h1>

        <div className="ml-auto flex flex-wrap items-center gap-2">
          <div className="relative">
            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400">
              {IconSearch}
            </span>
            <Input
              className="w-56 pl-9"
              placeholder="Search…"
              value={query}
              onChange={e => setQuery(e.target.value)}
            />
          </div>
          <Select
            className="w-36"
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
          >
            <option value="">All statuses</option>
            <option value="live">Live</option>
            <option value="draft">Draft</option>
          </Select>
          {folder && (
            <Button onClick={() => navigate(`/new/${encodeURIComponent(folder)}`)}>
              New page
            </Button>
          )}
        </div>
      </header>

      <table className="w-full text-[13px]">
        <thead>
          <tr className="border-b border-zinc-100 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500">
            <th className="px-6 py-3">Title</th>
            <th className="px-6 py-3">Path</th>
            {folder ? (
              <th className="px-6 py-3">Status</th>
            ) : (
              <th className="px-6 py-3">Type</th>
            )}
            <th className="w-40 px-6 py-3"></th>
          </tr>
        </thead>
        <tbody>
          {filtered.length === 0 && (
            <tr>
              <td colSpan={4} className="px-6 py-10 text-center text-zinc-500">
                No pages match.
              </td>
            </tr>
          )}
          {filtered.map(p => (
            <tr key={p.path} className="border-b border-zinc-100 last:border-b-0 hover:bg-zinc-50">
              <td className="px-6 py-4">
                <Link to={`/${p.path}`} className="font-semibold text-zinc-900 hover:underline">
                  {p.title || '(untitled)'}
                </Link>
              </td>
              <td className="px-6 py-4 font-mono text-[12px] text-zinc-500">{p.path}</td>
              {folder ? (
                <td className="px-6 py-4">
                  <Badge tone={p.draft ? 'draft' : 'live'}>{p.draft ? 'Draft' : 'Live'}</Badge>
                </td>
              ) : (
                <td className="px-6 py-4 text-zinc-500">{p.folder || '—'}</td>
              )}
              <td className="px-6 py-4">
                <div className="flex justify-end gap-2">
                  <Button variant="secondary" size="sm" onClick={() => navigate(`/${p.path}`)}>
                    Edit
                  </Button>
                  <Button
                    variant="danger"
                    size="sm"
                    onClick={() => {
                      if (confirm(`Delete "${p.title || p.path}"?`)) del.mutate(p.path);
                    }}
                  >
                    Delete
                  </Button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
