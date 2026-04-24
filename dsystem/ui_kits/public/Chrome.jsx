/* global React */
// Public-theme primitives: SiteHeader, SiteFooter, AdminFrontBar.
// Matches site/themes/default/templates/_header.php + _footer.php.

function SiteHeader({ route, onNavigate }) {
  const link = (r, label) => (
    <a className={route === r ? "is-active" : ""} onClick={() => onNavigate(r)}>{label}</a>
  );
  return (
    <nav>
      <span className="site-name">My Markdown Site</span>
      {link("home", "Home")}
      {link("blog", "Blog")}
      {link("about", "About")}
    </nav>
  );
}

function SiteFooter() {
  return (
    <footer className="site-footer">
      <span>© 2026 My Markdown Site</span>
      <span>
        <a href="#">Feed</a> · <a href="#">Sitemap</a>
      </span>
    </footer>
  );
}

function AdminFrontBar({ onEdit, onDashboard }) {
  return (
    <div className="admin-front-bar">
      <a onClick={onEdit}>Edit page</a>
      <a onClick={onDashboard}>Dashboard</a>
    </div>
  );
}

Object.assign(window, { SiteHeader, SiteFooter, AdminFrontBar });
