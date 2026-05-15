import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Bottom-right toast stack. Each toast slides in from the right, lingers for
// `duration` ms, then slides out. Identity is by id so re-renders don't
// re-trigger the entry animation; new toasts stack atop existing ones.

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const idRef = useRef(0);

  const show = useCallback((message, { tone = 'success', duration = 2400 } = {}) => {
    const id = ++idRef.current;
    setToasts((prev) => [...prev, { id, message, tone, duration }]);
    if (duration > 0) {
      setTimeout(() => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
      }, duration);
    }
    return id;
  }, []);

  const dismiss = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ show, dismiss }}>
      {children}
      {createPortal(<ToastStack toasts={toasts} onDismiss={dismiss} />, document.body)}
    </ToastContext.Provider>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider>');
  return ctx;
}

function ToastStack({ toasts, onDismiss }) {
  return (
    <div
      className="pointer-events-none fixed bottom-4 right-4 z-[9999] flex flex-col items-end gap-2"
      role="region"
      aria-live="polite"
      aria-label="Notifications"
    >
      {toasts.map((t) => (
        <ToastItem key={t.id} toast={t} onDismiss={() => onDismiss(t.id)} />
      ))}
    </div>
  );
}

function ToastItem({ toast, onDismiss }) {
  // Two-step animation: render hidden (translate-x), then flip to visible on
  // the next frame so CSS picks up the transition.
  const [shown, setShown] = useState(false);
  useEffect(() => {
    const raf = requestAnimationFrame(() => setShown(true));
    return () => cancelAnimationFrame(raf);
  }, []);

  const tones = {
    success: 'bg-zinc-900 text-white',
    error:   'bg-red-600 text-white',
    info:    'bg-zinc-700 text-white',
  };

  return (
    <div
      onClick={onDismiss}
      className={`pointer-events-auto flex max-w-sm cursor-pointer items-center gap-2 rounded-md px-3.5 py-2 text-[13px] font-medium shadow-modal transition-all duration-200 ${
        tones[toast.tone] || tones.success
      } ${shown ? 'translate-x-0 opacity-100' : 'translate-x-4 opacity-0'}`}
      role="status"
    >
      {toast.message}
    </div>
  );
}
