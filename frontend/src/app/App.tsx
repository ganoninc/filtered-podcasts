import { useEffect, useState } from 'react';
import { Link, Navigate, Route, Routes } from 'react-router-dom';
import { Header } from '../components/layout/Header';
import { ProtectedRoute } from '../components/routing/ProtectedRoute';
import { ScreenMessage } from '../components/ui/ScreenMessage';
import { ToastProvider } from '../components/ui/Toast';
import { AdminDashboardPage } from '../features/admin/AdminDashboardPage';
import { FeedEditorPage } from '../features/admin/FeedEditorPage';
import { LoginPage } from '../features/auth/LoginPage';
import { PublicCatalogPage } from '../features/catalog/PublicCatalogPage';
import { api } from '../lib/api';
import type { BootstrapData } from '../lib/types';

export function App() {
  const [bootstrap, setBootstrap] = useState<BootstrapData | null>(null);
  const [bootstrapError, setBootstrapError] = useState<string | null>(null);

  useEffect(() => {
    api.bootstrap()
      .then(setBootstrap)
      .catch(() => setBootstrapError('We couldn’t reach the server. Check your connection and reload the page.'));
  }, []);

  if (bootstrapError) {
    return (
      <main className="shell">
        <ScreenMessage
          title="Something went wrong"
          message={bootstrapError}
          action={<button type="button" className="btn btn-secondary" onClick={() => window.location.reload()}>Reload</button>}
        />
      </main>
    );
  }

  if (bootstrap === null) {
    return (
      <main className="shell">
        <ScreenMessage title="Loading" loading />
      </main>
    );
  }

  return (
    <ToastProvider>
      <Header bootstrap={bootstrap} />
      <main className="shell main">
        <Routes>
          <Route path="/" element={<PublicCatalogPage />} />
          <Route path="/login" element={<LoginPage bootstrap={bootstrap} />} />
          <Route path="/admin" element={<Navigate to="/admin/feeds" replace />} />
          <Route
            path="/admin/feeds"
            element={(
              <ProtectedRoute bootstrap={bootstrap}>
                <AdminDashboardPage bootstrap={bootstrap} />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/admin/feeds/new"
            element={(
              <ProtectedRoute bootstrap={bootstrap}>
                <FeedEditorPage bootstrap={bootstrap} />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/admin/feeds/:id/edit"
            element={(
              <ProtectedRoute bootstrap={bootstrap}>
                <FeedEditorPage bootstrap={bootstrap} />
              </ProtectedRoute>
            )}
          />
          <Route
            path="*"
            element={(
              <ScreenMessage
                title="Page not found"
                message="The page you’re looking for doesn’t exist."
                action={<Link className="btn btn-secondary" to="/">Back to feeds</Link>}
              />
            )}
          />
        </Routes>
      </main>
    </ToastProvider>
  );
}
