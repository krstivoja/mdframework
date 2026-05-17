const VOID_TAGS = new Set([
  'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
  'meta', 'param', 'source', 'track', 'wbr',
]);

export function tokenizeThemeSource(src) {
  const events = [];
  const len = src.length;
  let i = 0;
  let line = 1;

  while (i < len) {
    const ch = src[i];
    if (ch === '\n') { line += 1; i += 1; continue; }

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

    if (ch === '{' && src[i + 1] === '{') {
      const end = src.indexOf('}}', i + 2);
      if (end === -1) break;
      line += countNewlines(src, i, end + 2);
      i = end + 2;
      continue;
    }

    if (ch === '<' && src.startsWith('!--', i + 1)) {
      const end = src.indexOf('-->', i + 4);
      if (end === -1) break;
      line += countNewlines(src, i, end + 3);
      i = end + 3;
      continue;
    }

    if (ch === '<') {
      const tagMatch = /^<\/?([a-zA-Z][a-zA-Z0-9:-]*)/.exec(src.slice(i));
      if (tagMatch) {
        const isClose = src[i + 1] === '/';
        const tag = tagMatch[1].toLowerCase();
        const tagStartLine = line;
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
        if (j >= len) break;
        const selfClosed = src[j - 1] === '/' || VOID_TAGS.has(tag);
        events.push({ kind: isClose ? 'html-close' : 'html-open', tag, line: tagStartLine, selfClosed });
        if (!isClose && selfClosed) {
          events.push({ kind: 'html-close', tag, line: tagStartLine });
        }
        i = j + 1;
        continue;
      }
    }

    i += 1;
  }

  return events;
}

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
