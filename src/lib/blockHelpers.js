// Pure helpers for the block-builder. The block tree is a list of
// instances; nesting is allowed for blocks the registry marks as
// `hasChildren`. Each instance carries a stable id so React keys and
// inspector lookups don't depend on array position.
//
//   { id, type, data: {...}, children?: [...] }

let _idCounter = 0;
export function makeId() {
  _idCounter += 1;
  return `b-${Date.now().toString(36)}-${_idCounter.toString(36)}`;
}

/** Build a fresh block instance from the registry's definition. */
export function newBlock(def) {
  const data = {};
  for (const f of def.fields || []) {
    if (f.default !== undefined) data[f.name] = f.default;
  }
  return {
    id: makeId(),
    type: def.slug,
    data,
    ...(def.hasChildren ? { children: [] } : {}),
  };
}

/** Walk every block in the tree (depth-first), yielding {block, parent, index}. */
export function* walk(tree, parent = null) {
  for (let i = 0; i < tree.length; i++) {
    const block = tree[i];
    yield { block, parent, index: i };
    if (Array.isArray(block.children)) {
      yield* walk(block.children, block);
    }
  }
}

export function findById(tree, id) {
  for (const node of walk(tree)) {
    if (node.block.id === id) return node;
  }
  return null;
}

export function updateById(tree, id, updater) {
  return tree.map((block) => {
    if (block.id === id) return updater(block);
    if (Array.isArray(block.children)) {
      return { ...block, children: updateById(block.children, id, updater) };
    }
    return block;
  });
}

export function removeById(tree, id) {
  const out = [];
  for (const block of tree) {
    if (block.id === id) continue;
    if (Array.isArray(block.children)) {
      out.push({ ...block, children: removeById(block.children, id) });
    } else {
      out.push(block);
    }
  }
  return out;
}

/**
 * Move a block up or down within its parent. No-op if it's already at
 * the boundary. Stays inside the parent — cross-parent moves would need
 * drag/drop and are out of scope for v1.
 */
export function moveById(tree, id, direction) {
  // Helper that walks a sibling list and returns the moved version if `id`
  // lives at this level; otherwise recurses into children.
  function inSiblings(siblings) {
    const idx = siblings.findIndex((b) => b.id === id);
    if (idx !== -1) {
      const next = [...siblings];
      const swap = direction === 'up' ? idx - 1 : idx + 1;
      if (swap < 0 || swap >= next.length) return siblings;
      [next[idx], next[swap]] = [next[swap], next[idx]];
      return next;
    }
    return siblings.map((b) =>
      Array.isArray(b.children) ? { ...b, children: inSiblings(b.children) } : b,
    );
  }
  return inSiblings(tree);
}

/** Append a new block to either the tree root or a container block's children. */
export function appendBlock(tree, def, parentId = null) {
  const block = newBlock(def);
  if (!parentId) return [...tree, block];
  return updateById(tree, parentId, (parent) => ({
    ...parent,
    children: [...(parent.children || []), block],
  }));
}

/**
 * Is `maybeChildId` anywhere inside `ancestorId`'s subtree? Used to reject
 * drag-drop operations that would move a block into one of its own
 * descendants (which would orphan the subtree). Strict — a block is not
 * its own descendant.
 */
export function isDescendant(tree, ancestorId, maybeChildId) {
  const anc = findById(tree, ancestorId);
  if (!anc) return false;
  for (const node of walk([anc.block])) {
    if (node.block.id === maybeChildId && node.block.id !== ancestorId) return true;
  }
  return false;
}

/**
 * Insert an existing block instance into the tree at a position relative
 * to a target. Position is `'before' | 'after' | 'inside'`. `'inside'`
 * appends to the target's children (caller is responsible for confirming
 * the target accepts children). `targetId === null` appends at root.
 */
function insertExistingAt(tree, block, targetId, position) {
  if (!targetId) return [...tree, block];
  if (position === 'inside') {
    return updateById(tree, targetId, (parent) => ({
      ...parent,
      children: [...(parent.children || []), block],
    }));
  }
  function inSiblings(siblings) {
    const idx = siblings.findIndex((b) => b.id === targetId);
    if (idx !== -1) {
      const out = [...siblings];
      out.splice(position === 'before' ? idx : idx + 1, 0, block);
      return out;
    }
    return siblings.map((b) =>
      Array.isArray(b.children) ? { ...b, children: inSiblings(b.children) } : b,
    );
  }
  return inSiblings(tree);
}

/**
 * Build a fresh block from a registry def and drop it at a target position.
 * Used when the user drags a palette item onto a list-view row.
 */
export function insertBlockAt(tree, def, targetId, position) {
  return insertExistingAt(tree, newBlock(def), targetId, position);
}

/**
 * Move an existing block to a new position in the tree. No-op when the
 * move would be a self-drop or would put the block inside one of its own
 * descendants (which would orphan everything below it).
 */
export function moveToTarget(tree, sourceId, targetId, position) {
  if (sourceId === targetId) return tree;
  if (targetId && isDescendant(tree, sourceId, targetId)) return tree;
  const node = findById(tree, sourceId);
  if (!node) return tree;
  const removed = removeById(tree, sourceId);
  return insertExistingAt(removed, node.block, targetId, position);
}
