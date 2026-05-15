import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * Floating action bubble shown above an `<img>` inside the Toast UI WYSIWYG
 * surface. Toast UI doesn't expose a native image inspector, and clicking an
 * image in WYSIWYG isn't visually obvious — so we install our own click
 * delegate, track the active image's bounding rect, and render a small
 * Replace / Delete toolbar at viewport coordinates.
 *
 * Editing markdown is delegated back to the parent via `onReplace(url)` and
 * `onDelete()`; this component is purely presentational.
 *
 * Accessibility: rendered as `role="toolbar"`; focus moves to Replace when
 * the toolbar appears and is returned to the editor surface on close (Esc,
 * outside click, or after an action). Keyboard users can reach the toolbar
 * once a click has surfaced it; pure-keyboard discovery still depends on the
 * Toast UI image insertion flow (see app/docs/accessibility.md).
 */
export default function EditorImageMenu({
  containerRef,
  enabled,
  onReplace,
  onDelete,
}) {
  const [target, setTarget] = useState(null); // { rect, url, alt }
  const firstActionRef = useRef(null);
  const previousFocusRef = useRef(null);

  useEffect(() => {
    if (!enabled) return undefined;
    const root = containerRef.current;
    if (!root) return undefined;

    function pick(e) {
      const img = e.target?.closest?.('img');
      if (img && root.contains(img)) {
        const rect = img.getBoundingClientRect();
        setTarget({
          rect,
          url: img.getAttribute('src') || '',
          alt: img.getAttribute('alt') || '',
        });
        // Don't preventDefault — Toast UI's own selection ring is useful too.
      } else {
        setTarget(null);
      }
    }

    function reposition() {
      // The image may have moved (scroll, layout) — refresh rect from the
      // node we captured. If it's gone (e.g. just deleted), close the menu.
      setTarget((prev) => {
        if (!prev) return prev;
        const imgs = Array.from(root.querySelectorAll('img'));
        const node = imgs.find((i) => i.getAttribute('src') === prev.url);
        if (!node) return null;
        return { ...prev, rect: node.getBoundingClientRect() };
      });
    }

    root.addEventListener('click', pick);
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
    return () => {
      root.removeEventListener('click', pick);
      window.removeEventListener('scroll', reposition, true);
      window.removeEventListener('resize', reposition);
    };
  }, [containerRef, enabled]);

  // Close on Esc + outside click. Pointerdown so we don't fight the click
  // delegate above when the user clicks a different image. Move focus to
  // the first action on appear; restore it to whatever the editor had on
  // close so keyboard users return to where they were.
  useEffect(() => {
    if (!target) return undefined;
    previousFocusRef.current = document.activeElement;
    const id = requestAnimationFrame(() => firstActionRef.current?.focus());

    function onKey(e) { if (e.key === 'Escape') setTarget(null); }
    function onPointer(e) {
      if (e.target.closest('[data-editor-image-menu]')) return;
      if (e.target.closest('img')) return;
      setTarget(null);
    }
    window.addEventListener('keydown', onKey);
    window.addEventListener('pointerdown', onPointer, true);
    return () => {
      cancelAnimationFrame(id);
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('pointerdown', onPointer, true);
      const prev = previousFocusRef.current;
      if (prev instanceof HTMLElement && document.body.contains(prev)) prev.focus();
    };
  }, [target]);

  if (!target) return null;

  // Position above the image, aligned to its left edge. Clamp to viewport so
  // the menu never spills off-screen on a small image near the top.
  const top = Math.max(8, target.rect.top - 40);
  const left = Math.max(8, Math.min(window.innerWidth - 180, target.rect.left));

  return createPortal(
    <div
      data-editor-image-menu
      style={{ position: 'fixed', top, left, zIndex: 60 }}
      className="flex items-center gap-1 rounded-md border border-zinc-200 bg-white p-1 shadow-popover"
      role="toolbar"
      aria-label="Image actions"
    >
      <button
        ref={firstActionRef}
        type="button"
        onClick={() => { onReplace(target); setTarget(null); }}
        className="rounded px-2 py-1 text-[12px] font-medium text-zinc-700 hover:bg-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900/20"
      >
        Replace image
      </button>
      <button
        type="button"
        onClick={() => { onDelete(target); setTarget(null); }}
        className="rounded px-2 py-1 text-[12px] font-medium text-red-600 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/30"
      >
        Delete image
      </button>
    </div>,
    document.body,
  );
}
