import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { getCsrf } from '../../lib/api.js';

// The Bricks-style visual canvas. The rendered block tree IS the editor
// surface — the iframe shows live HTML and a thin injected script
// captures clicks + double-clicks, posts the block id back to the parent
// (this component), and the parent draws a floating toolbar over the
// selected element. Inline text edits flow through the same channel.
//
// Why an iframe: isolation. The block tree gets the theme's actual CSS
// without the admin chrome bleeding in, and our editor styles can't
// accidentally style content.
export default function VisualCanvas({
  tree,
  pageMeta,
  selectedId,
  onSelect,
  onTextEdit,
  onMove,
  onRemove,
  onAddChild,
  selectedDef,
}) {
  const iframeRef = useRef(null);
  const [docReady, setDocReady] = useState(false);
  const [rect, setRect] = useState(null);
  const [previewHtml, setPreviewHtml] = useState('');
  const reqIdRef = useRef(0);

  // Debounced server render — typing in a text field shouldn't fire one
  // request per keystroke. 250ms is comfortable for paste / quick edits
  // and barely noticeable on a slow keystroke.
  useEffect(() => {
    const id = ++reqIdRef.current;
    const timer = setTimeout(async () => {
      try {
        const res = await fetch('/admin/api/blocks/render', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() },
          body: JSON.stringify({ blocks: tree, page: pageMeta || {} }),
        });
        const data = await res.json();
        // Discard out-of-order responses.
        if (id !== reqIdRef.current) return;
        if (data.ok) setPreviewHtml(data.html || '');
      } catch { /* keep last known */ }
    }, 250);
    return () => clearTimeout(timer);
  }, [tree, pageMeta]);

  // Wire postMessage from the injected iframe script.
  useEffect(() => {
    function onMsg(e) {
      if (!e.data || typeof e.data !== 'object') return;
      if (e.data.type === 'fp-select') {
        onSelect(e.data.id || null);
      } else if (e.data.type === 'fp-text-edit') {
        onTextEdit?.(e.data.id, e.data.field, e.data.value);
      } else if (e.data.type === 'fp-rect') {
        setRect(e.data.rect);
      }
    }
    window.addEventListener('message', onMsg);
    return () => window.removeEventListener('message', onMsg);
  }, [onSelect, onTextEdit]);

  // Whenever the iframe content changes, re-poll the rect for the
  // currently selected block (its position will have moved post-render).
  useLayoutEffect(() => {
    if (!docReady || !selectedId) { setRect(null); return; }
    const w = iframeRef.current?.contentWindow;
    if (!w) return;
    w.postMessage({ type: 'fp-rect-request', id: selectedId }, '*');
  }, [docReady, selectedId, previewHtml]);

  const onLoad = useCallback(() => {
    setDocReady(true);
    // Inform iframe of current selection so it can draw the outline
    // immediately after a re-render.
    iframeRef.current?.contentWindow?.postMessage(
      { type: 'fp-select-set', id: selectedId },
      '*',
    );
  }, [selectedId]);

  // Push selection changes into the iframe so the outline tracks the
  // sidebar / inspector.
  useEffect(() => {
    if (!docReady) return;
    iframeRef.current?.contentWindow?.postMessage(
      { type: 'fp-select-set', id: selectedId },
      '*',
    );
  }, [selectedId, docReady]);

  const srcDoc = wrapPreview(previewHtml);

  return (
    <div className="relative h-full w-full overflow-hidden bg-zinc-100">
      <iframe
        ref={iframeRef}
        title="Visual block canvas"
        srcDoc={srcDoc}
        sandbox="allow-same-origin allow-scripts"
        onLoad={onLoad}
        className="h-full w-full"
      />
      {selectedId && rect && (
        <SelectionToolbar
          rect={rect}
          selectedId={selectedId}
          canHaveChildren={!!selectedDef?.hasChildren}
          onMove={onMove}
          onRemove={onRemove}
          onAddChild={onAddChild}
        />
      )}
      {!tree.length && (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div className="rounded-lg border border-dashed border-zinc-300 bg-white/90 px-6 py-8 text-center text-sm text-zinc-600">
            Pick a block from the palette to start composing.
          </div>
        </div>
      )}
    </div>
  );
}

function SelectionToolbar({ rect, canHaveChildren, onMove, onRemove, selectedId, onAddChild }) {
  // Position is absolute over the iframe, pinned to the top-left of the
  // block's bounding rect. The toolbar sits *above* the block so it
  // doesn't cover the content the user just clicked.
  const top = Math.max(0, rect.top - 36);
  const left = Math.max(8, rect.left);
  return (
    <div
      role="toolbar"
      style={{ position: 'absolute', top, left, zIndex: 20 }}
      className="pointer-events-auto flex items-center gap-1 rounded-md border border-zinc-200 bg-white p-1 shadow-popover"
      onMouseDown={(e) => e.preventDefault()}
    >
      <ToolbarBtn title="Move up"   onClick={() => onMove(selectedId, 'up')}>↑</ToolbarBtn>
      <ToolbarBtn title="Move down" onClick={() => onMove(selectedId, 'down')}>↓</ToolbarBtn>
      {canHaveChildren && (
        <ToolbarBtn title="Add child block" onClick={() => onAddChild?.(selectedId)}>+</ToolbarBtn>
      )}
      <span className="mx-0.5 h-4 w-px bg-zinc-200" />
      <ToolbarBtn title="Delete" tone="danger" onClick={() => onRemove(selectedId)}>×</ToolbarBtn>
    </div>
  );
}

function ToolbarBtn({ children, tone = 'plain', ...rest }) {
  const palette = tone === 'danger'
    ? 'text-red-600 hover:bg-red-50'
    : 'text-zinc-700 hover:bg-zinc-100';
  return (
    <button
      type="button"
      {...rest}
      className={`inline-flex h-7 w-7 items-center justify-center rounded text-[14px] font-semibold ${palette}`}
    >
      {children}
    </button>
  );
}

function wrapPreview(inner) {
  // Inject a small script that:
  //  - Listens for clicks on any [data-block-id] descendant and posts
  //    the id back so the parent can select it.
  //  - Responds to 'fp-select-set' by toggling an outline class on the
  //    matching block.
  //  - Responds to 'fp-rect-request' by posting the selected block's
  //    bounding rect (relative to the iframe viewport) back to the parent.
  //  - Marks elements with [data-fp-text="<field>"] contenteditable so
  //    inline edits flow back via 'fp-text-edit'.
  const script = `
    (function () {
      var SEL = null;
      function setOutline(id) {
        document.querySelectorAll('.fp-selected,.fp-hovered').forEach(function (n) {
          n.classList.remove('fp-selected');
        });
        SEL = id;
        if (!id) return;
        var el = document.querySelector('[data-block-id="' + id + '"]');
        if (el) el.classList.add('fp-selected');
        sendRect();
      }
      function sendRect() {
        if (!SEL) return;
        var el = document.querySelector('[data-block-id="' + SEL + '"]');
        if (!el) { parent.postMessage({ type: 'fp-rect', rect: null }, '*'); return; }
        var r = el.getBoundingClientRect();
        parent.postMessage({
          type: 'fp-rect',
          rect: { top: r.top, left: r.left, width: r.width, height: r.height },
        }, '*');
      }
      document.addEventListener('click', function (e) {
        var t = e.target;
        if (!(t instanceof Element)) return;
        // Don't hijack clicks on real anchors — let the user open them
        // in a new tab if they want to inspect.
        if (t.closest('a[href]')) e.preventDefault();
        var w = t.closest('[data-block-id]');
        if (!w) return;
        e.stopPropagation();
        parent.postMessage({ type: 'fp-select', id: w.getAttribute('data-block-id') }, '*');
      }, true);
      document.addEventListener('mouseover', function (e) {
        document.querySelectorAll('.fp-hovered').forEach(function (n) { n.classList.remove('fp-hovered'); });
        var w = e.target.closest && e.target.closest('[data-block-id]');
        if (w && w.getAttribute('data-block-id') !== SEL) w.classList.add('fp-hovered');
      });
      document.addEventListener('mouseout', function () {
        document.querySelectorAll('.fp-hovered').forEach(function (n) { n.classList.remove('fp-hovered'); });
      });
      // Inline text edits — fields are opted-in via [data-fp-text].
      document.querySelectorAll('[data-fp-text]').forEach(function (n) {
        n.setAttribute('contenteditable', 'true');
        n.addEventListener('blur', function () {
          var w = n.closest('[data-block-id]');
          if (!w) return;
          parent.postMessage({
            type: 'fp-text-edit',
            id: w.getAttribute('data-block-id'),
            field: n.getAttribute('data-fp-text'),
            value: n.innerText,
          }, '*');
        });
      });
      window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'fp-select-set') setOutline(e.data.id);
        if (e.data.type === 'fp-rect-request') { SEL = e.data.id; setOutline(e.data.id); }
      });
      // Track resize / scroll so the floating toolbar follows the block.
      window.addEventListener('resize', sendRect);
      window.addEventListener('scroll', sendRect, true);
    })();
  `;
  // Minimal CSS for the canvas: outline on hover, ring on select, system
  // font fallback so the page is readable before theme styles load.
  const style = `
    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; color: #18181b; }
    .fp-block { position: relative; }
    .fp-hovered { outline: 1px dashed #94a3b8; outline-offset: -1px; }
    .fp-selected { outline: 2px solid #2563eb !important; outline-offset: -2px; }
  `;
  return `<!doctype html><html><head><meta charset="utf-8"><base href="/"><link rel="stylesheet" href="/assets/style.css"><style>${style}</style></head><body>${inner}<script>${script}<\/script></body></html>`;
}
