import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import suneditor from 'suneditor';
// Pass only the plugins our toolbar actually uses. The default `plugins`
// bundle registers fileUpload / layout / template / math / etc., each of which
// emits a console warning when its required option isn't provided.
import { list_bulleted, list_numbered, hr, table, link, image } from 'suneditor/plugins';
import 'suneditor/css/editor';

const plugins = { list_bulleted, list_numbered, hr, table, link, image };
import TurndownService from 'turndown';
import { api, getCsrf } from '../lib/api.js';
import { encodePath, publicUrl, slugify } from '../lib/utils.js';
import { useDirty } from '../lib/dirty.jsx';
import { Alert, Button, Card, Field, Input, Select } from '../components/ui/index.js';
import PageFields from '../components/PageFields.jsx';

const turndown = makeTurndown();

export default function PageEditor() {
  const params = useParams();
  const folder = params.folder || '';
  const slugPath = params.slug || '';
  const isNew = slugPath === '';
  const path = isNew ? '' : `${folder}/${slugPath}`;
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { setDirty } = useDirty();

  const { data, isLoading, error } = useQuery({
    queryKey: ['page', path],
    queryFn: () => api.get(`/pages/${encodePath(path)}`),
    enabled: !isNew,
  });

  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [slugTouched, setSlugTouched] = useState(false);
  const [status, setStatus] = useState('published');
  const [taxValues, setTaxValues] = useState({});

  const editorRef = useRef(null);
  const sunRef = useRef(null);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (isNew) {
      setTitle('');
      setSlug('');
      setSlugTouched(false);
      setStatus('published');
      setTaxValues({});
    } else if (data) {
      const rest = (data.path || '').split('/').slice(1).join('/');
      setTitle(data.meta?.title || '');
      setSlug(rest);
      setSlugTouched(true);
      setStatus(data.meta?.draft ? 'draft' : 'published');
      setTaxValues(data.meta || {});
    }
    setDirty(false);
  }, [isNew, data, setDirty]);

  useEffect(() => {
    if (!isNew || slugTouched) return;
    setSlug(slugify(title));
  }, [isNew, slugTouched, title]);

  useEffect(() => {
    if (!editorRef.current) return;
    if (initializedRef.current) return;
    if (!isNew && !data) return;

    const pagePathForUpload = path;
    const ed = suneditor.create(editorRef.current, {
      plugins,
      width: '100%',
      height: '520px',
      buttonList: [
        ['undo', 'redo'],
        ['bold', 'italic', 'underline', 'strike'],
        ['removeFormat'],
        ['list_bulleted', 'list_numbered', 'hr'],
        ['link', 'image', 'table'],
        ['codeView', 'markdownView', 'fullScreen'],
      ],
      imageUploadHandler(_xhr, info, core) {
        const fd = new FormData();
        fd.append('file', info.file);
        if (pagePathForUpload) fd.append('page_path', pagePathForUpload);
        fetch('/admin/api/media', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': getCsrf() },
          body: fd,
        })
          .then(r => r.json())
          .then(data => {
            if (data?.ok && data.url) {
              core.plugins.image.register.call(core, info, { url: data.url });
            }
          })
          .catch(() => { /* ignore */ });
        return false;
      },
    });
    ed.onChange = () => setDirty(true);
    sunRef.current = ed;
    initializedRef.current = true;

    if (!isNew && data?.html) {
      ed.$.html.insert(data.html, false);
    }
    return () => {
      try { ed.destroy?.(); } catch { /* ignore */ }
      sunRef.current = null;
      initializedRef.current = false;
    };
  }, [isNew, data, path, setDirty]);

  const markDirty = (setter) => (value) => {
    setDirty(true);
    setter(value);
  };

  const del = useMutation({
    mutationFn: () => api.delete(`/pages/${encodePath(path)}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      setDirty(false);
      navigate(`/${encodeURIComponent(folder)}`, { replace: true });
    },
  });

  const save = useMutation({
    mutationFn: async () => {
      const html = sunRef.current?.$.html.get() || '';
      const body = turndown.turndown(html);
      const relPath = [folder, slug].filter(Boolean).join('/');
      const payload = { title, body, status, taxonomies: taxValues };
      if (isNew) {
        payload.path = relPath;
        return api.post('/pages', payload);
      }
      return api.put(`/pages/${encodePath(path)}`, payload);
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      qc.invalidateQueries({ queryKey: ['page', res.path] });
      setDirty(false);
      if (isNew) {
        const rest = (res.path || '').split('/').slice(1).join('/');
        navigate(`/${encodeURIComponent(folder)}/${encodePath(rest)}`, { replace: true });
      }
    },
  });

  if (!isNew && isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;
  if (!isNew && error) return <div className="text-sm text-red-600">Failed to load: {error.message}</div>;

  return (
    <div className="flex min-w-0 flex-1">
      <section className="min-w-0 flex-1 space-y-4 overflow-y-auto p-8">
        <Input
          value={title}
          onChange={e => markDirty(setTitle)(e.target.value)}
          placeholder="Page title"
          className="!h-12 !text-lg !font-semibold"
        />

        {save.error && <Alert tone="error">{save.error.message}</Alert>}

        <div className="rounded-lg border border-zinc-200 bg-white">
          <textarea ref={editorRef} defaultValue="" />
        </div>
      </section>

      <aside className="flex w-72 shrink-0 flex-col gap-3 overflow-y-auto border-l border-zinc-200 bg-white p-4">
        <Button onClick={() => save.mutate()} disabled={save.isPending}>
          {save.isPending ? 'Saving…' : 'Save'}
        </Button>

        {!isNew && (
          <a
            href={publicUrl(path)}
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-zinc-100"
          >
            Preview
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M9.5 2.5h4v4" />
              <path d="M13.5 2.5L7 9" />
              <path d="M12 9v3.5a1 1 0 0 1-1 1H3.5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1H7" />
            </svg>
          </a>
        )}

        <Card>
          <Field label="Slug">
            {isNew ? (
              <div className="flex h-9 w-full overflow-hidden rounded-md border border-zinc-200 bg-white transition-colors focus-within:border-zinc-900 focus-within:ring-2 focus-within:ring-zinc-900/15">
                <span className="inline-flex select-none items-center border-r border-zinc-200 bg-zinc-50 px-2 font-mono text-xs text-zinc-500">
                  {folder}/
                </span>
                <input
                  value={slug}
                  onChange={e => {
                    setSlugTouched(true);
                    markDirty(setSlug)(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                  }}
                  placeholder="my-post"
                  className="min-w-0 flex-1 border-0 bg-transparent px-2 font-mono text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0"
                />
              </div>
            ) : (
              <Input mono value={path} readOnly />
            )}
          </Field>

          <Field label="Status">
            <Select
              value={status}
              onChange={e => markDirty(setStatus)(e.target.value)}
            >
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </Select>
          </Field>
        </Card>

        {!isNew && (
          <Button
            variant="danger"
            onClick={() => {
              if (confirm(`Delete "${title || path}"?`)) del.mutate();
            }}
            disabled={del.isPending}
          >
            {del.isPending ? 'Deleting…' : 'Delete'}
          </Button>
        )}

        <PageFields
          folder={folder}
          values={taxValues}
          onChange={(slug, value) => {
            setDirty(true);
            setTaxValues(prev => ({ ...prev, [slug]: value }));
          }}
        />

      </aside>
    </div>
  );
}

function makeTurndown() {
  const td = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });
  td.addRule('html-blocks', {
    filter(node) {
      const blocks = ['DIV', 'SECTION', 'ARTICLE', 'ASIDE', 'FIGURE', 'FIGCAPTION', 'HEADER', 'FOOTER', 'DETAILS', 'SUMMARY'];
      return blocks.includes(node.nodeName) && node.hasAttributes();
    },
    replacement(_content, node) {
      const html = node.outerHTML
        .replace(/\\+_/g, '_')
        .replace(/\\\\/g, '\\');
      return '\n\n' + html + '\n\n';
    },
  });
  return td;
}
