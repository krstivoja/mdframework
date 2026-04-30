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
      const payload = { title, body, status, template, taxonomies: taxValues };
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
      toast.show(`Saved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
      if (isNew) {
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
