import { Loader2 } from "lucide-react";

export interface SpinnerProps {
  size?: number;
  className?: string;
  label?: string;
}

export function Spinner({ size = 18, className = "", label }: SpinnerProps) {
  return (
    <Loader2
      size={size}
      className={`spinner ${className}`.trim()}
      role="status"
      aria-label={label ?? "Loading"}
    />
  );
}

export interface LoadingStateProps {
  message?: string;
  spinnerSize?: number;
}

export function LoadingState({
  message = "Loading...",
  spinnerSize = 24,
}: LoadingStateProps) {
  return (
    <div className="loading-state">
      <Spinner size={spinnerSize} label={message} />
      <p>{message}</p>
    </div>
  );
}
