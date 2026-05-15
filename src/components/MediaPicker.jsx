import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button } from './ui/index.js';
import useFocusTrap from '../lib/useFocusTrap.js';
import MediaPickerLibraryTab from './MediaPickerLibraryTab.jsx';
import MediaPickerUploadTab from './MediaPickerUploadTab.jsx';

/**
 * WordPress-style media picker. Two tabs (Library / Upload) live in their own
 * files; this component is just the modal shell + tab switcher. Portal-mounted
 * on `document.body` so it sits above the Toast UI editor without z-index
 * gymnastics. Closing happens via Esc, the backdrop click, or the Cancel button.
 *
 * Accessibility: announced as `role="dialog" aria-modal="true"`, labelled by
 * the header title. Focus is trapped while open and restored to the opener on
 * close (via `useFocusTrap`).
 */
export default function MediaPicker({ open, onClose, onPick, pagePath = '' }) {
  const [tab, setTab] = useState('library');
  const dialogRef = useRef(null);
  const titleId = useId();

  useEffect(() => { if (open) setTab('library'); }, [open]);

  useEffect(() => {
    if (!open) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  useFocusTrap(dialogRef, open);

  if (!open) return null;

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="flex h-[80vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-modal"
      >
        <header className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
          <h2 id={titleId} className="sr-only">Media picker</h2>
          <div className="flex items-center gap-1 rounded-md border border-zinc-200 bg-white p-1" role="tablist" aria-label="Media picker tabs">
            <TabButton active={tab === 'library'} onClick={() => setTab('library')}>Library</TabButton>
            <TabButton active={tab === 'upload'}  onClick={() => setTab('upload')}>Upload</TabButton>
          </div>
          <Button variant="ghost" onClick={onClose}>Close</Button>
        </header>

        <div className="flex-1 overflow-y-auto p-5">
          {tab === 'library' && <MediaPickerLibraryTab onPick={onPick} pagePath={pagePath} />}
          {tab === 'upload'  && <MediaPickerUploadTab  onPick={onPick} pagePath={pagePath} />}
        </div>
      </div>
    </div>,
    document.body,
  );
}

function TabButton({ active, children, ...rest }) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      {...rest}
      className={`rounded px-2.5 py-1 text-[12px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900/20 ${
        active ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
      }`}
    >
      {children}
    </button>
  );
}
