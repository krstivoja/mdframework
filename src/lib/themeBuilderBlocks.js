import { tokenizeThemeSource } from './themeBuilderTokenizer.js';

// Tags the outline surfaces as selectable blocks. Curated, not exhaustive
// — `<span>` and most inline-only elements would drown the structure view
// in noise. If you're missing a tag here, the rule is "is it something a
// theme author would want to point at in the outline?" If yes, add it.
//
// Mirrored in `bootstrap.php`'s `inject_preview_script` so the preview's
// click-to-select bridge resolves the same set of tags. Keep them in sync.
const VISUAL_TAGS = new Set([
  // Sectioning + layout
  'article', 'aside', 'div', 'footer', 'form', 'header', 'li', 'main',
  'nav', 'ol', 'section', 'ul',
  // Headings
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
  // Text content
  'p', 'blockquote', 'pre',
  // Media + embeds
  'a', 'button', 'img', 'figure', 'figcaption', 'video', 'audio', 'iframe',
  // Tables
  'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
  // Misc
  'hr',
]);

export function parseThemeBlocks(source) {
  const tokens = tokenizeThemeSource(String(source || ''));
  return buildTree(tokens);
}

export function flattenBlocks(blocks, depth = 0, parentId = null) {
  return blocks.flatMap((block) => [
    { ...block, depth, parentId, children: undefined },
    ...flattenBlocks(block.children || [], depth + 1, block.id),
  ]);
}

/**
 * Move a block in the outline relative to a target block. Supports
 * cross-parent moves: `position: 'before' | 'after' | 'inside'`
 * specifies where to drop the moved chunk relative to `toId`.
 *
 * Re-indents the moved chunk to match the target context. The chunk's
 * current leading indent (the minimum indent across its non-empty lines)
 * is stripped, then a new indent matching the target row's indent (plus
 * one extra step for `'inside'`) is added.
 *
 * Returns the new source string. No-ops (returns the original) when:
 *   - either block can't be located,
 *   - `toId === fromId`,
 *   - dropping a block inside one of its own descendants (would tangle
 *     the tree).
 */
export function moveBlock(source, fromId, toId, position, blocks) {
  if (!fromId || !toId || fromId === toId) return source;
  if (!['before', 'after', 'inside'].includes(position)) return source;

  const flat = flattenBlocks(blocks);
  const from = flat.find((b) => b.id === fromId);
  const to   = flat.find((b) => b.id === toId);
  if (!from || !to) return source;
  if (!from.startLine || !from.endLine || !to.startLine || !to.endLine) return source;

  // Refuse "drop a parent inside its own descendant" — that would orphan
  // the tree. Walk down from `from` and bail if we find `to`.
  if (isDescendant(blocks, fromId, toId)) return source;

  const lines = String(source || '').split('\n');
  const chunk = lines.slice(from.startLine - 1, from.endLine);

  // Strip the chunk's current leading indent so we can re-indent fresh.
  const currentIndent = minLeadingWhitespace(chunk);
  const stripped = chunk.map((l) => (l.length >= currentIndent ? l.slice(currentIndent) : l));

  // Target indent: the indent of the target's first line, plus one
  // extra step for `'inside'`. We don't know the project's tab size; the
  // codebase's existing snippets all use 2-space indent, so we match.
  const targetLine = lines[to.startLine - 1] || '';
  const baseIndent = targetLine.match(/^\s*/)?.[0] || '';
  const indent = position === 'inside' ? baseIndent + '  ' : baseIndent;
  const reindented = stripped.map((l) => (l.length ? indent + l : l));

  // Remove the chunk first so subsequent line numbers line up. Then
  // compute where to splice it back in. `to.startLine` / `to.endLine`
  // need adjusting when the removed chunk was above them.
  lines.splice(from.startLine - 1, chunk.length);
  let toStart = to.startLine;
  let toEnd   = to.endLine;
  if (from.startLine < to.startLine) {
    toStart -= chunk.length;
    toEnd   -= chunk.length;
  }

  let insertAt;
  if (position === 'before') {
    insertAt = toStart - 1;
  } else if (position === 'after') {
    insertAt = toEnd;
  } else {
    // 'inside' — after the target's opening line. This puts the moved
    // chunk as the first child of the target. Good enough for the
    // common case ("drop into an empty container"); the user can
    // re-drag among siblings to fine-tune ordering.
    insertAt = toStart;
  }
  lines.splice(insertAt, 0, ...reindented);
  return lines.join('\n');
}

function isDescendant(blocks, ancestorId, candidateId) {
  function walk(items, foundAncestor) {
    for (const b of items) {
      const here = foundAncestor || b.id === ancestorId;
      if (here && b.id === candidateId && b.id !== ancestorId) return true;
      if (b.children && walk(b.children, here)) return true;
    }
    return false;
  }
  return walk(blocks, false);
}

function minLeadingWhitespace(lines) {
  let min = Infinity;
  for (const line of lines) {
    if (!line.trim()) continue;
    const m = line.match(/^(\s*)/);
    const n = m ? m[1].length : 0;
    if (n < min) min = n;
  }
  return min === Infinity ? 0 : min;
}

export function findBlock(blocks, id) {
  for (const block of blocks) {
    if (block.id === id) return block;
    const child = findBlock(block.children || [], id);
    if (child) return child;
  }
  return null;
}

/**
 * Depth-first walk to find the Nth `element` block with a given tag.
 * Used by the preview-click bridge: the iframe sends `{path, tag, occurrence}`
 * for what was clicked; we resolve that to a source block here.
 */
export function findElementByTag(blocks, tag, occurrence) {
  if (!tag || occurrence < 0) return null;
  let n = 0;
  function walk(items) {
    for (const b of items) {
      if (b.source === 'html' && b.tag === tag) {
        if (n === occurrence) return b;
        n += 1;
      }
      if (Array.isArray(b.children) && b.children.length) {
        const found = walk(b.children);
        if (found) return found;
      }
    }
    return null;
  }
  return walk(blocks);
}

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

