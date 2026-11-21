import type { ReactNode } from "react";
import { Select } from "../ui/Input";

export interface FilterSelectProps {
  "aria-label": string;
  value: string;
  onChange: (value: string) => void;
  children: ReactNode;
}

/** Lightweight toolbar filter dropdown built on the shared Select. */
export function FilterSelect({
  "aria-label": ariaLabel,
  value,
  onChange,
  children,
}: FilterSelectProps) {
  return (
    <Select
      aria-label={ariaLabel}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      className="filter-select"
    >
      {children}
    </Select>
  );
}

export default FilterSelect;
