import { createContext, useCallback, useContext, useState } from 'react';
import type { ReactNode } from 'react';
import { Icon } from './Icon';

interface Toast {
  id: number;
  message: string;
  tone: 'default' | 'error';
}

type Notify = (message: string, tone?: Toast['tone']) => void;

const ToastContext = createContext<Notify>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const notify = useCallback<Notify>((message, tone = 'default') => {
    const id = Date.now() + Math.random();
    setToasts((current) => [...current, { id, message, tone }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3500);
  }, []);

  return (
    <ToastContext.Provider value={notify}>
      {children}
      <div className="toast-region" role="status" aria-live="polite">
        {toasts.map((toast) => (
          <div key={toast.id} className={`toast ${toast.tone === 'error' ? 'error' : ''}`}>
            <Icon name={toast.tone === 'error' ? 'alert' : 'check'} />
            {toast.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): Notify {
  return useContext(ToastContext);
}
