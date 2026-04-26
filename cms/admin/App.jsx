import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useAuth } from './lib/auth.jsx';

import Protected from './components/Protected.jsx';
import Shell from './components/Shell.jsx';
import NotFound from './components/NotFound.jsx';

// Eager: critical-path screens. Login is the entry for unauthenticated users;
// PagesList is the landing route once signed in.
import Login from './screens/Login.jsx';
import PagesList from './screens/PagesList.jsx';

// Lazy: each screen pulls in its own deps (SunEditor, Turndown, the taxonomy
// builder, the backup zip flow, etc.). Splitting one chunk per screen keeps
// the initial bundle small and warms the cache as the user clicks around.
const PageEditor   = lazy(() => import('./screens/PageEditor.jsx'));
const Media        = lazy(() => import('./screens/Media.jsx'));
const Backup       = lazy(() => import('./screens/Backup.jsx'));
const Settings     = lazy(() => import('./screens/Settings/index.jsx'));
const SiteSettings = lazy(() => import('./screens/Settings/SiteSettings.jsx'));
const Fields       = lazy(() => import('./screens/Settings/Fields/index.jsx'));
const Themes       = lazy(() => import('./screens/Settings/Themes.jsx'));

export default function App() {
  const { status, user } = useAuth();

  if (status === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
        Loading…
      </div>
    );
  }

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Protected user={user} />}>
        <Route element={<Shell />}>
          <Route path="/"          element={<PagesList />} />
          <Route path="/edit"      element={<Lazy><PageEditor /></Lazy>} />
          <Route path="/edit/*"    element={<Lazy><PageEditor /></Lazy>} />
          <Route path="/media"     element={<Lazy><Media /></Lazy>} />
          <Route path="/backup"    element={<Lazy><Backup /></Lazy>} />
          <Route path="/settings"  element={<Lazy><Settings /></Lazy>}>
            <Route index           element={<Lazy><SiteSettings /></Lazy>} />
            <Route path="fields"   element={<Lazy><Fields /></Lazy>} />
            <Route path="themes"   element={<Lazy><Themes /></Lazy>} />
          </Route>
          <Route path="*" element={<NotFound />} />
        </Route>
      </Route>
    </Routes>
  );
}

function Lazy({ children }) {
  return (
    <Suspense fallback={<div className="text-sm text-zinc-500">Loading…</div>}>
      {children}
    </Suspense>
  );
}
