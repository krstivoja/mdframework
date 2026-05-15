import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from './api.js';
import { useToast } from './toast.jsx';
import { encodePath } from './utils.js';

/**
 * Trash-aware delete for the pages list. Returns `{ del, deleteMany }`:
 *
 *   del.mutate(page)   — soft-delete a single page; toast shows Undo for 10s.
 *   deleteMany(paths)  — bulk variant; one toast for the batch, Undo
 *                        restores all tokens in parallel.
 *
 * The server moves files to `site/cache/trash/<token>/` and purges entries
 * older than 24h on the next page-list request, so Undo only works inside
 * that window — the toast's 10s lifetime is the social contract.
 */
export function usePageTrash() {
  const qc = useQueryClient();
  const toast = useToast();

  async function restoreTokens(tokens, expected) {
    const results = await Promise.all(
      tokens.map((t) => api.post('/pages-restore', { token: t }).catch(() => null)),
    );
    const ok = results.filter(Boolean).length;
    qc.invalidateQueries({ queryKey: ['pages'] });
    if (ok === expected) {
      toast.show(`Restored ${ok} ${ok === 1 ? 'page' : 'pages'}.`, { tone: 'info', duration: 1800 });
    } else {
      toast.show(`Restored ${ok} of ${expected}. The rest may have already been purged.`, { tone: 'error', duration: 4000 });
    }
  }

  const del = useMutation({
    mutationFn: async (page) => {
      const data = await api.delete(`/pages/${encodePath(page.path)}`);
      return { token: data.token, title: page.title || page.path };
    },
    onSuccess: ({ token, title }) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      toast.show(`Deleted "${title}".`, {
        duration: 10000,
        action: token ? { label: 'Undo', onClick: () => restoreTokens([token], 1) } : undefined,
      });
    },
    onError: (err) => toast.show(err.message || "Couldn't delete.", { tone: 'error' }),
  });

  async function deleteMany(paths) {
    const tokens = [];
    for (const path of paths) {
      try {
        const data = await api.delete(`/pages/${encodePath(path)}`);
        if (data?.token) tokens.push(data.token);
      } catch { /* keep going */ }
    }
    qc.invalidateQueries({ queryKey: ['pages'] });
    toast.show(`Deleted ${tokens.length} ${tokens.length === 1 ? 'page' : 'pages'}.`, {
      duration: 10000,
      action: tokens.length > 0
        ? { label: 'Undo', onClick: () => restoreTokens(tokens, tokens.length) }
        : undefined,
    });
  }

  return { del, deleteMany };
}
