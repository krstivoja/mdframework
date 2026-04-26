import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useAuth } from './lib/auth.jsx';

import Protected from './components/Protected.jsx';
import Shell, { PaddedOutlet } from './components/Shell.jsx';
import PostTypeShell from './components/PostTypeShell.jsx';
import NotFound from './components/NotFound.jsx';

import Login from './screens/Login.jsx';
import PagesList from './screens/PagesList.jsx';

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
          <Route element={<PaddedOutlet />}>
            <Route path="/"          element={<PagesList />} />
            <Route path="/media"     element={<Lazy><Media /></Lazy>} />
            <Route path="/backup"    element={<Lazy><Backup /></Lazy>} />
            <Route path="/settings"  element={<Lazy><Settings /></Lazy>}>
              <Route index           element={<Lazy><SiteSettings /></Lazy>} />
              <Route path="fields"   element={<Lazy><Fields /></Lazy>} />
              <Route path="themes"   element={<Lazy><Themes /></Lazy>} />
            </Route>
            <Route path="/:folder" element={<PagesList />} />
            <Route path="*" element={<NotFound />} />
          </Route>

          <Route path="/new/:folder" element={<PostTypeShell />}>
            <Route index element={<Lazy><PageEditor /></Lazy>} />
          </Route>
          <Route path="/:folder/:slug" element={<PostTypeShell />}>
            <Route index element={<Lazy><PageEditor /></Lazy>} />
          </Route>
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
