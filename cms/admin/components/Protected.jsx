import { Navigate, Outlet, useLocation } from 'react-router-dom';

export default function Protected({ user }) {
  const location = useLocation();
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />;
  return <Outlet />;
}
