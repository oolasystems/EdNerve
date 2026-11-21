import type { LucideIcon } from "lucide-react";
import type { InputHTMLAttributes } from "react";

export interface SearchInputProps
  extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  icon?: LucideIcon;
}

export function SearchInput({
  icon: Icon,
  className = "",
  ...rest
}: SearchInputProps) {
  return (
    <div className={`search-input ${className}`.trim()}>
      {Icon && <Icon size={18} aria-hidden="true" />}
      <input type="search" {...rest} />
    </div>
  );
}

export default SearchInput;
