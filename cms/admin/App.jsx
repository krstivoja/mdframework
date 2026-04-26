import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useAuth } from './lib/auth.jsx';

import Protected from './components/Protected.jsx';
import Shell from './components/Shell.jsx';
import NotFound from './components/NotFound.jsx';

import Login from './screens/Login.jsx';
import PagesList from './screens/PagesList.jsx';
import Media from './screens/Media.jsx';
import Backup from './screens/Backup.jsx';
import Settings from './screens/Settings/index.jsx';
import SiteSettings from './screens/Settings/SiteSettings.jsx';
import Fields from './screens/Settings/Fields/index.jsx';
import Themes from './screens/Settings/Themes.jsx';

// Editor pulls in SunEditor (~900KB) — load on demand.
const PageEditor = lazy(() => import('./screens/PageEditor.jsx'));

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
          <Route path="/" element={<PagesList />} />
          <Route path="/edit" element={<LazyEditor />} />
          <Route path="/edit/*" element={<LazyEditor />} />
          <Route path="/media" element={<Media />} />
          <Route path="/settings" element={<Settings />}>
            <Route index element={<SiteSettings />} />
            <Route path="fields" element={<Fields />} />
            <Route path="themes" element={<Themes />} />
          </Route>
          <Route path="/backup" element={<Backup />} />
          <Route path="*" element={<NotFound />} />
        </Route>
      </Route>
    </Routes>
  );
}

function LazyEditor() {
  return (
    <Suspense fallback={<div className="text-sm text-zinc-500">Loading editor…</div>}>
      <PageEditor />
    </Suspense>
  );
}
