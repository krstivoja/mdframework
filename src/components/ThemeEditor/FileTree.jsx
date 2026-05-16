import { useMemo, useState } from 'react';

// Theme file tree. Receives a flat list of `{ path, type, size, mtime }`
// from /admin/api/theme/tree and renders it as nested foldable nodes.
//
// Selection is single-file; toggling a folder is local UI state. The
// `dirty` Set tells us which paths have unsaved changes — those get a
// small dot next to the name.
export default function FileTree({ entries, currentPath, dirty, onSelect }) {
  const tree = useMemo(() => buildTree(entries || []), [entries]);
  const [open, setOpen] = useState(() => initiallyOpen(entries || []));

  function toggle(path) {
    setOpen((prev) => {
      const next = new Set(prev);
      next.has(path) ? next.delete(path) : next.add(path);
      return next;
    });
  }

  return (
    <div className="flex h-full min-w-0 flex-col overflow-y-auto border-r border-zinc-200 bg-white">
      <header className="border-b border-zinc-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500">
        Active theme
      </header>
      <ul role="tree" className="flex-1 py-1 text-[13px]">
        {tree.map((node) => (
          <TreeNode
            key={node.path}
            node={node}
            depth={0}
            open={open}
            currentPath={currentPath}
            dirty={dirty}
            onToggle={toggle}
            onSelect={onSelect}
          />
        ))}
      </ul>
    </div>
  );
}

function TreeNode({ node, depth, open, currentPath, dirty, onToggle, onSelect }) {
  const isOpen   = open.has(node.path);
  const isCurr   = node.type === 'file' && node.path === currentPath;
  const isDirty  = dirty?.has(node.path);
  const padding  = { paddingLeft: 8 + depth * 12 };

  if (node.type === 'dir') {
    return (
      <li role="treeitem" aria-expanded={isOpen}>
        <button
          type="button"
          onClick={() => onToggle(node.path)}
          className="flex w-full items-center gap-1 py-1 pr-2 text-left text-zinc-700 hover:bg-zinc-50"
          style={padding}
        >
          <span aria-hidden="true" className="inline-block w-3 text-zinc-400">{isOpen ? '▾' : '▸'}</span>
          <span className="truncate font-medium">{node.name}/</span>
        </button>
        {isOpen && (
          <ul role="group">
            {node.children.map((child) => (
              <TreeNode
                key={child.path}
                node={child}
                depth={depth + 1}
                open={open}
                currentPath={currentPath}
                dirty={dirty}
                onToggle={onToggle}
                onSelect={onSelect}
              />
            ))}
          </ul>
        )}
      </li>
    );
  }

  return (
    <li role="treeitem">
      <button
        type="button"
        onClick={() => onSelect(node.path)}
        className={`flex w-full items-center gap-2 py-1 pr-2 text-left font-mono text-[12px] ${
          isCurr ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-50'
        }`}
        style={padding}
        aria-current={isCurr ? 'true' : undefined}
      >
        <span className="truncate">{node.name}</span>
        {isDirty && (
          <span
            aria-label="Unsaved changes"
            className={`inline-block h-1.5 w-1.5 rounded-full ${isCurr ? 'bg-amber-300' : 'bg-amber-500'}`}
          />
        )}
      </button>
    </li>
  );
}

function buildTree(entries) {
  // Build a nested {name, path, type, children?[]} structure from the flat list.
  const root = new Map();
  for (const e of entries) {
    const parts = e.path.split('/');
    let bucket = root;
    let acc = '';
    for (let i = 0; i < parts.length; i++) {
      const name = parts[i];
      acc = acc ? acc + '/' + name : name;
      const isLeaf = i === parts.length - 1;
      let node = bucket.get(name);
      if (!node) {
        node = {
          name,
          path: acc,
          type: isLeaf ? e.type : 'dir',
          children: new Map(),
        };
        bucket.set(name, node);
      }
      bucket = node.children;
    }
  }
  return normalize(root);
}

function normalize(map) {
  const arr = Array.from(map.values()).map((n) => ({
    ...n,
    children: n.type === 'dir' ? normalize(n.children) : undefined,
  }));
  // dirs first, then files, alphabetised within each group.
  arr.sort((a, b) => {
    if (a.type !== b.type) return a.type === 'dir' ? -1 : 1;
    return a.name.localeCompare(b.name);
  });
  return arr;
}

function initiallyOpen(entries) {
  // Auto-expand the two top-level roots so the user lands on something
  // useful instead of two closed folders.
  const out = new Set();
  for (const e of entries) {
    if (e.type === 'dir' && !e.path.includes('/')) out.add(e.path);
  }
  return out;
}
