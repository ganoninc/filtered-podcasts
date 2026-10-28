import type { ReactNode } from 'react';

interface ScreenMessageProps {
  title: string;
  message?: string;
  loading?: boolean;
  action?: ReactNode;
}

export function ScreenMessage({ title, message, loading = false, action }: ScreenMessageProps) {
  return (
    <section className="state-screen" aria-busy={loading}>
      {loading ? <div className="spinner" aria-hidden="true" /> : null}
      <h1 className={loading ? 'visually-hidden' : undefined}>{title}</h1>
      {message ? <p>{message}</p> : null}
      {action}
    </section>
  );
}
