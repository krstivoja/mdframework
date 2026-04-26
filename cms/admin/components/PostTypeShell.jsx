import { Navigate, Outlet, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import PostTypeList from './PostTypeList.jsx';
import { DirtyProvider } from '../lib/dirty.jsx';

// Layout for /:folder and /new/:folder. Validates the folder against real
// content folders, then renders the sibling-list middle column + active
// child (PagesList table or PageEditor) inside a shared dirty-state context.
export default function PostTypeShell() {
  const { folder } = useParams();
  const { data, isLoading } = useQuery({
    queryKey: ['pages'],
    queryFn: () => api.get('/pages'),
  });

  if (isLoading) return null;
  const folders = data?.folders || [];
  if (folder && !folders.includes(folder)) {
    return <Navigate to="/" replace />;
  }

  return (
    <DirtyProvider>
      <PostTypeList />
      <Outlet />
    </DirtyProvider>
  );
}
