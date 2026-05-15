import { useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api, getCsrf } from '../lib/api.js';
import { useConfirmDialog } from '../lib/hooks.js';
import { cap, encodePath } from '../lib/utils.js';
import { Button, ConfirmDialog, Input, Select } from '../components/ui/index.js';
import { IconSearch } from '../components/icons.jsx';
import PageRow from '../components/PageRow.jsx';

// Mirrors dsystem ui_kit `PagesList.jsx` — card-wrapped, header with count
// pill + filter toolbar, inline Draft badge, Edit + Delete row actions.
// Mounted both at `/` (All Content) and at `/:folder` (per-folder list).
export default function PagesList() {
  const { folder = '' } = useParams();
  const qc = useQueryClient();
  const navigate = useNavigate();

  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  // Bulk selection — tracks page paths the user has ticked. Cleared on
  // filter changes so what's "selected" always matches what's visible.
  const [selected, setSelected] = useState(() => new Set());
  const [importMsg, setImportMsg] = useState(null);
  const importInputRef = useRef(null);
  const { confirm, dialogProps } = useConfirmDialog();

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

  // Drop selections that aren't visible after a filter change so the bulk
  // toolbar count never lies about what "Delete selected" will affect.
  const visiblePaths = useMemo(() => new Set(filtered.map((p) => p.path)), [filtered]);
  const visibleSelected = useMemo(
    () => Array.from(selected).filter((p) => visiblePaths.has(p)),
    [selected, visiblePaths],
  );
  const allVisibleSelected = filtered.length > 0 && visibleSelected.length === filtered.length;

  function toggleOne(path, checked) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (checked) next.add(path); else next.delete(path);
      return next;
    });
  }
  function toggleAll(checked) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (checked) filtered.forEach((p) => next.add(p.path));
      else filtered.forEach((p) => next.delete(p.path));
      return next;
    });
  }

  function exportPages() {
    // GET endpoint — auth is the session cookie which the browser sends
    // with a top-level navigation, so a plain href download works.
    const q = folder ? `?folder=${encodeURIComponent(folder)}` : '';
    window.location.href = `/admin/api/pages-export${q}`;
  }

  const importMut = useMutation({
    mutationFn: async (files) => {
      const fd = new FormData();
      if (folder) fd.append('folder', folder);
      for (const f of files) fd.append('files[]', f);
      const res = await fetch('/admin/api/pages-import', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrf() },
        body: fd,
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Import failed');
      return data;
    },
    onSuccess: (data) => {
      const n = data.imported?.length || 0;
      const errs = data.errors?.length || 0;
      setImportMsg(
        errs
          ? `Imported ${n}; ${errs} skipped. ${data.errors.slice(0, 3).join(' ')}`
          : `Imported ${n} ${n === 1 ? 'page' : 'pages'}.`,
      );
      qc.invalidateQueries({ queryKey: ['pages'] });
    },
    onError: (err) => setImportMsg(`Import failed: ${err.message}`),
  });

  function onImportFiles(fileList) {
    const files = Array.from(fileList || []).filter(Boolean);
    if (files.length === 0) return;
    setImportMsg(null);
    importMut.mutate(files);
  }

  async function bulkDelete() {
    if (visibleSelected.length === 0) return;
    const ok = await confirm({
      title: `Delete ${visibleSelected.length} pages`,
      message: `Delete ${visibleSelected.length} selected ${visibleSelected.length === 1 ? 'page' : 'pages'}? This cannot be undone.`,
    });
    if (!ok) return;
    // Run sequentially so a failure doesn't bury earlier successes; the
    // mutation cache invalidates only when everything's done.
    for (const path of visibleSelected) {
      try { await api.delete(`/pages/${encodePath(path)}`); } catch { /* keep going */ }
    }
    setSelected(new Set());
    qc.invalidateQueries({ queryKey: ['pages'] });
  }

  if (isLoading) {
    return (
      <div className="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm" aria-hidden="true">
        <div className="h-6 w-40 animate-pulse rounded bg-zinc-200" />
        <div className="mt-6 space-y-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-10 w-full animate-pulse rounded bg-zinc-100" />
          ))}
        </div>
      </div>
    );
  }
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

        <div className="ml-auto flex flex-nowrap items-center gap-2">
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
          <Button variant="secondary" onClick={exportPages}>
            {folder ? `Download ${folder}` : 'Download all'}
          </Button>
          <Button
            variant="secondary"
            onClick={() => importInputRef.current?.click()}
            disabled={importMut.isPending}
            aria-busy={importMut.isPending}
          >
            {importMut.isPending ? 'Importing…' : 'Import'}
          </Button>
          <input
            ref={importInputRef}
            type="file"
            accept=".md,.zip,application/zip,text/markdown"
            multiple
            hidden
            onChange={(e) => { onImportFiles(e.target.files); e.target.value = ''; }}
          />
          {folder && (
            <Button onClick={() => navigate(`/new/${encodeURIComponent(folder)}`)}>
              New page
            </Button>
          )}
        </div>
      </header>

      {importMsg && (
        <div
          role="status"
          className="border-b border-zinc-100 bg-zinc-50 px-6 py-2.5 text-[13px] text-zinc-700"
        >
          {importMsg}
        </div>
      )}

      {visibleSelected.length > 0 && (
        <div className="flex items-center justify-between gap-3 border-b border-zinc-100 bg-zinc-50 px-6 py-2 text-[12px]">
          <span className="font-medium text-zinc-700">
            {visibleSelected.length} selected
          </span>
          <div className="flex gap-2">
            <Button variant="secondary" size="sm" onClick={() => setSelected(new Set())}>Clear</Button>
            <Button variant="danger" size="sm" onClick={bulkDelete}>Delete selected</Button>
          </div>
        </div>
      )}

      <table className="w-full text-[13px]">
        <thead>
          <tr className="border-b border-zinc-100 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500">
            <th className="w-10 px-6 py-3">
              <input
                type="checkbox"
                aria-label="Select all"
                checked={allVisibleSelected}
                ref={(el) => { if (el) el.indeterminate = visibleSelected.length > 0 && !allVisibleSelected; }}
                onChange={(e) => toggleAll(e.target.checked)}
                className="h-4 w-4 cursor-pointer rounded border-zinc-300"
              />
            </th>
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
              <td colSpan={5} className="px-6 py-12 text-center">
                <div className="mx-auto max-w-sm space-y-3 text-zinc-500">
                  <div className="text-sm font-semibold text-zinc-700">
                    {query || statusFilter ? 'No pages match your filter' : 'No pages yet'}
                  </div>
                  <div className="text-xs">
                    {query || statusFilter
                      ? 'Try clearing the search or status filter.'
                      : folder
                        ? `Create your first page under ${folder} to get started.`
                        : 'Pick a folder from the sidebar and add a page.'}
                  </div>
                  {folder && !query && !statusFilter && (
                    <Button onClick={() => navigate(`/new/${encodeURIComponent(folder)}`)}>
                      New page
                    </Button>
                  )}
                </div>
              </td>
            </tr>
          )}
          {filtered.map(p => (
            <PageRow
              key={p.path}
              page={p}
              showStatus={!!folder}
              selected={selected.has(p.path)}
              onToggle={toggleOne}
              onEdit={navigate}
              onDelete={async (page) => {
                const ok = await confirm({
                  title: 'Delete page',
                  message: `Delete "${page.title || page.path}"? This cannot be undone.`,
                });
                if (ok) del.mutate(page.path);
              }}
            />
          ))}
        </tbody>
      </table>
      <ConfirmDialog {...dialogProps} />
    </div>
  );
}
