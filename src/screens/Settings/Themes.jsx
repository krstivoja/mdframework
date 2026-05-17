import { useState } from 'react';
import { Link } from 'react-router-dom';
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

  const remove = useMutation({
    mutationFn: (slug) => api.post('/themes/delete', { slug }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['themes'] }),
    onError: (e) => setError(e.message),
  });

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-base font-semibold">Themes</h2>
        <Link
          to="/theme-builder"
          className="inline-flex h-8 items-center justify-center rounded-md border border-zinc-200 bg-white px-2.5 text-xs font-medium text-zinc-900 transition-colors hover:bg-zinc-100"
        >
          Open theme builder
        </Link>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      <Card title="Installed">
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(data?.themes || []).map(t => (
            <div key={t.slug} className="rounded-md border border-zinc-200 p-3">
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <div className="font-medium">{t.name || t.slug}</div>
                  <EngineBadge engine={t.engine} />
                </div>
                {data.active === t.slug ? (
                  <Badge tone="active">Active</Badge>
                ) : (
                  <div className="flex items-center gap-3">
                    <Button variant="link" size="sm" onClick={() => activate.mutate(t.slug)}>
                      Activate
                    </Button>
                    <Button
                      variant="link-danger"
                      size="sm"
                      onClick={() => {
                        if (confirm(`Delete the "${t.name || t.slug}" theme? This removes the theme files from disk.`)) {
                          remove.mutate(t.slug);
                        }
                      }}
                    >
                      Delete
                    </Button>
                  </div>
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
              <div className="flex items-center gap-2">
                <div className="font-medium">{s.name || s.slug}</div>
                <EngineBadge engine={s.engine} />
              </div>
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

// Tiny tone-coded badge that surfaces the templating engine declared in
// `theme.json` (or auto-detected by ThemeService::detectEngine). Skipped
// when the engine couldn't be determined — better silence than a misleading
// "unknown" pill on the card.
function EngineBadge({ engine }) {
  if (!engine || engine === 'unknown') return null;
  const tone = engine === 'twig' ? 'success' : engine === 'php' ? 'purple' : 'warning';
  return <Badge tone={tone}>{engine}</Badge>;
}
