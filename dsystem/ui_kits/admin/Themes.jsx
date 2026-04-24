/* global React */
// Themes screen — modeled on cms/templates/themes.php.
// Grid of theme cards with an active ring, preview placeholder wireframe,
// and an "Activate" / "Active" affordance.

const { useState } = React;

const SEED_THEMES = [
  { slug: "default",    name: "Default",    version: "1.0.0", description: "Clean minimal typographic theme. Warm cream, purple link.", active: true },
  { slug: "mono",       name: "Mono",       version: "0.9.0", description: "Black on white, serif headings, letter-spaced nav." },
  { slug: "magazine",   name: "Magazine",   version: "0.3.2", description: "Two-column layout with generous lead and pull quotes." },
];

function ThemePreview() {
  return (
    <svg viewBox="0 0 100 75" fill="none" stroke="currentColor" strokeWidth="1" opacity="0.45" style={{ width: "50%" }}>
      <rect x="4"  y="4"  width="92" height="67" rx="2" />
      <line x1="4" y1="14" x2="96" y2="14" />
      <rect x="10" y="20" width="40" height="3" rx="1" />
      <rect x="10" y="26" width="72" height="2" rx="1" />
      <rect x="10" y="30" width="58" height="2" rx="1" />
      <rect x="10" y="34" width="66" height="2" rx="1" />
      <rect x="10" y="44" width="80" height="14" rx="1" />
      <rect x="10" y="62" width="20" height="3" rx="1" />
    </svg>
  );
}

function ThemesGrid({ onToast }) {
  const [themes, setThemes] = useState(SEED_THEMES);

  const activate = (slug) => {
    setThemes(ts => ts.map(t => ({ ...t, active: t.slug === slug })));
    onToast && onToast("Theme activated ✓", "success");
  };

  return (
    <div className="admin-card">
      <div className="list-header">
        <h1>Themes <span className="page-count">{themes.length}</span></h1>
        <div className="list-controls">
          <button className="btn btn-secondary">Install theme</button>
        </div>
      </div>

      <div className="theme-grid">
        {themes.map(t => (
          <div key={t.slug} className={`theme-card${t.active ? " is-active" : ""}`}>
            <div className="theme-preview"><ThemePreview /></div>
            <div className="theme-meta">
              <div className="theme-name-row">
                <span className="theme-name">{t.name}</span>
                <span className="theme-version">v{t.version}</span>
                {t.active && <span className="theme-badge">Active</span>}
              </div>
              <p className="theme-desc">{t.description}</p>
              <div style={{ marginTop: "var(--space-3)" }}>
                {t.active ? (
                  <button className="btn btn-secondary btn-sm" disabled>Active</button>
                ) : (
                  <button className="btn btn-primary btn-sm" onClick={() => activate(t.slug)}>Activate</button>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { ThemesGrid, SEED_THEMES });
