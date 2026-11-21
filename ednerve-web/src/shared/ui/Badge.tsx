import type { ReactNode } from "react";

export type BadgeTone =
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "neutral";

export interface BadgeProps {
  tone?: BadgeTone;
  dot?: boolean;
  children: ReactNode;
}

export function Badge({ tone = "neutral", dot = false, children }: BadgeProps) {
  return (
    <span className={`badge badge-${tone}`}>
      {dot && <span className="badge-dot" aria-hidden="true" />}
      {children}
    </span>
  );
}

export function StatusBadge({ status }: { status: string }) {
  const normalized = status.toLowerCase();

  if (normalized === "active") {
    return (
      <Badge tone="success" dot>
        Active
      </Badge>
    );
  }

  if (normalized === "inactive") {
    return (
      <Badge tone="warning" dot>
        Inactive
      </Badge>
    );
  }

  return (
    <Badge tone="neutral" dot>
      {status}
    </Badge>
  );
}
