import { memo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { useConfirmDialog } from '../lib/hooks.js';
import { extLabel, isImageFile } from '../lib/utils.js';
import { Button, ConfirmDialog, Dropzone } from '../components/ui/index.js';
import MediaUploadDialog from '../components/MediaUploadDialog.jsx';

export default function Media() {
  const qc = useQueryClient();
  const [uploadOpen, setUploadOpen] = useState(false);
  // When the empty-state dropzone receives files, we hand them to the upload
  // dialog so the user doesn't have to re-drop inside the modal.
  const [pendingFiles, setPendingFiles] = useState(null);
  const { confirm, dialogProps } = useConfirmDialog();

  const { data, isLoading } = useQuery({
    queryKey: ['media'],
    queryFn: () => api.get('/media'),
  });
  // Drives the size-limit hint in the empty-state dropzone. Falls back to the
  // 5 MB default when settings haven't loaded yet — better than rendering "0 MB".
  const { data: settings } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });
  const maxMb = settings?.uploads?.max_mb ?? 5;

  const del = useMutation({
    mutationFn: (name) => api.delete(`/media/${encodeURIComponent(name)}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['media'] }),
  });

  async function askDelete(name) {
    const ok = await confirm({
      title: 'Delete media',
      message: `Delete ${name}? This cannot be undone.`,
    });
    if (ok) del.mutate(name);
  }

  if (isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Global media library</h1>
        <Button onClick={() => setUploadOpen(true)}>Upload</Button>
      </div>

      {(data?.files || []).length === 0 ? (
        <div className="space-y-3 rounded-lg border border-zinc-200 bg-white p-6 shadow-card">
          <div className="space-y-1 text-center">
            <h2 className="text-base font-semibold text-zinc-800">Your media library is empty</h2>
            <p className="text-[13px] text-zinc-500">
              Upload images, PDFs, or ZIPs and link them from any page. Files live in site/uploads/ and are served straight off disk — no database, no resize step.
            </p>
          </div>
          <Dropzone
            accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.zip,image/*,application/pdf,application/zip"
            multiple
            label="Drop files here to upload"
            hint={`JPG · PNG · GIF · WebP · SVG · PDF · ZIP — up to ${maxMb} MB each`}
            buttonLabel="Choose files"
            onFiles={(files) => { setPendingFiles(files); setUploadOpen(true); }}
          />
          <p className="text-center text-[11px] text-zinc-400">
            Change the size limit under Settings → Uploads.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
          {(data?.files || []).map(f => (
            <MediaItem key={f.name + (f.url || '')} file={f} onDelete={() => askDelete(f.name)} />
          ))}
        </div>
      )}

      <MediaUploadDialog
        open={uploadOpen}
        onClose={() => { setUploadOpen(false); setPendingFiles(null); }}
        initialFiles={pendingFiles}
      />
      <ConfirmDialog {...dialogProps} />
    </div>
  );
}

const MediaItem = memo(function MediaItem({ file, onDelete }) {
  return (
    <div className="group relative overflow-hidden rounded-lg border border-zinc-200 bg-white">
      {isImageFile(file) ? (
        <img
          src={file.thumb_url || file.url}
          alt={file.alt || file.name}
          loading="lazy"
          decoding="async"
          className="aspect-square w-full object-cover"
        />
      ) : (
        <div className="flex aspect-square w-full items-center justify-center bg-zinc-50 text-xs text-zinc-500">
          {extLabel(file.name)}
        </div>
      )}
      <div className="border-t border-zinc-100 p-2">
        <div className="truncate text-xs font-medium" title={file.name}>{file.name}</div>
        <div className="mt-1 flex items-center justify-between">
          <a href={file.url} target="_blank" rel="noreferrer" className="text-xs text-zinc-500 hover:underline">
            View
          </a>
          <button
            onClick={onDelete}
            className="text-xs text-red-600 hover:underline"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  );
});
