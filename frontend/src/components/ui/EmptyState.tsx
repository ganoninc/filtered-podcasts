import type { ReactNode } from 'react';
import { Icon } from './Icon';
import type { IconName } from './Icon';

interface EmptyStateProps {
  title: string;
  message: string;
  icon?: IconName;
  action?: ReactNode;
  compact?: boolean;
}

export function EmptyState({ title, message, icon = 'podcast', action, compact = false }: EmptyStateProps) {
  return (
    <div className={`empty ${compact ? 'compact' : ''}`}>
      {compact ? null : <span className="empty-icon"><Icon name={icon} size={20} /></span>}
      <h3>{title}</h3>
      <p>{message}</p>
      {action}
    </div>
  );
}
