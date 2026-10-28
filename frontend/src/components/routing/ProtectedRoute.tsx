import { Navigate } from 'react-router-dom';
import type { ReactNode } from 'react';
import type { BootstrapData } from '../../lib/types';

interface ProtectedRouteProps {
  bootstrap: BootstrapData;
  children: ReactNode;
}

export function ProtectedRoute({ bootstrap, children }: ProtectedRouteProps) {
  if (!bootstrap.user) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}
