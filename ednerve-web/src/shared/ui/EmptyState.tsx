import type { ReactNode } from "react";
import { Inbox } from "lucide-react";

export interface EmptyStateProps {
  icon?: ReactNode;
  title: string;
  description?: string;
  action?: ReactNode;
}

export function EmptyState({
  icon,
  title,
  description,
  action,
}: EmptyStateProps) {
  return (
    <div className="empty-state">
      {icon ?? <Inbox size={30} aria-hidden="true" />}
      <strong>{title}</strong>
      {description && <p>{description}</p>}
      {action}
    </div>
  );
}
