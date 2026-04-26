import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import suneditor from 'suneditor';
import plugins from 'suneditor/plugins';
import 'suneditor/css/editor';
import TurndownService from 'turndown';
import { api, getCsrf } from '../lib/api.js';
import { encodePath } from '../lib/utils.js';
import { Alert, Button, Card, Field, Input, Select } from '../components/ui/index.js';

const turndown = makeTurndown();

export default function PageEditor() {
  const params = useParams();
  const path = params['*'] || '';
  const isNew = path === '';
  const navigate = useNavigate();
  const qc = useQueryClient();

  const { data, isLoading, error } = useQuery({
    queryKey: ['page', path],
    queryFn: () => api.get(`/pages/${encodePath(path)}`),
    enabled: !isNew,
  });

  const [title, setTitle] = useState('');
  const [pathInput, setPathInput] = useState('');
  const [status, setStatus] = useState('published');
  const editorRef = useRef(null);
  const sunRef = useRef(null);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (isNew) {
      setTitle('');
      setPathInput('');
      setStatus('published');
    } else if (data) {
      setTitle(data.meta?.title || '');
      setPathInput(data.path || '');
      setStatus(data.meta?.draft ? 'draft' : 'published');
    }
  }, [isNew, data]);

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
  }, [isNew, data, path]);

  const save = useMutation({
    mutationFn: async () => {
      const html = sunRef.current?.$.html.get() || '';
      const body = turndown.turndown(html);
      const payload = { title, body, status };
      if (isNew) {
        payload.path = pathInput;
        return api.post('/pages', payload);
      }
      return api.put(`/pages/${encodePath(path)}`, payload);
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      qc.invalidateQueries({ queryKey: ['page', res.path] });
      if (isNew) navigate(`/edit/${res.path}`, { replace: true });
    },
  });

  if (!isNew && isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;
  if (!isNew && error) return <div className="text-sm text-red-600">Failed to load: {error.message}</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">{isNew ? 'New page' : 'Edit page'}</h1>
        <div className="flex items-center gap-2">
          <Select value={status} onChange={e => setStatus(e.target.value)} className="w-auto">
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </Select>
          <Button onClick={() => save.mutate()} disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </div>
      </div>

      {save.error && <Alert tone="error">{save.error.message}</Alert>}

      <Card>
        <Field label="Title">
          <Input value={title} onChange={e => setTitle(e.target.value)} />
        </Field>

        <Field
          label="Path"
          hint={<>Lowercase letters, digits, hyphens, slashes (e.g. <code>blog/my-post</code>).</>}
        >
          <Input
            mono
            value={pathInput}
            onChange={e => isNew && setPathInput(e.target.value)}
            readOnly={!isNew}
            placeholder="blog/my-post"
          />
        </Field>
      </Card>

      <div className="rounded-lg border border-zinc-200 bg-white">
        <textarea ref={editorRef} defaultValue="" />
      </div>
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
      return '\n\n' + node.outerHTML + '\n\n';
    },
  });
  return td;
}
