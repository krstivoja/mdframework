import { useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api } from './api.js';
import { encodePath } from './utils.js';
import { useToast } from './toast.jsx';

/**
 * Save + delete mutations for the page editor, plus the Cmd/Ctrl+S keybinding
 * that fires the save. Lives outside `<PageEditor>` so the screen file stays
 * focused on layout + state, not network plumbing.
 *
 * The hook returns `{ save, del }` which behave like the bare `useMutation`
 * results the screen previously held.
 */
export function usePageMutations({
  isNew,
  path,
  folder,
  slug,
  title,
  status,
  template,
  taxValues,
  editorMode,
  edRef,
  htmlValue,
  setDirty,
}) {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();

  // Delete moves the page to trash and returns a token. We navigate back to
  // the folder list and surface an Undo toast there — the editor itself is
  // gone by the time the user reaches for it, so showing the toast at the
  // destination is what makes Undo discoverable.
  const del = useMutation({
    mutationFn: async () => {
      const data = await api.delete(`/pages/${encodePath(path)}`);
      return { token: data?.token, title: title || path };
    },
    onSuccess: ({ token, title: pageTitle }) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      setDirty(false);
      navigate(`/${encodeURIComponent(folder)}`, { replace: true });
      toast.show(`Deleted "${pageTitle}".`, {
        duration: 10000,
        action: token ? {
          label: 'Undo',
          onClick: async () => {
            try {
              const res = await api.post('/pages-restore', { token });
              qc.invalidateQueries({ queryKey: ['pages'] });
              toast.show('Restored.', { tone: 'info', duration: 1800 });
              if (res?.path) navigate(`/${encodePath(res.path)}`);
            } catch {
              toast.show("Couldn't restore — it may have already been purged.", { tone: 'error' });
            }
          },
        } : undefined,
      });
    },
    onError: (err) => toast.show(err.message || "Couldn't delete.", { tone: 'error' }),
  });

  const save = useMutation({
    mutationFn: async () => {
      // Toast UI stores content as markdown internally regardless of which
      // edit mode (wysiwyg / markdown) the user is in — `getMarkdown()` is
      // always the source of truth. When the user is in our custom HTML view,
      // push the textarea content back through `setHTML` so Toast UI's
      // HTML→Markdown converter runs before we serialize.
      if (editorMode === 'html') {
        try { edRef.current?.setHTML?.(htmlValue); } catch { /* ignore */ }
      }
      const body = edRef.current?.getMarkdown?.() ?? '';
      const relPath = [folder, slug].filter(Boolean).join('/');
      // `path` is the *target* — for an update it doubles as the rename
      // request when it differs from the URL path; for a create it's the
      // location to write to.
      const payload = { title, body, status, template, taxonomies: taxValues, path: relPath };
      if (isNew) {
        return api.post('/pages', payload);
      }
      return api.put(`/pages/${encodePath(path)}`, payload);
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      qc.invalidateQueries({ queryKey: ['page', res.path] });
      // Stale cache under the old key — the page editor reads `['page', path]`
      // on next mount and would otherwise show the previous content briefly.
      if (path && res.path && res.path !== path) {
        qc.removeQueries({ queryKey: ['page', path] });
      }
      setDirty(false);
      toast.show(`Saved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
      // Navigate when the path changed — covers both initial create and
      // renames of an existing page.
      if (res.path && res.path !== path) {
        const rest = (res.path || '').split('/').slice(1).join('/');
        navigate(`/${encodeURIComponent(folder)}/${encodePath(rest)}`, { replace: true });
      }
    },
  });

  // Cmd/Ctrl+S — save without leaving the keyboard. `save.isPending` guards
  // against firing a second mutation while one is in flight.
  useEffect(() => {
    function onKey(e) {
      const isMeta = e.metaKey || e.ctrlKey;
      if (!isMeta || e.key.toLowerCase() !== 's') return;
      e.preventDefault();
      if (!save.isPending) save.mutate();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [save]);

  return { save, del };
}
