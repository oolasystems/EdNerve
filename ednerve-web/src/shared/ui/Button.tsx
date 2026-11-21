import type { LucideIcon } from "lucide-react";
import { Loader2 } from "lucide-react";
import type { ButtonHTMLAttributes } from "react";

export type ButtonVariant = "primary" | "secondary" | "ghost" | "danger";
export type ButtonSize = "sm" | "md";

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  icon?: LucideIcon;
  fullWidth?: boolean;
}

export function Button({
  variant = "primary",
  size = "md",
  loading = false,
  icon: Icon,
  fullWidth = false,
  className = "",
  disabled,
  children,
  type = "button",
  ...rest
}: ButtonProps) {
  const classes = [
    "btn",
    `btn-${variant}`,
    size === "sm" ? "btn-sm" : "",
    fullWidth ? "btn-block" : "",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <button
      type={type}
      className={classes}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...rest}
    >
      {loading ? (
        <Loader2 size={16} className="spinner" aria-hidden="true" />
      ) : (
        Icon && <Icon size={16} aria-hidden="true" />
      )}
      {children}
    </button>
  );
}

export default Button;
