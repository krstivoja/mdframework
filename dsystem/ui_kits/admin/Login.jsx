/* global React */
// Login — modeled on cms/templates/login.php.
// A centered small card with a mark + wordmark, two inputs, and a submit button.
// The submit handler is fake; any non-empty values "succeed".

const { useState } = React;

function Login({ onSubmit }) {
  const [user, setUser] = useState("admin");
  const [pass, setPass] = useState("••••••••");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const submit = (e) => {
    e.preventDefault();
    if (!user.trim() || !pass.trim()) {
      setError("Both fields are required.");
      return;
    }
    setError(null);
    setBusy(true);
    setTimeout(() => { setBusy(false); onSubmit && onSubmit({ user }); }, 450);
  };

  return (
    <div className="login-shell">
      <form className="login-card" onSubmit={submit}>
        <div className="login-brand">
          <span className="admin-bar-logo-mark">M</span>
          <div>
            <h1 className="login-title">Admin Login</h1>
            <p className="login-subtitle">MD Framework — Ultralight flat-file CMS</p>
          </div>
        </div>

        <div className="form-group">
          <label className="form-label">Username</label>
          <input className="form-input" style={{ width: "100%" }}
                 value={user} onChange={e => setUser(e.target.value)} autoFocus />
        </div>

        <div className="form-group">
          <label className="form-label">Password</label>
          <input className="form-input" style={{ width: "100%" }}
                 type="password" value={pass} onChange={e => setPass(e.target.value)} />
        </div>

        {error && (
          <div style={{
            background: "var(--danger-soft-bg)",
            border: "1px solid var(--danger-soft-br)",
            color: "var(--danger)",
            padding: "10px 12px",
            borderRadius: "var(--radius-md)",
            fontSize: 12,
            marginTop: "var(--space-4)",
          }}>{error}</div>
        )}

        <div style={{ marginTop: "var(--space-6)" }}>
          <button className="btn btn-primary btn-lg" style={{ width: "100%" }}
                  type="submit" disabled={busy}>
            {busy ? "Signing in…" : "Sign in"}
          </button>
        </div>

        <p style={{ margin: "var(--space-4) 0 0", fontSize: 12, color: "var(--text-muted)", textAlign: "center" }}>
          Change the password before deploying.
        </p>
      </form>
    </div>
  );
}

Object.assign(window, { Login });
