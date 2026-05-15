import { Link, Outlet } from 'react-router-dom';
import { useAuth } from '../lib/auth.jsx';
import Sidebar from './Sidebar.jsx';

// Outer chrome: optional top banner + the (Sidebar | content) row.
// `<PostTypeShell />` renders as a fragment (PostTypeList + Outlet) — those
// must remain direct children of the same flex *row* alongside <Sidebar />,
// so the banner is lifted into a sibling column above the row rather than
// wrapping the outlet. Padded "regular" screens get their wrapping from
// `<PaddedOutlet>`; the editor renders its own full-bleed layout.
export default function Shell() {
  const { passwordIsDefault } = useAuth();
  return (
    <div className="flex h-screen flex-col overflow-hidden bg-zinc-50 text-zinc-900 antialiased">
      {passwordIsDefault && <DefaultPasswordBanner />}
      {/* The row must keep its definite height so the page-editor surface
          can use `flex-1 min-h-0` to fill the viewport; `min-h-0` here lets
          the row shrink to whatever the banner left behind. */}
      <div className="flex min-h-0 flex-1">
        <Sidebar />
        <Outlet />
      </div>
    </div>
  );
}

export function PaddedOutlet() {
  return (
    <main className="min-w-0 flex-1 overflow-y-auto p-8">
      <div className="mx-auto max-w-5xl">
        <Outlet />
      </div>
    </main>
  );
}

// Persistent banner — does not auto-dismiss. Disappears the instant the
// password is rotated (auth refreshes after the change-password mutation).
// Tone is checklist-item, not alarm: "finish setup" rather than "insecure".
function DefaultPasswordBanner() {
  return (
    <div
      role="status"
      className="flex items-center justify-between gap-4 border-b border-amber-200 bg-amber-50 px-6 py-2.5 text-sm text-amber-900"
    >
      <span>
        Set a strong admin password to finish setup.
      </span>
      <Link
        to="/settings/security"
        className="font-medium underline decoration-amber-400 underline-offset-2 hover:decoration-amber-700"
      >
        Open Security settings
      </Link>
    </div>
  );
}
