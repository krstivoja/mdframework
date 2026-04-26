import { useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getCsrf } from '../lib/api.js';
import { formatBytes } from '../lib/utils.js';
import { Alert, Button, Card, Input } from '../components/ui/index.js';

export default function Backup() {
  const qc = useQueryClient();
  const fileRef = useRef(null);
  const [confirmText, setConfirmText] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState(null);

  const { data, isLoading } = useQuery({
    queryKey: ['backup'],
    queryFn: () => api.get('/backup'),
  });

  async function download(scope) {
    setBusy(true);
    setMsg(null);
    try {
      const res = await fetch('/admin/api/backup/download', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() },
        body: JSON.stringify({ scope }),
      });
      if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      const cd = res.headers.get('Content-Disposition') || '';
      const m = /filename="([^"]+)"/.exec(cd);
      a.download = m ? m[1] : `mdframework-${scope}-${new Date().toISOString().slice(0, 10)}.zip`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      setMsg({ tone: 'error', text: e.message });
    } finally {
      setBusy(false);
    }
  }

  async function restore(e) {
    e.preventDefault();
    if (confirmText !== 'RESTORE') {
      setMsg({ tone: 'error', text: 'Type RESTORE to confirm.' });
      return;
    }
    const f = fileRef.current?.files?.[0];
    if (!f) { setMsg({ tone: 'error', text: 'Choose a backup file.' }); return; }

    setBusy(true);
    setMsg(null);
    try {
      const fd = new FormData();
      fd.append('backup', f);
      const res = await fetch('/admin/api/backup/restore', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrf() },
        body: fd,
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Restore failed');
      setMsg({ tone: 'success', text: 'Restored successfully.' });
      qc.invalidateQueries();
    } catch (e) {
      setMsg({ tone: 'error', text: e.message });
    } finally {
      setBusy(false);
      setConfirmText('');
      if (fileRef.current) fileRef.current.value = '';
    }
  }

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-semibold">Backup</h1>

      {msg && <Alert tone={msg.tone}>{msg.text}</Alert>}

      <Card title="Download">
        <div className="grid gap-3 md:grid-cols-3">
          {['full', 'content', 'settings'].map(scope => (
            <div key={scope} className="rounded-md border border-zinc-200 p-3">
              <div className="font-medium capitalize">{scope}</div>
              <div className="mt-1 text-xs text-zinc-500">{formatBytes(data?.sizes?.[scope] ?? 0)}</div>
              <div className="mt-2">
                <Button size="sm" onClick={() => download(scope)} disabled={busy}>
                  Download .zip
                </Button>
              </div>
            </div>
          ))}
        </div>
      </Card>

      <Card title="Restore">
        <p className="text-xs text-zinc-500">Restoring overwrites content and settings. Type RESTORE to confirm.</p>
        <form onSubmit={restore} className="space-y-3">
          <input ref={fileRef} type="file" accept=".zip" className="block text-sm" />
          <Input
            className="w-48"
            value={confirmText}
            onChange={e => setConfirmText(e.target.value)}
            placeholder="Type RESTORE"
          />
          <Button type="submit" variant="danger" disabled={busy}>
            {busy ? 'Working…' : 'Restore'}
          </Button>
        </form>
      </Card>
    </div>
  );
}
