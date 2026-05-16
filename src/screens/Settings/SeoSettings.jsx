import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { Alert, Button, Card, Checkbox, Field, Input } from '../../components/ui/index.js';

// Defaults must match MD\Seo's reads — keep in sync.
const DEFAULTS = {
  enabled:        true,
  opengraph:      true,
  twitter_card:   true,
  json_ld:        true,
  indexable:      true,
  twitter_handle: '',
  default_image:  '',
  locale:         'en_US',
};

export default function SeoSettings() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });

  const [seo, setSeo] = useState(DEFAULTS);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const s = data?.settings?.seo;
    if (!s) return;
    setSeo({ ...DEFAULTS, ...s });
  }, [data]);

  const save = useMutation({
    mutationFn: () => api.put('/settings', {
      site:       data?.settings?.site || {},
      uploads:    data?.settings?.uploads || {},
      taxonomies: data?.settings?.taxonomies || {},
      seo,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['settings'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  function bind(key) {
    return {
      checked: !!seo[key],
      onChange: (e) => setSeo({ ...seo, [key]: e.target.checked }),
    };
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-base font-semibold">SEO &amp; schemas</h2>
          <p className="mt-1 text-sm text-zinc-600">
            Framework-level injection of OpenGraph, Twitter cards, JSON-LD, and robots into <code className="rounded bg-zinc-100 px-1 py-0.5 font-mono text-[12px]">&lt;head&gt;</code>. Themes don't have to opt in — toggle off any block you'd rather control yourself.
          </p>
        </div>
        <div className="flex items-center gap-3">
          {saved && <span className="text-xs text-emerald-600">Saved</span>}
          <Button onClick={() => save.mutate()} disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </div>
      </div>

      {save.error && <Alert tone="error">{save.error.message}</Alert>}

      <Card title="Inject">
        <p className="text-xs text-zinc-500">
          The master toggle disables every injected tag, even if individual blocks are on. Useful while you debug a theme of your own.
        </p>
        <div className="mt-3 space-y-2">
          <Checkbox label="Inject SEO tags into every HTML page (master switch)" {...bind('enabled')} />
          <Checkbox label="OpenGraph (og:title, og:image, og:url, …)"               {...bind('opengraph')} />
          <Checkbox label="Twitter card (twitter:card, twitter:title, …)"           {...bind('twitter_card')} />
          <Checkbox label="JSON-LD (BlogPosting on posts, WebPage elsewhere)"       {...bind('json_ld')} />
        </div>
      </Card>

      <Card title="Indexable">
        <p className="text-xs text-zinc-500">
          When this is off the framework emits <code className="rounded bg-zinc-100 px-1 py-0.5 font-mono text-[12px]">noindex,nofollow</code> for every page — useful for staging. Per-page <code className="rounded bg-zinc-100 px-1 py-0.5 font-mono text-[12px]">noindex: true</code> in front matter overrides this when the site is indexable.
        </p>
        <div className="mt-3">
          <Checkbox label="Allow search engines to index this site" {...bind('indexable')} />
        </div>
      </Card>

      <Card title="Defaults">
        <Field label="Twitter handle" hint="With or without the @ — used as twitter:site on every card.">
          <Input
            value={seo.twitter_handle}
            onChange={(e) => setSeo({ ...seo, twitter_handle: e.target.value })}
            placeholder="@krstivoja"
          />
        </Field>
        <Field label="Default share image" hint="Absolute URL or /relative path. Used when a page has no featured image.">
          <Input
            value={seo.default_image}
            onChange={(e) => setSeo({ ...seo, default_image: e.target.value })}
            placeholder="/uploads/og-default.png"
          />
        </Field>
        <Field label="Locale" hint="Two-part locale for og:locale. Common values: en_US, en_GB, sr_RS.">
          <Input
            value={seo.locale}
            onChange={(e) => setSeo({ ...seo, locale: e.target.value })}
            placeholder="en_US"
          />
        </Field>
      </Card>

      <Card title="Per-page overrides">
        <p className="text-xs text-zinc-500">
          Any page can override these defaults from its front matter:
        </p>
        <ul className="mt-3 list-disc space-y-1 pl-5 text-[13px] text-zinc-700">
          <li><code className="font-mono text-[12px]">seo: false</code> — skip SEO injection on this page entirely</li>
          <li><code className="font-mono text-[12px]">noindex: true</code> — emit robots noindex (drafts are already noindex)</li>
          <li><code className="font-mono text-[12px]">og_image: "/path/to/img.jpg"</code> — override the share image</li>
          <li><code className="font-mono text-[12px]">og_type: "article"</code> — override og:type (auto-detected from the route)</li>
          <li><code className="font-mono text-[12px]">description: "…"</code> — meta description (already standard)</li>
        </ul>
      </Card>
    </div>
  );
}
