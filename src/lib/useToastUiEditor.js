import { useEffect, useRef } from 'react';
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import { getCsrf } from './api.js';

/**
 * Mount and own a Toast UI Editor instance for the page editor.
 *
 * Returns `{ edRef, editorElRef }` — the parent renders `<div ref={editorElRef} />`
 * to give Toast UI a host element, and reads `edRef.current` when it needs
 * to call commands (`getMarkdown`, `setHTML`, `exec('addImage', …)`, …).
 *
 * Initialisation is deferred until `bodyReady` flips so the editor seeds
 * itself from the loaded page body exactly once. `data` is intentionally not
 * a dep on the underlying effect — refetches after a save must not tear the
 * editor down and remount it, which would dump cursor focus back to the top
 * of the document.
 */
export function useToastUiEditor({
  isNew,
  bodyReady,
  initialBody,
  pagePath,
  onDirty,
  onOpenMediaPicker,
}) {
  const editorElRef = useRef(null);
  const edRef = useRef(null);
  const initializedRef = useRef(false);

  useEffect(() => {
    if (!editorElRef.current) return undefined;
    if (initializedRef.current) return undefined;
    if (!bodyReady) return undefined;

    // Replace Toast UI's built-in image popup with a custom toolbar button
    // that opens the React MediaPicker. Mounted into a raw <button> so
    // Toast UI's toolbar styles still apply.
    const imageButton = document.createElement('button');
    imageButton.className = 'toastui-editor-toolbar-icons image';
    imageButton.style.margin = '0';
    imageButton.setAttribute('aria-label', 'Insert image');
    imageButton.setAttribute('type', 'button');
    imageButton.addEventListener('click', () => onOpenMediaPicker());

    const ed = new Editor({
      el: editorElRef.current,
      // `100%` lets the editor fill its flex parent (the wrapper sets a
      // bounded height with `flex-1 min-h-0`). Hard-coded 600px would leave
      // the bottom of the page empty on tall viewports.
      height: '100%',
      initialEditType: 'wysiwyg',
      previewStyle: 'vertical',
      usageStatistics: false,
      hideModeSwitch: true,
      initialValue: !isNew ? initialBody : '',
      toolbarItems: [
        ['heading', 'bold', 'italic', 'strike'],
        ['hr', 'quote'],
        ['ul', 'ol', 'task', 'indent', 'outdent'],
        ['table', 'link', { name: 'image', tooltip: 'Insert image', el: imageButton }],
        ['code', 'codeblock'],
        ['scrollSync'],
      ],
      hooks: {
        addImageBlobHook(blob, callback) {
          const fd = new FormData();
          fd.append('file', blob);
          if (pagePath) fd.append('page_path', pagePath);
          fetch('/admin/api/media', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': getCsrf() },
            body: fd,
          })
            .then((r) => r.json())
            .then((json) => {
              if (json?.ok && json.url) callback(json.url, blob.name || '');
            })
            .catch(() => { /* ignore */ });
        },
      },
    });
    ed.on('change', () => onDirty(true));
    edRef.current = ed;
    initializedRef.current = true;

    return () => {
      try { ed.destroy?.(); } catch { /* ignore */ }
      edRef.current = null;
      initializedRef.current = false;
    };
    // Same trade-off as before: omit `data`/`initialBody`/callbacks so a
    // post-save refetch doesn't tear the editor down. The init runs once per
    // mount; everything after that goes through the returned ref.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isNew, pagePath, bodyReady]);

  return { edRef, editorElRef };
}
