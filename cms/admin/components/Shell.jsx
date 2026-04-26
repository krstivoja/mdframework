import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar.jsx';

// Two-column layout — sidebar (240px, full height, logo + nav + footer) and
// scrollable content. No top admin-bar in this iteration.
export default function Shell() {
  return (
    <div className="flex min-h-screen bg-zinc-50 text-zinc-900 antialiased">
      <Sidebar />
      <main className="min-w-0 flex-1 overflow-y-auto p-8">
        <div className="mx-auto max-w-5xl">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
