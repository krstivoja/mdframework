import { useFileUpload } from '../lib/hooks.js';
import { Alert, Dropzone } from './ui/index.js';

// Upload tab for the MediaPicker — drop-zone + click-to-pick. On a successful
// `POST /admin/api/media` the new file is auto-selected via `onPick`.
export default function MediaPickerUploadTab({ onPick, pagePath }) {
  const { upload, busy, error } = useFileUpload({
    endpoint: '/admin/api/media',
    extraFields: pagePath ? { page_path: pagePath } : {},
    invalidate: [['media']],
  });

  async function uploadFile(file) {
    if (!file) return;
    try {
      const data = await upload(file);
      onPick({ url: data.url, alt: file.name });
    } catch { /* error surfaced via the hook */ }
  }

  return (
    <div className="space-y-3">
      {error && <Alert tone="error">{error}</Alert>}
      <Dropzone
        accept="image/*"
        disabled={busy}
        label="Drop an image here"
        buttonLabel={busy ? 'Uploading…' : 'Choose file'}
        onFiles={(files) => uploadFile(files[0])}
      />
    </div>
  );
}
