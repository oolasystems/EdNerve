import type { ReactNode } from "react";

export interface FormFieldProps {
  label: string;
  htmlFor: string;
  required?: boolean;
  hint?: string;
  error?: string;
  children: ReactNode;
  className?: string;
}

export function FormField({
  label,
  htmlFor,
  required = false,
  hint,
  error,
  children,
  className = "",
}: FormFieldProps) {
  return (
    <div className={`form-group ${className}`.trim()}>
      <label className="form-label" htmlFor={htmlFor}>
        {label}
        {required && (
          <>
            {" "}
            <span className="required-mark" aria-hidden="true">
              *
            </span>
          </>
        )}
      </label>

      {children}

      {hint && !error && <small className="field-hint">{hint}</small>}
      {error && (
        <small className="field-error" role="alert">
          {error}
        </small>
      )}
    </div>
  );
}

export default FormField;
