/* global React */
// Editor — modeled on cms/templates/edit.php + fields.php.
// A two-column form: Markdown body on the left, a "fields" sidebar on the right
// with title, path, draft, tags, date, and custom fields.

const { useState } = React;

function Editor({ page, onSave, onPublish, onCancel, onViewLive }) {
  const [form, setForm] = useState({
    title: page?.title ?? "New page",
    path:  page?.path  ?? "blog/new-post",
    folder: page?.folder ?? "blog",
    draft: page?.draft ?? true,
    tags: page?.tags ?? ["design", "php"],
    date: page?.date ?? "2026-04-22",
    body: page?.body ?? DEFAULT_BODY,
  });

  const update = (k, v) => setForm(f => ({ ...f, [k]: v }));

  return (
    <div className="admin-card">
      <div className="editor-header">
        <h1 className="editor-title">
          {form.title || "Untitled"}
          {form.draft && <span className="badge badge-draft">Draft</span>}
        </h1>
        <div className="editor-actions">
          <button className="btn btn-ghost" onClick={onCancel}>Cancel</button>
          <button className="btn btn-secondary" onClick={onViewLive}>View →</button>
          <button className="btn btn-secondary" onClick={() => onSave(form)}>Save draft</button>
          <button className="btn btn-primary" onClick={() => onPublish({ ...form, draft: false })}>
            {form.draft ? "Publish" : "Update"}
          </button>
        </div>
      </div>

      <div className="editor-grid">
        <div>
          <div className="form-group">
            <label className="form-label">Title</label>
            <input className="form-input" style={{ width: "100%" }}
                   value={form.title} onChange={e => update("title", e.target.value)} />
          </div>
          <div className="form-group">
            <label className="form-label">
              Content <span style={{ color: "var(--text-muted)", fontWeight: 400, fontSize: 12 }}>(Markdown)</span>
            </label>
            <textarea className="form-input" style={{ width: "100%" }}
                      value={form.body}
                      onChange={e => update("body", e.target.value)} />
          </div>
        </div>

        <aside className="field-sidebar">
          <div className="sidebar-heading">Fields</div>

          <div className="form-group">
            <label className="form-label">Path <span className="field-chip">path</span></label>
            <input className="form-input" style={{ width: "100%", fontFamily: "ui-monospace, Menlo, monospace", fontSize: 12 }}
                   value={form.path} onChange={e => update("path", e.target.value)} />
            <p className="form-hint">Lowercase, hyphens, slashes. Controls the URL.</p>
          </div>

          <div className="form-group">
            <label className="form-label">Date <span className="field-chip">date</span></label>
            <input type="date" className="form-input" style={{ width: "100%" }}
                   value={form.date} onChange={e => update("date", e.target.value)} />
          </div>

          <div className="form-group">
            <label className="form-label">Tags <span className="field-chip">tags</span></label>
            <input className="form-input" style={{ width: "100%" }}
                   value={form.tags.join(", ")}
                   onChange={e => update("tags", e.target.value.split(",").map(s => s.trim()).filter(Boolean))}
                   placeholder="comma, separated" />
          </div>

          <div className="form-group">
            <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 13, fontWeight: 500 }}>
              <input type="checkbox" checked={form.draft}
                     onChange={e => update("draft", e.target.checked)} />
              Draft
            </label>
            <p className="form-hint" style={{ marginLeft: 24 }}>Drafts are excluded from the public site.</p>
          </div>
        </aside>
      </div>
    </div>
  );
}

const DEFAULT_BODY = `# Hello World

This is the homepage. It's a static markdown file at \`content/pages/index.md\`.

Visit [the blog](/blog) for recent posts.

## What this is

MD Framework is an ultralight flat-file CMS. No database — content lives in Markdown files on disk.

- Edit here
- Preview from the sidebar
- Ship when ready
`;

Object.assign(window, { Editor });
