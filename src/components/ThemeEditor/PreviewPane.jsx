import { useEffect, useRef, useState } from 'react';
import { Button, SegmentedControl } from '../ui/index.js';

const WIDTHS = {
  desktop: { width: '100%',  label: 'Desktop' },
  tablet:  { width: '820px', label: 'Tablet'  },
  mobile:  { width: '380px', label: 'Mobile'  },
};

// Live preview iframe for the public site. `version` is a bumping counter
// the parent increments after a successful save — we use it as a
// cache-busting query string so the iframe fetches a fresh render.
//
// The element-hover overlay is opt-in via the Inspect toggle. When on, we
// inject a tiny script into the iframe document that posts the hovered
// element's tag + classes to the parent via postMessage, and the parent
// forwards them to onHover().
export default function PreviewPane({ version, onHover }) {
  const iframeRef = useRef(null);
  const [device, setDevice] = useState('desktop');
  const [url, setUrl] = useState('/');
  const [inspect, setInspect] = useState(false);

  // Cache-buster bound to the save counter — flipping it forces a refetch
  // of the iframe without scaling the user out of their scroll position.
  const src = `${url}${url.includes('?') ? '&' : '?'}__fp=${version}`;

  // Wire postMessage hover events.
  useEffect(() => {
    function onMessage(e) {
      if (e.source !== iframeRef.current?.contentWindow) return;
      if (!e.data || e.data.type !== 'fp-hover') return;
      onHover?.(e.data.tag, e.data.className);
    }
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [onHover]);

  // Re-inject the inspector script every time the iframe reloads while
  // inspect mode is on.
  useEffect(() => {
    if (!inspect) return undefined;
    const iframe = iframeRef.current;
    if (!iframe) return undefined;
    function inject() {
      try {
        const doc = iframe.contentDocument;
        if (!doc || doc.getElementById('__fp_hover')) return;
        const script = doc.createElement('script');
        script.id = '__fp_hover';
        script.textContent = `
          (function () {
            const style = document.createElement('style');
            style.textContent = '[data-fp-hover]{outline:2px solid #f59e0b!important;outline-offset:-2px;}';
            document.head.appendChild(style);
            let last = null;
            document.addEventListener('mouseover', function (e) {
              if (last) last.removeAttribute('data-fp-hover');
              const t = e.target;
              if (!(t instanceof Element)) return;
              t.setAttribute('data-fp-hover', '1');
              last = t;
              parent.postMessage({
                type: 'fp-hover',
                tag: t.tagName.toLowerCase(),
                className: typeof t.className === 'string' ? t.className : '',
              }, '*');
            }, true);
          })();
        `;
        doc.body.appendChild(script);
      } catch {
        // Cross-origin or doc not ready — re-tried on next load.
      }
    }
    inject();
    iframe.addEventListener('load', inject);
    return () => iframe.removeEventListener('load', inject);
  }, [inspect, src]);

  function reload() {
    if (iframeRef.current) iframeRef.current.contentWindow?.location.reload();
  }

  const w = WIDTHS[device] || WIDTHS.desktop;

  return (
    <div className="flex h-full min-w-0 flex-col border-l border-zinc-200 bg-zinc-100">
      <header className="flex items-center gap-2 border-b border-zinc-200 bg-white px-3 py-2">
        <input
          type="text"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          placeholder="/"
          aria-label="Preview URL"
          className="h-8 w-44 rounded-md border border-zinc-200 px-2 font-mono text-[12px]"
        />
        <Button variant="secondary" size="sm" onClick={reload}>Reload</Button>
        <SegmentedControl
          ariaLabel="Preview width"
          value={device}
          onChange={setDevice}
          options={[
            { value: 'desktop', label: 'Desktop' },
            { value: 'tablet',  label: 'Tablet'  },
            { value: 'mobile',  label: 'Mobile'  },
          ]}
        />
        <label className="ml-auto flex cursor-pointer items-center gap-1.5 text-[12px] text-zinc-700">
          <input
            type="checkbox"
            checked={inspect}
            onChange={(e) => setInspect(e.target.checked)}
            className="h-4 w-4 cursor-pointer rounded border-zinc-300"
          />
          Inspect
        </label>
      </header>

      <div className="flex-1 overflow-auto p-4">
        <div className="mx-auto h-full bg-white shadow-card transition-[width] duration-200" style={{ width: w.width, maxWidth: '100%' }}>
          <iframe
            ref={iframeRef}
            title="Theme preview"
            src={src}
            className="block h-full w-full"
            sandbox="allow-same-origin allow-scripts allow-forms allow-popups"
          />
        </div>
      </div>
    </div>
  );
}
