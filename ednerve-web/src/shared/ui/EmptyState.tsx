import type { HTMLAttributes, ReactNode } from "react";
import { Inbox } from "lucide-react";

export interface EmptyStateProps extends HTMLAttributes<HTMLDivElement> {
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
  className = "",
  ...rest
}: EmptyStateProps) {
  return (
    <div className={`empty-state ${className}`.trim()} {...rest}>
      <span className="empty-state-icon" aria-hidden="true">
        {icon ?? <Inbox size={30} />}
      </span>
      <strong>{title}</strong>
      {description && <p>{description}</p>}
      {action}
    </div>
  );
}
