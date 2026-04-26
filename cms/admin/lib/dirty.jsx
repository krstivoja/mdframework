import { createContext, useCallback, useContext, useRef, useState } from 'react';

const DirtyContext = createContext(null);

export function DirtyProvider({ children }) {
  const [dirty, setDirty] = useState(false);
  const messageRef = useRef('Discard unsaved changes?');

  const confirmLeave = useCallback(() => {
    if (!dirty) return true;
    return window.confirm(messageRef.current);
  }, [dirty]);

  return (
    <DirtyContext.Provider value={{ dirty, setDirty, confirmLeave }}>
      {children}
    </DirtyContext.Provider>
  );
}

export function useDirty() {
  const ctx = useContext(DirtyContext);
  if (!ctx) return { dirty: false, setDirty: () => {}, confirmLeave: () => true };
  return ctx;
}
