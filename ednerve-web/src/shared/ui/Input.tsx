import type { InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes } from "react";

export type InputProps = InputHTMLAttributes<HTMLInputElement>;
export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;
export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

export function Input({ className = "", ...rest }: InputProps) {
  return <input className={`form-control ${className}`.trim()} {...rest} />;
}

export function Select({ className = "", children, ...rest }: SelectProps) {
  return (
    <select className={`form-control ${className}`.trim()} {...rest}>
      {children}
    </select>
  );
}

export function Textarea({ className = "", ...rest }: TextareaProps) {
  return <textarea className={`form-control ${className}`.trim()} {...rest} />;
}
