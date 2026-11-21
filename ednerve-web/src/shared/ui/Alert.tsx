import type { ReactNode } from "react";
import { AlertCircle, CheckCircle2, Info, TriangleAlert } from "lucide-react";

export type AlertTone = "error" | "success" | "info" | "warning";

export interface AlertProps {
  tone?: AlertTone;
  children: ReactNode;
  className?: string;
}

const icons: Record<AlertTone, typeof AlertCircle> = {
  error: AlertCircle,
  success: CheckCircle2,
  info: Info,
  warning: TriangleAlert,
};

export function Alert({ tone = "info", children, className = "" }: AlertProps) {
  const Icon = icons[tone];

  return (
    <div className={`alert alert-${tone} ${className}`.trim()} role="alert">
      <Icon size={18} aria-hidden="true" />
      <span>{children}</span>
    </div>
  );
}
