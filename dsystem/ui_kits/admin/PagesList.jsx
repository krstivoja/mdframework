/* global React, Icon, SearchIcon, POST_TYPES */
// Pages list — modeled on cms/templates/pages.php.
// Renders a client-filterable table of content with search, type filter,
// status filter, and a "Rebuild cache" action.

const { useState, useMemo } = React;

const SEED_PAGES = [
  { title: "Hello World",           path: "blog/hello-world",              folder: "blog",  draft: false },
  { title: "On Ultralight PHP",     path: "blog/on-ultralight-php",        folder: "blog",  draft: true  },
  { title: "Shipping v1",           path: "blog/shipping-v1",              folder: "blog",  draft: false },
  { title: "Welcome",               path: "pages/index",                   folder: "pages", draft: false },
  { title: "About",                 path: "pages/about",                   folder: "pages", draft: false },
  { title: "A markdown primer",     path: "blog/markdown-primer",          folder: "blog",  draft: false },
  { title: "Release notes — 1.0.0", path: "blog/release-notes-1-0-0",      folder: "blog",  draft: false },
  { title: "Caching deep-dive",     path: "blog/caching-deep-dive",        folder: "blog",  draft: true  },
];

function PagesList({ folder, onEdit, onDelete, onRebuildCache, onNew }) {
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState(folder || "");
  const [statusFilter, setStatusFilter] = useState("");

  // Keep typeFilter aligned with the sidebar-selected folder.
  React.useEffect(() => { setTypeFilter(folder || ""); }, [folder]);

  const filtered = useMemo(() => {
    return SEED_PAGES.filter(p => {
      if (typeFilter && p.folder !== typeFilter) return false;
      if (statusFilter === "published" && p.draft) return false;
      if (statusFilter === "draft" && !p.draft) return false;
      if (search) {
        const q = search.toLowerCase();
        if (!p.title.toLowerCase().includes(q) && !p.path.toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }, [search, typeFilter, statusFilter]);

  const heading = folder ? folder[0].toUpperCase() + folder.slice(1) : "All Content";

  return (
    <div className="admin-card">
      <div className="list-header">
        <h1>
          {heading}
          <span className="page-count">{filtered.length}</span>
        </h1>
        <div className="list-controls">
          <div className="search-wrap">
            <SearchIcon />
            <input type="search"
                   className="form-input search-input"
                   placeholder="Search…"
                   value={search}
                   onChange={e => setSearch(e.target.value)} />
          </div>
          {!folder && (
            <select className="form-input type-select"
                    value={typeFilter}
                    onChange={e => setTypeFilter(e.target.value)}>
              <option value="">All types</option>
              {POST_TYPES.map(t => <option key={t} value={t}>{t[0].toUpperCase() + t.slice(1)}</option>)}
            </select>
          )}
          <select className="form-input type-select"
                  value={statusFilter}
                  onChange={e => setStatusFilter(e.target.value)}>
            <option value="">All statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </select>
          <button className="btn btn-secondary" onClick={onRebuildCache}>Rebuild cache</button>
          <button className="btn btn-primary" onClick={onNew}>New page</button>
        </div>
      </div>

      {filtered.length === 0 ? (
        <p className="text-muted">No results.</p>
      ) : (
        <table className="pages-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Path</th>
              {!folder && <th>Type</th>}
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(p => (
              <tr key={p.path}>
                <td>
                  <strong>{p.title}</strong>
                  {p.draft && <span className="badge badge-draft">Draft</span>}
                </td>
                <td className="col-path">{p.path}</td>
                {!folder && <td className="col-folder">{p.folder}</td>}
                <td className="col-actions">
                  <button className="btn btn-secondary btn-sm" onClick={() => onEdit(p)}>Edit</button>
                  {" "}
                  <button className="btn btn-danger btn-sm"
                          onClick={() => {
                            if (confirm(`Delete "${p.title}"?`)) onDelete(p);
                          }}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

Object.assign(window, { PagesList, SEED_PAGES });
