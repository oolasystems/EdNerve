import { Loader2 } from "lucide-react";
import type { HTMLAttributes, SVGProps } from "react";

export interface SpinnerProps extends Omit<SVGProps<SVGSVGElement>, "size"> {
  size?: number;
  label?: string;
}

export function Spinner({
  size = 18,
  className = "",
  label = "Loading",
  role = "status",
  ...rest
}: SpinnerProps) {
  return (
    <Loader2
      size={size}
      className={`spinner ${className}`.trim()}
      role={role}
      aria-label={label}
      {...rest}
    />
  );
}

export interface LoadingStateProps extends HTMLAttributes<HTMLDivElement> {
  message?: string;
  spinnerSize?: number;
}

export function LoadingState({
  message = "Loading...",
  spinnerSize = 24,
  className = "",
  ...rest
}: LoadingStateProps) {
  return (
    <div className={`loading-state ${className}`.trim()} {...rest}>
      <Spinner size={spinnerSize} label={message} />
      <p>{message}</p>
    </div>
  );
}
