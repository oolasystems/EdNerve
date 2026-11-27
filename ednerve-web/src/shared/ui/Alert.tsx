import type { HTMLAttributes, ReactNode } from "react";
import { AlertCircle, CheckCircle2, Info, TriangleAlert } from "lucide-react";

export type AlertTone = "error" | "success" | "info" | "warning";

export interface AlertProps extends HTMLAttributes<HTMLDivElement> {
  tone?: AlertTone;
  children: ReactNode;
}

const icons: Record<AlertTone, typeof AlertCircle> = {
  error: AlertCircle,
  success: CheckCircle2,
  info: Info,
  warning: TriangleAlert,
};

/** Shared feedback message. Defaults to role="alert" for existing behavior. */
export function Alert({
  tone = "info",
  children,
  className = "",
  role = "alert",
  ...rest
}: AlertProps) {
  const Icon = icons[tone];
  const classes = ["alert", `alert-${tone}`, className]
    .filter(Boolean)
    .join(" ");

  return (
    <div {...rest} className={classes} role={role}>
      <Icon size={18} aria-hidden="true" />
      <span>{children}</span>
    </div>
  );
}
