// Theme Builder block parser.
//
// Walks a Twig source string and emits a tree of blocks for the outline.
// Three kinds of blocks coexist:
//
//   - marker  : explicit author intent — wrapped in {# fp:block ... #} ...
//               {# /fp:block #} comments. These are the *editable* blocks
//               (reorder, drag, delete, add). The marker comment carries
//               an id/type/label.
//   - code    : Twig control flow — {% for %} ... {% endfor %} and
//               {% if %} ... {% endif %}. Visible in the outline as
//               structure; clicking jumps the code editor, but the UI
//               never mutates these.
//   - html    : raw HTML elements (article, section, div, ...) — same
//               read-only role as `code`.
//
// The previous implementation was a line-scan with simple regexes. This
// version tokenizes the source character-by-character with quote-awareness
// so it survives:
//
//   - multi-line opening tags (`<div\n  class="x">`)
//   - multiple elements per line (`<span>a</span><span>b</span>`)
//   - `>` inside attribute values (`<a title="1>2">`)
//   - HTML comments and CDATA — skipped wholesale rather than confusing
//     the tag matcher
//
// The token stream is then run through a stack walker that pairs opens
// with closes and emits the tree. Marker / code / html each track their
// own nesting so a `</div>` doesn't accidentally close a `{% for %}`.

const VISUAL_TAGS = new Set([
  'article', 'aside', 'div', 'footer', 'form', 'header', 'li', 'main',
  'nav', 'ol', 'p', 'section', 'ul', 'h1', 'h2', 'h3', 'h4',
]);

const VOID_TAGS = new Set([
  'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
  'meta', 'param', 'source', 'track', 'wbr',
]);

export function parseThemeBlocks(source) {
  const tokens = tokenize(String(source || ''));
  return buildTree(tokens);
}

export function flattenBlocks(blocks, depth = 0) {
  return blocks.flatMap((block) => [
    { ...block, depth, children: undefined },
    ...flattenBlocks(block.children || [], depth + 1),
  ]);
}

export function findBlock(blocks, id) {
  for (const block of blocks) {
    if (block.id === id) return block;
    const child = findBlock(block.children || [], id);
    if (child) return child;
  }
  return null;
}

export function canEditBlock(block) {
  return block?.source === 'marker';
}

/* -------------------------------------------------------------------- */
/* Tokenizer                                                            */
/* -------------------------------------------------------------------- */

// Emits a flat list of events:
//   { kind: 'marker-open', id, type, label, line }
//   { kind: 'marker-close', line }
//   { kind: 'twig-open', type: 'loop'|'condition', label, line }
//   { kind: 'twig-close', type: 'loop'|'condition', line }
//   { kind: 'html-open', tag, line, selfClosed }
//   { kind: 'html-close', tag, line }
function tokenize(src) {
  const events = [];
  const len = src.length;
  let i = 0;
  let line = 1;

  while (i < len) {
    const ch = src[i];

    if (ch === '\n') { line += 1; i += 1; continue; }

    // Twig comment — may contain an fp:block marker or be a plain comment.
    if (ch === '{' && src[i + 1] === '#') {
      const end = src.indexOf('#}', i + 2);
      if (end === -1) break;
      const body = src.slice(i + 2, end).trim();
      if (/^fp:block\b/.test(body)) {
        const attrs = parseAttrs(body.replace(/^fp:block\b/, ''));
        events.push({
          kind: 'marker-open',
          id: attrs.id || `marker-${line}`,
          type: attrs.type || 'section',
          label: attrs.label || attrs.id || `Block ${line}`,
          line,
        });
      } else if (/^\/fp:block\s*$/.test(body)) {
        events.push({ kind: 'marker-close', line });
      }
      line += countNewlines(src, i, end + 2);
      i = end + 2;
      continue;
    }

    // Twig statement — only the control-flow ones we care about.
    if (ch === '{' && src[i + 1] === '%') {
      const end = src.indexOf('%}', i + 2);
      if (end === -1) break;
      const body = src.slice(i + 2, end).trim();
      const open = body.match(/^(for|if)\b\s*(.*)$/);
      const close = body.match(/^end(for|if)\b/);
      if (open) {
        events.push({
          kind: 'twig-open',
          type: open[1] === 'for' ? 'loop' : 'condition',
          label: open[1] === 'for' ? `Loop ${cleanInline(open[2])}` : `If ${cleanInline(open[2])}`,
          line,
        });
      } else if (close) {
        events.push({
          kind: 'twig-close',
          type: close[1] === 'for' ? 'loop' : 'condition',
          line,
        });
      }
      line += countNewlines(src, i, end + 2);
      i = end + 2;
      continue;
    }

    // Twig output `{{ ... }}` — skip; doesn't affect structure.
    if (ch === '{' && src[i + 1] === '{') {
      const end = src.indexOf('}}', i + 2);
      if (end === -1) break;
      line += countNewlines(src, i, end + 2);
      i = end + 2;
      continue;
    }

    // HTML comment.
    if (ch === '<' && src.startsWith('!--', i + 1)) {
      const end = src.indexOf('-->', i + 4);
      if (end === -1) break;
      line += countNewlines(src, i, end + 3);
      i = end + 3;
      continue;
    }

    // HTML tag — open, close, or self-closing.
    if (ch === '<') {
      const tagMatch = /^<\/?([a-zA-Z][a-zA-Z0-9:-]*)/.exec(src.slice(i));
      if (tagMatch) {
        const isClose = src[i + 1] === '/';
        const tag = tagMatch[1].toLowerCase();
        const tagStartLine = line;
        // Walk past the rest of the opening tag, respecting quoted
        // attribute values so `>` inside a string doesn't close the tag.
        let j = i + tagMatch[0].length;
        let quote = null;
        while (j < len) {
          const c = src[j];
          if (quote) {
            if (c === quote) quote = null;
          } else if (c === '"' || c === "'") {
            quote = c;
          } else if (c === '>') {
            break;
          }
          if (c === '\n') line += 1;
          j += 1;
        }
        if (j >= len) break; // Unterminated tag — bail.
        const selfClosed = src[j - 1] === '/' || VOID_TAGS.has(tag);
        if (isClose) {
          events.push({ kind: 'html-close', tag, line: tagStartLine });
        } else {
          events.push({ kind: 'html-open', tag, line: tagStartLine, selfClosed });
          if (selfClosed) {
            // Void/self-closed: emit a paired close immediately so the
            // stack walker doesn't keep the element open across siblings.
            events.push({ kind: 'html-close', tag, line: tagStartLine });
          }
        }
        i = j + 1;
        continue;
      }
    }

    i += 1;
  }

  return events;
}

/* -------------------------------------------------------------------- */
/* Stack walker — events → tree                                         */
/* -------------------------------------------------------------------- */

function buildTree(events) {
  const root = { id: 'root', children: [] };
  const stack = [root];

  for (const ev of events) {
    if (ev.kind === 'marker-open') {
      pushBlock(stack, {
        id: ev.id,
        type: ev.type,
        label: ev.label,
        source: 'marker',
        startLine: ev.line,
        endLine: ev.line,
        children: [],
      });
      continue;
    }
    if (ev.kind === 'marker-close') {
      closeMostRecent(stack, (b) => b.source === 'marker', ev.line);
      continue;
    }
    if (ev.kind === 'twig-open') {
      pushBlock(stack, {
        id: `twig-${ev.line}`,
        type: ev.type,
        label: ev.label,
        source: 'code',
        startLine: ev.line,
        endLine: ev.line,
        children: [],
      });
      continue;
    }
    if (ev.kind === 'twig-close') {
      closeMostRecent(stack, (b) => b.source === 'code' && b.type === ev.type, ev.line);
      continue;
    }
    if (ev.kind === 'html-open') {
      if (!VISUAL_TAGS.has(ev.tag)) continue;
      pushBlock(stack, {
        id: `html-${ev.line}`,
        type: 'element',
        label: `<${ev.tag}>`,
        tag: ev.tag,
        source: 'html',
        startLine: ev.line,
        endLine: ev.line,
        children: [],
      });
      continue;
    }
    if (ev.kind === 'html-close') {
      if (!VISUAL_TAGS.has(ev.tag)) continue;
      closeMostRecent(stack, (b) => b.source === 'html' && b.tag === ev.tag, ev.line);
      continue;
    }
  }

  // Anything still open at EOF gets its end line clamped to the last
  // event's line so down-stream code (delete, swap) has a sane range.
  const lastLine = events.length ? events[events.length - 1].line : 1;
  while (stack.length > 1) {
    stack[stack.length - 1].endLine = lastLine;
    stack.pop();
  }

  return root.children;
}

function pushBlock(stack, block) {
  stack[stack.length - 1].children.push(block);
  stack.push(block);
}

function closeMostRecent(stack, predicate, line) {
  for (let i = stack.length - 1; i > 0; i -= 1) {
    if (!predicate(stack[i])) continue;
    stack[i].endLine = line;
    stack.length = i;
    return;
  }
}

/* -------------------------------------------------------------------- */
/* Source mutations — used by the outline's Add / Up / Down / Delete /  */
/* drag-reorder. All operate on the raw source string + a parsed block, */
/* never on the tokenized events.                                       */
/* -------------------------------------------------------------------- */

export function insertSection(source) {
  const id = `section-${Date.now().toString(36)}`;
  const snippet = [
    `{# fp:block id="${id}" type="section" label="New section" #}`,
    '<section class="section">',
    '  <div class="container">',
    '    <h2>New section</h2>',
    '    <p>Section content</p>',
    '  </div>',
    '</section>',
    '{# /fp:block #}',
  ];
  const lines = String(source || '').trimEnd().split('\n');

  // Prefer to land inside `{% block content %}` ... `{% endblock %}` (the
  // template-inheritance pattern). Fall back to just above the footer
  // partial. If neither exists, append at the end.
  const blockEnd = lines.findIndex((line) => /\{%\s*endblock\b/.test(line));
  if (blockEnd >= 0) {
    lines.splice(blockEnd, 0, ...snippet);
    return `${lines.join('\n')}\n`;
  }
  const footerIndex = lines.findIndex((line) => /partial\(['"]footer['"]/.test(line));
  if (footerIndex >= 0) {
    lines.splice(footerIndex, 0, '', ...snippet, '');
    return `${lines.join('\n')}\n`;
  }
  return `${lines.join('\n')}\n\n${snippet.join('\n')}\n`;
}

export function deleteBlock(source, block) {
  if (!block?.endLine) return source;
  const lines = String(source || '').split('\n');
  lines.splice(block.startLine - 1, block.endLine - block.startLine + 1);
  return lines.join('\n');
}

export function moveMarkedBlock(source, block, direction, blocks) {
  const peers = flattenBlocks(blocks)
    .filter((item) => item.source === 'marker' && item.depth === 0)
    .sort((a, b) => a.startLine - b.startLine);
  const index = peers.findIndex((item) => item.id === block?.id);
  const target = peers[index + direction];
  if (!target) return source;
  return direction < 0
    ? swapLineRanges(source, target, block)
    : swapLineRanges(source, block, target);
}

export function reorderMarkedBlock(source, fromId, toId, blocks) {
  if (!fromId || fromId === toId) return source;
  const peers = flattenBlocks(blocks)
    .filter((item) => item.source === 'marker' && item.depth === 0);
  const from = peers.find((item) => item.id === fromId);
  const to = peers.find((item) => item.id === toId);
  if (!from || !to) return source;

  const lines = String(source || '').split('\n');
  const segment = lines.slice(from.startLine - 1, from.endLine);
  lines.splice(from.startLine - 1, segment.length);
  const insertAt = from.startLine < to.startLine
    ? to.startLine - segment.length - 1
    : to.startLine - 1;
  lines.splice(insertAt, 0, ...segment);
  return lines.join('\n');
}

/* -------------------------------------------------------------------- */
/* Small helpers                                                        */
/* -------------------------------------------------------------------- */

function parseAttrs(text) {
  const attrs = {};
  const re = /([a-zA-Z0-9_-]+)=("([^"]*)"|'([^']*)'|([^\s]+))/g;
  let match = re.exec(text);
  while (match) {
    attrs[match[1]] = match[3] || match[4] || match[5] || '';
    match = re.exec(text);
  }
  return attrs;
}

function countNewlines(src, from, to) {
  let n = 0;
  for (let k = from; k < to; k += 1) if (src[k] === '\n') n += 1;
  return n;
}

function cleanInline(text) {
  return String(text || '').replace(/\s+/g, ' ').trim();
}

function swapLineRanges(source, first, second) {
  const lines = String(source || '').split('\n');
  const aStart = first.startLine - 1;
  const aEnd = first.endLine;
  const bStart = second.startLine - 1;
  const bEnd = second.endLine;
  return [
    ...lines.slice(0, aStart),
    ...lines.slice(bStart, bEnd),
    ...lines.slice(aEnd, bStart),
    ...lines.slice(aStart, aEnd),
    ...lines.slice(bEnd),
  ].join('\n');
}
