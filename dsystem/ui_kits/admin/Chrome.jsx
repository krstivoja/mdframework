/* global React */
// Shared admin primitives: logo, icons, sidebar, admin bar, buttons/badges used across screens.
// Exports to window.* so the index.html page can compose screens.

const { useState, useRef, useEffect } = React;

// ── Icon component ──────────────────────────────────────────────────────────
// Uses the SVG sprite in assets/icons.svg. `id` is the symbol id without the
// `icon-` prefix ("all-content", "folder", "plus", etc.).
function Icon({ id, size = 16, className = "sidebar-icon", ...rest }) {
  return (
    <svg className={className} width={size} height={size} viewBox="0 0 16 16" {...rest}>
      <use href={`../../assets/icons.svg#icon-${id}`} />
    </svg>
  );
}

// The "New" icon is the sparkle/plus icon but reusable.
function PlusIcon({ size = 16 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
      <path d="M8 3v10M3 8h10" />
    </svg>
  );
}

function SearchIcon() {
  return (
    <svg className="search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
      <circle cx="6.5" cy="6.5" r="4" /><path d="M11 11l3 3" />
    </svg>
  );
}

// ── Admin bar (top) ─────────────────────────────────────────────────────────
function AdminBar({ pageTitle }) {
  return (
    <nav className="admin-bar">
      <span className="admin-bar-logo">
        <span className="admin-bar-logo-mark">M</span>
        MD Admin
      </span>
      <span className="spacer" />
      <span className="admin-bar-page">{pageTitle}</span>
    </nav>
  );
}

// ── Sidebar nav ─────────────────────────────────────────────────────────────
const POST_TYPES = ["blog", "pages"];

function Sidebar({ active, activeFolder, onNavigate, user = "admin" }) {
  const isActive = (route, folder = null) => {
    if (route === "all") return active === "pages" && !activeFolder;
    if (route === "folder") return active === "pages" && activeFolder === folder;
    return active === route;
  };

  return (
    <aside className="admin-sidebar">
      <nav className="sidebar-nav">
        <div className="sidebar-group">
          <a className={`sidebar-link${isActive("all") ? " is-active" : ""}`}
             onClick={() => onNavigate("pages", null)}>
            <Icon id="all-content" />
            All content
          </a>
          {POST_TYPES.map(t => (
            <a key={t}
               className={`sidebar-link${isActive("folder", t) ? " is-active" : ""}`}
               onClick={() => onNavigate("pages", t)}>
              <Icon id="folder" />
              {t[0].toUpperCase() + t.slice(1)}
            </a>
          ))}
        </div>

        <div className="sidebar-group">
          <div className="sidebar-heading">Create</div>
          <a className={`sidebar-link${isActive("new") ? " is-active" : ""}`}
             onClick={() => onNavigate("new")}>
            <Icon id="plus" />
            New page
          </a>
        </div>

        <div className="sidebar-group">
          <div className="sidebar-heading">Assets</div>
          <a className={`sidebar-link${isActive("media") ? " is-active" : ""}`}
             onClick={() => onNavigate("media")}>
            <Icon id="media" />
            Media library
          </a>
          <a className={`sidebar-link${isActive("themes") ? " is-active" : ""}`}
             onClick={() => onNavigate("themes")}>
            <Icon id="themes" />
            Themes
          </a>
        </div>
      </nav>

      <div className="sidebar-footer">
        <a className={`sidebar-footer-link${isActive("backup") ? " is-active" : ""}`}
           onClick={() => onNavigate("backup")}>
          <Icon id="backup" />
          Backup
        </a>
        <a className={`sidebar-footer-link${isActive("settings") ? " is-active" : ""}`}
           onClick={() => onNavigate("settings")}>
          <Icon id="settings" />
          Settings
        </a>
        <a className="sidebar-footer-link"
           onClick={() => onNavigate("logout")}>
          <Icon id="logout" />
          Log out
        </a>
        <div className="sidebar-user">
          <div className="sidebar-avatar">{user[0].toUpperCase()}</div>
          <div className="sidebar-user-info">
            <div className="sidebar-user-name">{user}</div>
            <div className="sidebar-user-role">Administrator</div>
          </div>
        </div>
      </div>
    </aside>
  );
}

// ── Toast ───────────────────────────────────────────────────────────────────
function Toast({ message, kind = "default", onDone }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!message) return;
    const el = ref.current;
    requestAnimationFrame(() => el && el.classList.add("is-visible"));
    const t = setTimeout(() => {
      el && el.classList.remove("is-visible");
      setTimeout(() => onDone && onDone(), 180);
    }, 2400);
    return () => clearTimeout(t);
  }, [message]);
  if (!message) return null;
  const cls = kind === "success" ? " toast--success" : kind === "error" ? " toast--error" : "";
  return <div ref={ref} className={`toast${cls}`}>{message}</div>;
}

// Export
Object.assign(window, { Icon, PlusIcon, SearchIcon, AdminBar, Sidebar, Toast, POST_TYPES });
