import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { Alert, Badge, Button, Card } from '../../components/ui/index.js';

export default function Themes() {
  const qc = useQueryClient();
  const [error, setError] = useState(null);
  const { data, isLoading } = useQuery({
    queryKey: ['themes'],
    queryFn: () => api.get('/themes'),
  });

  const activate = useMutation({
    mutationFn: (slug) => api.post('/themes/activate', { slug }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['themes'] }),
    onError: (e) => setError(e.message),
  });

  const install = useMutation({
    mutationFn: ({ starter, theme_slug }) => api.post('/themes/install', { starter, theme_slug }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['themes'] }),
    onError: (e) => setError(e.message),
  });

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-6">
      <h2 className="text-base font-semibold">Themes</h2>

      {error && <Alert tone="error">{error}</Alert>}

      <Card title="Installed">
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(data?.themes || []).map(t => (
            <div key={t.slug} className="rounded-md border border-zinc-200 p-3">
              <div className="flex items-center justify-between">
                <div className="font-medium">{t.name || t.slug}</div>
                {data.active === t.slug ? (
                  <Badge tone="active">Active</Badge>
                ) : (
                  <Button variant="link" size="sm" onClick={() => activate.mutate(t.slug)}>
                    Activate
                  </Button>
                )}
              </div>
              {t.description && <p className="mt-1 text-xs text-zinc-500">{t.description}</p>}
            </div>
          ))}
        </div>
      </Card>

      <Card title="Starters">
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(data?.starters || []).map(s => (
            <div key={s.slug} className="rounded-md border border-zinc-200 p-3">
              <div className="font-medium">{s.name || s.slug}</div>
              {s.description && <p className="mt-1 text-xs text-zinc-500">{s.description}</p>}
              <div className="mt-2">
                <Button
                  variant="link"
                  size="sm"
                  onClick={() => install.mutate({ starter: s.slug, theme_slug: s.slug })}
                >
                  Install
                </Button>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
