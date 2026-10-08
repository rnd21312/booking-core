import { cloneElement, useId, type ReactElement } from 'react';

type FieldProps = {
  label: string;
  hint?: string;
  error?: string;
  /** A single form control; receives id, aria-invalid and aria-describedby. */
  children: ReactElement<{ id?: string; 'aria-invalid'?: boolean; 'aria-describedby'?: string }>;
};

export const Field = ({ label, hint, error, children }: FieldProps) => {
  const id = useId();
  const describedBy = [error ? `${id}-error` : '', hint ? `${id}-hint` : ''].filter(Boolean).join(' ');

  return (
    <div className="stz-field">
      <label htmlFor={id}>{label}</label>
      {cloneElement(children, {
        id,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy || undefined,
      })}
      {hint && (
        <p className="stz-hint" id={`${id}-hint`}>
          {hint}
        </p>
      )}
      {error && (
        <p className="stz-error" id={`${id}-error`} role="alert">
          {error}
        </p>
      )}
    </div>
  );
};
