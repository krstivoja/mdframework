import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { Card, SegmentedControl } from '../../components/ui/index.js';
import { ROUTES, META_KEYS, HELPERS, GLOBALS } from '../../lib/themeReference.js';

// Theme-author cheat sheet — what you can call from a template, what each
// route hands you, and what custom fields the operator has defined for
// this install. Static schemas come from src/lib/themeReference.js; custom
// fields come from /settings so they reflect what's configured right now.
//
// Engine toggle (Twig | PHP) swaps the example column without re-querying.
export default function ThemeReference() {
  const [engine, setEngine] = useState(() =>
    typeof window !== 'undefined'
      ? (window.localStorage.getItem('md.themeReference.engine') || 'twig')
      : 'twig'
  );

  const { data, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });
  const taxonomies = data?.settings?.taxonomies || {};
  const customFields = useMemo(() => collectCustomFields(taxonomies), [taxonomies]);

  function pickEngine(v) {
    setEngine(v);
    try { window.localStorage.setItem('md.themeReference.engine', v); } catch { /* ignore */ }
  }

  return (
    <div className="space-y-4">
      <Card>
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold">Theme reference</h2>
            <p className="mt-1 text-sm text-zinc-600">
              What a theme template can reach for. Pair with the <strong>Debug ({engine === 'twig' ? 'Twig' : 'PHP'})</strong> starter under <em>Themes</em> to see live values per route.
            </p>
          </div>
          <SegmentedControl
            ariaLabel="Template engine"
            value={engine}
            onChange={pickEngine}
            options={[
              { value: 'twig', label: 'Twig' },
              { value: 'php',  label: 'PHP' },
            ]}
          />
        </header>
      </Card>

      <Section title="Route templates" subtitle="Each URL pattern maps to one of these templates. Variables listed here are passed into the template scope.">
        {ROUTES.map((r) => (
          <div key={r.type} className="rounded-md border border-zinc-200 bg-white p-4">
            <div className="flex items-baseline justify-between gap-3">
              <code className="font-mono text-[13px] font-semibold text-zinc-900">{r.templates[engine]}</code>
              <span className="font-mono text-[11px] text-zinc-500">{r.match}</span>
            </div>
            <VarTable rows={r.vars} engine={engine} />
          </div>
        ))}
      </Section>

      <Section title="Standard meta keys" subtitle="Recognised front-matter keys. Anything else is passed through to your template untouched.">
        <VarTable rows={META_KEYS} engine={engine} dense />
      </Section>

      <Section title="Your custom fields" subtitle={isLoading ? 'Loading…' : `Defined under Settings → Manage fields. Each field lives on the page's front matter at the key shown.`}>
        {!isLoading && customFields.length === 0 && (
          <p className="rounded-md border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-500">
            No custom fields yet. Add one in <em>Manage fields</em>.
          </p>
        )}
        {!isLoading && customFields.length > 0 && (
          <CustomFieldsTable rows={customFields} engine={engine} />
        )}
      </Section>

      <Section title="Helpers" subtitle="Callable from any template, both PHP (global function) and Twig (registered function).">
        <SigTable rows={HELPERS} engine={engine} />
      </Section>

      <Section title="Globals" subtitle="Available at template scope without arguments.">
        <SigTable rows={GLOBALS} engine={engine} />
      </Section>
    </div>
  );
}

function Section({ title, subtitle, children }) {
  return (
    <Card>
      <header className="mb-3 space-y-1">
        <h3 className="text-sm font-semibold text-zinc-900">{title}</h3>
        {subtitle && <p className="text-[13px] text-zinc-600">{subtitle}</p>}
      </header>
      <div className="space-y-3">{children}</div>
    </Card>
  );
}

function VarTable({ rows, engine, dense }) {
  return (
    <div className="mt-3 overflow-hidden rounded-md border border-zinc-100 bg-zinc-50">
      <table className="w-full text-[13px]">
        <tbody>
          {rows.map((r) => (
            <tr key={r.name} className="border-b border-zinc-100 last:border-b-0">
              <td className={`${dense ? 'py-1.5' : 'py-2'} px-3 align-top font-mono text-[12px] font-semibold text-zinc-900`} style={{ width: '18%' }}>{r.name}</td>
              <td className={`${dense ? 'py-1.5' : 'py-2'} px-3 align-top font-mono text-[11px] uppercase text-zinc-500`} style={{ width: '10%' }}>{r.type}</td>
              <td className={`${dense ? 'py-1.5' : 'py-2'} px-3 align-top`} style={{ width: '34%' }}>
                <code className="block whitespace-pre-wrap break-words font-mono text-[12px] text-emerald-800">{r[engine]}</code>
              </td>
              <td className={`${dense ? 'py-1.5' : 'py-2'} px-3 align-top text-zinc-700`}>{r.desc}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function SigTable({ rows, engine }) {
  return (
    <div className="overflow-hidden rounded-md border border-zinc-100 bg-zinc-50">
      <table className="w-full text-[13px]">
        <tbody>
          {rows.map((r) => (
            <tr key={r.name} className="border-b border-zinc-100 last:border-b-0">
              <td className="px-3 py-2 align-top font-mono text-[12px] font-semibold text-zinc-900" style={{ width: '14%' }}>{r.name}</td>
              <td className="px-3 py-2 align-top font-mono text-[11px] uppercase text-zinc-500" style={{ width: '14%' }}>→ {r.returns}</td>
              <td className="px-3 py-2 align-top" style={{ width: '38%' }}>
                <code className="block whitespace-pre-wrap break-words font-mono text-[12px] text-emerald-800">{r[engine]}</code>
              </td>
              <td className="px-3 py-2 align-top text-zinc-700">{r.desc}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function CustomFieldsTable({ rows, engine }) {
  return (
    <div className="overflow-hidden rounded-md border border-zinc-200 bg-white">
      <table className="w-full text-[13px]">
        <thead>
          <tr className="border-b border-zinc-100 bg-zinc-50 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500">
            <th className="px-3 py-2">Key</th>
            <th className="px-3 py-2">Type</th>
            <th className="px-3 py-2">Example</th>
            <th className="px-3 py-2">Applies to</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((f) => (
            <tr key={f.taxonomy + '.' + f.name} className="border-b border-zinc-100 last:border-b-0">
              <td className="px-3 py-2 align-top font-mono text-[12px]">meta.{f.name}</td>
              <td className="px-3 py-2 align-top font-mono text-[12px] text-zinc-600">{f.typeLabel}</td>
              <td className="px-3 py-2 align-top">
                <code className="block whitespace-pre-wrap break-words font-mono text-[12px] text-emerald-800">{exampleFor(f, engine)}</code>
              </td>
              <td className="px-3 py-2 align-top text-zinc-600">
                {f.postTypes.length
                  ? f.postTypes.map((t) => <code key={t} className="mr-1 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px]">{t}</code>)
                  : <span className="text-zinc-400">any</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function exampleFor(field, engine) {
  const k = field.name;
  if (field.array) {
    return engine === 'twig'
      ? `{% for v in meta.${k} %}{{ v }}{% endfor %}`
      : `<?php foreach (($meta["${k}"] ?? []) as $v): ?><?= e($v) ?><?php endforeach; ?>`;
  }
  return engine === 'twig'
    ? `{{ meta.${k} }}`
    : `<?= e($meta["${k}"] ?? "") ?>`;
}

function collectCustomFields(taxonomies) {
  const out = [];
  for (const [slug, tax] of Object.entries(taxonomies || {})) {
    for (const f of tax.fields || []) {
      if (!f?.name || f.hidden) continue;
      out.push({
        taxonomy:  slug,
        name:      f.name,
        array:     f.type === 'array',
        typeLabel: typeLabel(f),
        postTypes: tax.post_types || [],
      });
    }
  }
  return out;
}

function typeLabel(f) {
  if (f.type === 'array') {
    const items = (f.items || []).length;
    if (f.widget === 'checkbox' || f.widget === 'radio') return `array · ${items} options`;
    return f.multiple ? `array<string>` : `string`;
  }
  return 'string';
}
