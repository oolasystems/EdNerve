import type { HTMLAttributes, ReactNode } from "react";

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
}

export function Card({ className = "", children, ...rest }: CardProps) {
  return (
    <div className={`page-card ${className}`.trim()} {...rest}>
      {children}
    </div>
  );
}

export interface StatCardProps extends HTMLAttributes<HTMLDivElement> {
  title: string;
  value: number | string;
  description?: string;
  icon: ReactNode;
  /** Existing CSS tone class; defaults to the established blue treatment. */
  iconTone?: string;
}

export function StatCard({
  title,
  value,
  description,
  icon,
  iconTone = "blue",
  className = "",
  ...rest
}: StatCardProps) {
  return (
    <div className={`dashboard-metric-card ${className}`.trim()} {...rest}>
      <div className={`dashboard-metric-icon ${iconTone}`} aria-hidden="true">
        {icon}
      </div>
      <div className="dashboard-metric-details">
        <p className="dashboard-metric-title">{title}</p>
        <h2>
          {typeof value === "number" ? value.toLocaleString("en-IN") : value}
        </h2>
        {description && <span>{description}</span>}
      </div>
    </div>
  );
}
