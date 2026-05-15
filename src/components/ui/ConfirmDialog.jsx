import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import Button from './Button.jsx';
import useFocusTrap from '../../lib/useFocusTrap.js';

// Themed replacement for `window.confirm()`. Portal-mounted on body so it
// floats above any modal context (Toast UI editor, MediaPicker). Esc + the
// backdrop close it; the primary action is auto-focused and Enter triggers it.
// Focus is trapped while open and restored to the opener on close.
export default function ConfirmDialog({
  open,
  title = 'Are you sure?',
  message,
  confirmLabel = 'Delete',
  cancelLabel = 'Cancel',
  variant = 'danger',
  onConfirm,
  onCancel,
}) {
  const dialogRef = useRef(null);
  const confirmRef = useRef(null);

  useFocusTrap(dialogRef, open, confirmRef);

  useEffect(() => {
    if (!open) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') onCancel?.(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onCancel]);

  if (!open) return null;

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={(e) => { if (e.target === e.currentTarget) onCancel?.(); }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        className="w-full max-w-sm rounded-lg bg-white p-5 shadow-modal"
      >
        <h2 id="confirm-dialog-title" className="text-base font-semibold text-zinc-900">{title}</h2>
        {message && <p className="mt-2 text-sm text-zinc-600">{message}</p>}
        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" onClick={onCancel}>{cancelLabel}</Button>
          <Button ref={confirmRef} variant={variant} onClick={onConfirm}>{confirmLabel}</Button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
