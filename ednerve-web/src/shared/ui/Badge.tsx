import type { HTMLAttributes, ReactNode } from "react";

export type BadgeTone =
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "neutral";

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  tone?: BadgeTone;
  dot?: boolean;
  children: ReactNode;
}

export function Badge({
  tone = "neutral",
  dot = false,
  className = "",
  children,
  ...rest
}: BadgeProps) {
  const classes = ["badge", `badge-${tone}`, className]
    .filter(Boolean)
    .join(" ");

  return (
    <span {...rest} className={classes}>
      {dot && <span className="badge-dot" aria-hidden="true" />}
      {children}
    </span>
  );
}

export interface StatusBadgeProps {
  status: string;
}

/** Maps the common active/inactive states; unknown states remain neutral. */
export function StatusBadge({ status }: StatusBadgeProps) {
  const normalized = status.trim().toLowerCase();

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
