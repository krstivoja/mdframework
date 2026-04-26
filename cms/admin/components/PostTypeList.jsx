import { useMemo, useState } from 'react';
import { NavLink, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { cap, encodePath } from '../lib/utils.js';
import { useDirty } from '../lib/dirty.jsx';
import { baseControlCls } from './ui/Input.jsx';
import { IconSearch } from './icons.jsx';

// Sibling-list column rendered between Sidebar and the editor when the active
// route is under a post-type folder. Title-only rows, search filter, dirty-
// state guard on navigation.
export default function PostTypeList() {
  const { folder } = useParams();
  const navigate = useNavigate();
  const { confirmLeave } = useDirty();
  const [query, setQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['pages'],
    queryFn: () => api.get('/pages'),
  });

  const items = useMemo(() => {
    const all = (data?.pages || []).filter(p => (p.folder || '') === folder);
    if (!query.trim()) return all;
    const q = query.toLowerCase();
    return all.filter(p => (p.title || p.path || '').toLowerCase().includes(q));
  }, [data, folder, query]);

  const guard = (e, to) => {
    if (!confirmLeave()) {
      e.preventDefault();
      return;
    }
    if (to) navigate(to);
  };

  return (
    <aside className="flex w-72 shrink-0 flex-col border-r border-zinc-200 bg-white">
      <div className="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3">
        <h2 className="text-[13px] font-semibold tracking-tight text-zinc-900">{cap(folder)}</h2>
        <button
          type="button"
          onClick={() => { if (confirmLeave()) navigate(`/new/${encodeURIComponent(folder)}`); }}
          className="inline-flex h-7 items-center justify-center rounded-md border border-zinc-200 bg-white px-2.5 text-[12px] font-medium text-zinc-700 transition-colors hover:bg-zinc-100"
        >
          New
        </button>
      </div>

      <div className="border-b border-zinc-100 px-3 py-2">
        <div className="relative">
          <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400">
            {IconSearch}
          </span>
          <input
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Search…"
            className={`${baseControlCls} pl-9`}
          />
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto p-2">
        {isLoading && <div className="px-3 py-2 text-[12px] text-zinc-500">Loading…</div>}
        {!isLoading && items.length === 0 && (
          <div className="px-3 py-2 text-[12px] text-zinc-500">No items.</div>
        )}
        {items.map(p => {
          const slug = p.path.startsWith(folder + '/') ? p.path.slice(folder.length + 1) : p.path;
          const to = `/${encodeURIComponent(folder)}/${encodePath(slug)}`;
          return (
            <NavLink
              key={p.path}
              to={to}
              onClick={(e) => guard(e)}
              className={({ isActive }) =>
                `block truncate rounded-md px-3 py-2 text-[13px] transition-colors ${
                  isActive
                    ? 'bg-zinc-900 text-white'
                    : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900'
                }`
              }
            >
              {p.title || '(untitled)'}
            </NavLink>
          );
        })}
      </nav>
    </aside>
  );
}
