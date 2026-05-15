import { memo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Badge, Button } from './ui/index.js';

// One row in <PagesList>. Memo'd so toggling one checkbox doesn't re-render
// every other row in a large content set.
const PageRow = memo(function PageRow({ page, showStatus, selected, onToggle, onEdit, onDelete }) {
  const handleEdit = useCallback(() => onEdit(`/${page.path}`), [onEdit, page.path]);
  const handleDelete = useCallback(() => onDelete(page), [onDelete, page]);
  const handleToggle = useCallback((e) => onToggle(page.path, e.target.checked), [onToggle, page.path]);
  return (
    <tr className="border-b border-zinc-100 last:border-b-0 hover:bg-zinc-50">
      <td className="px-6 py-4">
        <input
          type="checkbox"
          checked={selected}
          onChange={handleToggle}
          aria-label={`Select ${page.title || page.path}`}
          className="h-4 w-4 cursor-pointer rounded border-zinc-300"
        />
      </td>
      <td className="px-6 py-4">
        <Link to={`/${page.path}`} className="font-semibold text-zinc-900 hover:underline">
          {page.title || '(untitled)'}
        </Link>
      </td>
      <td className="px-6 py-4 font-mono text-[12px] text-zinc-500">{page.path}</td>
      {showStatus ? (
        <td className="px-6 py-4">
          <Badge tone={page.draft ? 'draft' : 'live'}>{page.draft ? 'Draft' : 'Live'}</Badge>
        </td>
      ) : (
        <td className="px-6 py-4 text-zinc-500">{page.folder || '—'}</td>
      )}
      <td className="px-6 py-4">
        <div className="flex justify-end gap-2">
          <Button variant="secondary" size="sm" onClick={handleEdit}>Edit</Button>
          <Button variant="danger" size="sm" onClick={handleDelete}>Delete</Button>
        </div>
      </td>
    </tr>
  );
});

export default PageRow;
