import { useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api, getCsrf } from '../lib/api.js';
import { Alert, Button } from '../components/ui/index.js';

export default function Media() {
  const qc = useQueryClient();
  const fileRef = useRef(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const { data, isLoading } = useQuery({
    queryKey: ['media'],
    queryFn: () => api.get('/media'),
  });

  const del = useMutation({
    mutationFn: (name) => api.delete(`/media/${encodeURIComponent(name)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['media'] }),
  });

  async function onPick(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    setBusy(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append('file', f);
      const res = await fetch('/admin/api/media', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrf() },
        body: fd,
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Upload failed');
      qc.invalidateQueries({ queryKey: ['media'] });
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
      e.target.value = '';
    }
  }

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Media library</h1>
        <div>
          <input ref={fileRef} type="file" hidden onChange={onPick} />
          <Button onClick={() => fileRef.current?.click()} disabled={busy}>
            {busy ? 'Uploading…' : 'Upload'}
          </Button>
        </div>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        {(data?.files || []).length === 0 && (
          <div className="col-span-full rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center text-sm text-zinc-500">
            No files yet.
          </div>
        )}
        {(data?.files || []).map(f => (
          <MediaItem key={f.name + (f.url || '')} file={f} onDelete={() => del.mutate(f.name)} />
        ))}
      </div>
    </div>
  );
}

function MediaItem({ file, onDelete }) {
  return (
    <div className="group relative overflow-hidden rounded-lg border border-zinc-200 bg-white">
      {isImage(file) ? (
        <img src={file.thumb_url || file.url} alt={file.alt || file.name} className="aspect-square w-full object-cover" />
      ) : (
        <div className="flex aspect-square w-full items-center justify-center bg-zinc-50 text-xs text-zinc-500">
          {ext(file.name)}
        </div>
      )}
      <div className="border-t border-zinc-100 p-2">
        <div className="truncate text-xs font-medium" title={file.name}>{file.name}</div>
        <div className="mt-1 flex items-center justify-between">
          <a href={file.url} target="_blank" rel="noreferrer" className="text-xs text-zinc-500 hover:underline">
            View
          </a>
          <button
            onClick={() => { if (confirm(`Delete ${file.name}?`)) onDelete(); }}
            className="text-xs text-red-600 hover:underline"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  );
}

function isImage(f) {
  return /\.(jpe?g|png|gif|webp|svg)$/i.test(f.name);
}
function ext(name) {
  const m = /\.([^.]+)$/.exec(name);
  return m ? m[1].toUpperCase() : 'FILE';
}
