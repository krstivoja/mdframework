import { Annotation } from '@codemirror/state';
import { useEffect, useRef } from 'react';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { html } from '@codemirror/lang-html';
import {
  syntaxHighlighting,
  defaultHighlightStyle,
  bracketMatching,
  indentOnInput,
} from '@codemirror/language';

// Annotation used to tag transactions where we (not the user) wrote the
// new doc — file switch, mode swap, external buffer reset. The update
// listener uses this to suppress onChange so the parent doesn't get
// marked dirty by its own hydration.
const SYNC_FROM_PROP = Annotation.define();

/**
 * Thin React wrapper around a CodeMirror 6 EditorView. Used by PageEditor for
 * the HTML view and reusable for any future code-editing surface.
 *
 * The editor is created once on mount; subsequent prop changes (`value`)
 * are applied via dispatch only when they differ from the current document,
 * so React-driven re-renders don't blow away cursor/selection state.
 */
export default function CodeEditor({
  value,
  onChange,
  language = 'html',
  className = '',
  focusLine = null,
}) {
  const hostRef    = useRef(null);
  const viewRef    = useRef(null);
  const onChangeRef = useRef(onChange);

  // Keep the latest onChange in a ref so the EditorView's update listener
  // (created once) always calls the freshest closure without re-instantiating
  // the editor on every parent re-render.
  useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

  useEffect(() => {
    if (!hostRef.current || viewRef.current) return;

    const langExt = language === 'html' ? html() : html();

    const state = EditorState.create({
      doc: value || '',
      extensions: [
        lineNumbers(),
        history(),
        bracketMatching(),
        indentOnInput(),
        highlightActiveLine(),
        syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
        keymap.of([indentWithTab, ...defaultKeymap, ...historyKeymap]),
        langExt,
        EditorView.lineWrapping,
        EditorView.updateListener.of((update) => {
          if (!update.docChanged) return;
          // Skip transactions we dispatched ourselves to mirror a `value`
          // prop change — otherwise the parent's hydration of a freshly-
          // loaded file would round-trip back as "user edit" and trip the
          // dirty flag the moment a file tab is clicked.
          const fromProp = update.transactions.some(
            (tr) => tr.annotation(SYNC_FROM_PROP),
          );
          if (fromProp) return;
          onChangeRef.current?.(update.state.doc.toString());
        }),
      ],
    });

    viewRef.current = new EditorView({ state, parent: hostRef.current });

    return () => {
      viewRef.current?.destroy();
      viewRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Sync prop → editor when the parent supplies a different document
  // (e.g. switching files, mode swap). Compares text equality so we don't
  // dispatch redundant transactions while the user is typing.
  useEffect(() => {
    const view = viewRef.current;
    if (!view) return;
    const current = view.state.doc.toString();
    if (current === (value ?? '')) return;
    view.dispatch({
      changes: { from: 0, to: current.length, insert: value ?? '' },
      annotations: SYNC_FROM_PROP.of(true),
    });
  }, [value]);

  useEffect(() => {
    const view = viewRef.current;
    if (!view || !focusLine) return;
    const lineNo = Math.max(1, Math.min(focusLine, view.state.doc.lines));
    const line = view.state.doc.line(lineNo);
    view.dispatch({
      selection: { anchor: line.from, head: line.to },
      effects: EditorView.scrollIntoView(line.from, { y: 'center' }),
    });
    view.focus();
  }, [focusLine]);

  return <div ref={hostRef} className={`cm-host text-[13px] ${className}`} />;
}
