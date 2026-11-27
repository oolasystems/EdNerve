import type { LucideIcon } from "lucide-react";
import type { InputHTMLAttributes } from "react";

export interface SearchInputProps
  extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  icon?: LucideIcon;
  /** Additional class for the search input wrapper; `className` remains supported. */
  wrapperClassName?: string;
  /** Additional class for the native input element. */
  inputClassName?: string;
}

export function SearchInput({
  icon: Icon,
  className = "",
  wrapperClassName = "",
  inputClassName = "",
  ...rest
}: SearchInputProps) {
  return (
    <div className={`search-input ${className} ${wrapperClassName}`.trim()}>
      {Icon && <Icon size={18} aria-hidden="true" />}
      <input
        type="search"
        className={inputClassName || undefined}
        {...rest}
      />
    </div>
  );
}

export default SearchInput;
