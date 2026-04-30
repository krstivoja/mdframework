import { useRef, useState } from 'react';
import Button from './Button.jsx';

/**
 * Drag-and-drop file picker shared by the media uploader and the backup
 * restore form. Renders the dashed-border zone, owns the drag/click hover
 * state, and emits a flat list of File objects via `onFiles`.
 *
 * Pass `multiple` to allow selecting more than one file at once. `accept`
 * mirrors the underlying `<input type="file">` accept attribute. The label
 * and hint default to a media-friendly wording — override for other use
 * cases.
 *
 * Optional `selectedLabel` shows a "Selected: <name>" line below the zone
 * when the parent wants the dropzone to act as a sticky picker (no auto-
 * upload — see backup restore).
 */
export default function Dropzone({
  onFiles,
  accept,
  multiple = false,
  disabled = false,
  label = 'Drop a file here',
  hint = 'or',
  buttonLabel = 'Choose file',
  selectedLabel,
}) {
  const inputRef = useRef(null);
  const [drag, setDrag] = useState(false);

  function handleFiles(fileList) {
    if (disabled) return;
    const files = Array.from(fileList || []).filter(Boolean);
    if (files.length === 0) return;
    onFiles(multiple ? files : [files[0]]);
  }

  return (
    <div className="space-y-2">
      <div
        onDragEnter={(e) => { e.preventDefault(); if (!disabled) setDrag(true); }}
        onDragOver={(e)  => { e.preventDefault(); if (!disabled) setDrag(true); }}
        onDragLeave={()  => setDrag(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDrag(false);
          handleFiles(e.dataTransfer.files);
        }}
        className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-10 text-center transition-colors ${
          disabled ? 'cursor-not-allowed border-zinc-200 bg-zinc-50 opacity-60' :
          drag     ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-300 bg-white'
        }`}
      >
        <p className="text-sm text-zinc-700">{label}</p>
        <p className="mt-1 text-xs text-zinc-500">{hint}</p>
        <Button
          variant="secondary"
          className="mt-3"
          disabled={disabled}
          onClick={() => inputRef.current?.click()}
        >
          {buttonLabel}
        </Button>
        <input
          ref={inputRef}
          type="file"
          accept={accept}
          multiple={multiple}
          hidden
          onChange={(e) => { handleFiles(e.target.files); e.target.value = ''; }}
        />
      </div>
      {selectedLabel && (
        <p className="truncate text-xs text-zinc-600">
          Selected: <span className="font-mono text-zinc-900">{selectedLabel}</span>
        </p>
      )}
    </div>
  );
}
