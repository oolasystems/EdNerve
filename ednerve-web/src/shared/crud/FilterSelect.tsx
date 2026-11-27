import type { ReactNode, SelectHTMLAttributes } from "react";
import { Select } from "../ui/Input";

export interface FilterSelectProps
  extends Omit<SelectHTMLAttributes<HTMLSelectElement>, "value" | "onChange" | "children"> {
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
  className = "",
  ...selectProps
}: FilterSelectProps) {
  return (
    <Select
      {...selectProps}
      aria-label={ariaLabel}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      className={["filter-select", className].filter(Boolean).join(" ")}
    >
      {children}
    </Select>
  );
}

export default FilterSelect;
