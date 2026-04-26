import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { Alert, Button, Card, Field, Input } from '../../components/ui/index.js';

export default function SiteSettings() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });

  const [site, setSite] = useState({ name: '', base: '/' });
  const [uploads, setUploads] = useState({ max_mb: 5, max_width: 0, max_height: 0 });
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const s = data?.settings;
    if (!s) return;
    setSite({ name: s.site?.name || '', base: s.site?.base || '/' });
    setUploads({
      max_mb: s.uploads?.max_mb ?? 5,
      max_width: s.uploads?.max_width ?? 0,
      max_height: s.uploads?.max_height ?? 0,
    });
  }, [data]);

  const save = useMutation({
    mutationFn: () => api.put('/settings', {
      site,
      uploads,
      taxonomies: data?.settings?.taxonomies || {},
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['settings'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold">Site settings</h2>
        <div className="flex items-center gap-3">
          {saved && <span className="text-xs text-emerald-600">Saved</span>}
          <Button onClick={() => save.mutate()} disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </div>
      </div>

      {save.error && <Alert tone="error">{save.error.message}</Alert>}

      <Card title="Site">
        <Field label="Site name">
          <Input value={site.name} onChange={e => setSite({ ...site, name: e.target.value })} />
        </Field>
        <Field label="Base path">
          <Input value={site.base} onChange={e => setSite({ ...site, base: e.target.value })} />
        </Field>
      </Card>

      <Card title="Uploads">
        <Field label="Max size (MB)">
          <Input type="number" min="1" max="512"
            value={uploads.max_mb} onChange={e => setUploads({ ...uploads, max_mb: +e.target.value })} />
        </Field>
        <Field label="Max width (px, 0 = no limit)">
          <Input type="number" min="0" max="20000"
            value={uploads.max_width} onChange={e => setUploads({ ...uploads, max_width: +e.target.value })} />
        </Field>
        <Field label="Max height (px, 0 = no limit)">
          <Input type="number" min="0" max="20000"
            value={uploads.max_height} onChange={e => setUploads({ ...uploads, max_height: +e.target.value })} />
        </Field>
      </Card>
    </div>
  );
}
