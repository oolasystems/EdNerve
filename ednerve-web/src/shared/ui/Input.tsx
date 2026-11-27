import type {
  InputHTMLAttributes,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from "react";

/** Native input attributes plus the shared EdNerve form-control styling. */
export type InputProps = InputHTMLAttributes<HTMLInputElement>;

/** Native select attributes plus the shared EdNerve form-control styling. */
export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

/** Native textarea attributes plus the shared EdNerve form-control styling. */
export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

function mergeControlClassName(className?: string): string {
  return ["form-control", className].filter(Boolean).join(" ");
}

export function Input({ className, ...rest }: InputProps) {
  return <input className={mergeControlClassName(className)} {...rest} />;
}

export function Select({ className, children, ...rest }: SelectProps) {
  return (
    <select className={mergeControlClassName(className)} {...rest}>
      {children}
    </select>
  );
}

export function Textarea({ className, ...rest }: TextareaProps) {
  return <textarea className={mergeControlClassName(className)} {...rest} />;
}
