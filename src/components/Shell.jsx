import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar.jsx';

// Outer chrome: sidebar (240px) + the active layout. Padded content for
// "regular" screens (PagesList/Media/Settings/Backup) is provided by
// `<PaddedOutlet>`; layouts that need full-bleed (PostTypeShell's 3-col
// editor view) render their own `<Outlet />` directly.
export default function Shell() {
  return (
    // `h-screen` (not `min-h-screen`) gives the flex column a definite
    // height — required for the page-editor surface to actually fill the
    // viewport via `flex-1 min-h-0`. Internal scrolling is owned by
    // `<PaddedOutlet>` and the editor's own panes.
    <div className="flex h-screen overflow-hidden bg-zinc-50 text-zinc-900 antialiased">
      <Sidebar />
      <Outlet />
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
