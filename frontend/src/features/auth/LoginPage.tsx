import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { Icon } from '../../components/ui/Icon';
import type { BootstrapData } from '../../lib/types';

interface LoginPageProps {
  bootstrap: BootstrapData;
}

export function LoginPage({ bootstrap }: LoginPageProps) {
  const [submitting, setSubmitting] = useState(false);

  if (bootstrap.user) {
    return <Navigate to="/admin/feeds" replace />;
  }

  return (
    <div className="auth">
      <div className="auth-head">
        <h1>Sign in</h1>
        <p className="muted">Manage your filtered feeds and their rules.</p>
      </div>
      <form method="post" action="/login" className="card panel" onSubmit={() => setSubmitting(true)}>
        {bootstrap.auth.lastError ? (
          <div className="banner banner-error" role="alert">
            <Icon name="alert" />
            That email and password don’t match. Please try again.
          </div>
        ) : null}
        <div className="fields">
          <label className="field">
            <span className="field-label">Email</span>
            <input
              className="input"
              name="_username"
              type="email"
              autoComplete="username"
              defaultValue={bootstrap.auth.lastUsername}
              autoFocus={!bootstrap.auth.lastUsername}
              required
            />
          </label>
          <label className="field">
            <span className="field-label">Password</span>
            <input
              className="input"
              name="_password"
              type="password"
              autoComplete="current-password"
              autoFocus={Boolean(bootstrap.auth.lastUsername)}
              required
            />
          </label>
        </div>
        <input type="hidden" name="_csrf_token" value={bootstrap.csrf.login} />
        <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </div>
  );
}
