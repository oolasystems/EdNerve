import {
  Children,
  cloneElement,
  Fragment,
  isValidElement,
  useId,
  type ReactElement,
  type ReactNode,
} from "react";

export interface FormFieldProps {
  label: string;
  htmlFor: string;
  required?: boolean;
  hint?: string;
  error?: string;
  children: ReactNode;
  className?: string;
}

type FormControlAccessibilityProps = {
  "aria-describedby"?: string;
  "aria-invalid"?: boolean | "true" | "false";
  "aria-required"?: boolean | "true" | "false";
  id?: string;
};

/**
 * Adds the field's accessible description and validation state to its control.
 * Fragments are supported. A FormField should contain one form control; the
 * htmlFor value is applied as that control's id to keep the label association
 * reliable. Existing aria-describedby values are preserved.
 */
function attachAccessibilityProps(
  children: ReactNode,
  accessibilityProps: FormControlAccessibilityProps,
  controlId: string,
): ReactNode {
  return Children.map(children, (child) => {
    if (!isValidElement(child)) return child;

    // Preserve fragments while applying the attributes to their control child.
    if (child.type === Fragment) {
      const fragment = child as ReactElement<{ children?: ReactNode }>;
      return cloneElement(
        fragment,
        undefined,
        attachAccessibilityProps(fragment.props.children, accessibilityProps, controlId),
      );
    }

    const existingProps = child.props as FormControlAccessibilityProps;
    const describedBy = [
      existingProps["aria-describedby"],
      accessibilityProps["aria-describedby"],
    ]
      .filter(Boolean)
      .join(" ");

    return cloneElement(
      child as ReactElement<Record<string, unknown>>,
      {
        ...accessibilityProps,
        id: controlId,
        ...(describedBy ? { "aria-describedby": describedBy } : {}),
        ...(accessibilityProps["aria-invalid"] === undefined &&
        existingProps["aria-invalid"] !== undefined
          ? { "aria-invalid": existingProps["aria-invalid"] }
          : {}),
      },
    );
  });
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
  const generatedId = useId();
  const hintId = `${generatedId}-hint`;
  const errorId = `${generatedId}-error`;
  const descriptionId = error ? errorId : hint ? hintId : undefined;

  const control = attachAccessibilityProps(children, {
    ...(descriptionId ? { "aria-describedby": descriptionId } : {}),
    ...(error ? { "aria-invalid": true } : {}),
    ...(required ? { "aria-required": true } : {}),
  }, htmlFor);

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

      {control}

      {hint && !error && (
        <small id={hintId} className="field-hint">
          {hint}
        </small>
      )}
      {error && (
        <small id={errorId} className="field-error" role="alert">
          {error}
        </small>
      )}
    </div>
  );
}

export default FormField;
