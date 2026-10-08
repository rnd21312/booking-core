import {
  useEffect,
  useRef,
  useState,
  type ButtonHTMLAttributes,
  type ComponentProps,
  type InputHTMLAttributes,
} from 'react';

/* ---- Button ---- */

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'default' | 'primary' | 'danger' | 'ghost';
  icon?: boolean;
};

export const Button = ({ variant = 'default', icon = false, className = '', type = 'button', ...rest }: ButtonProps) => {
  const classes = [
    'stz-btn',
    variant === 'primary' && 'stz-btn-primary',
    variant === 'danger' && 'stz-btn-danger',
    variant === 'ghost' && 'stz-btn-ghost',
    icon && 'stz-btn-icon',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return <button type={type} className={classes} {...rest} />;
};

/* ---- Text ---- */

type TextInputProps = Omit<ComponentProps<'input'>, 'onChange' | 'value'> & {
  value: string;
  onChange: (value: string) => void;
};

export const TextInput = ({ onChange, className = '', ...rest }: TextInputProps) => (
  <input type="text" className={`stz-input ${className}`} onChange={(e) => onChange(e.target.value)} {...rest} />
);

type TextareaProps = {
  value: string;
  onChange: (value: string) => void;
  rows?: number;
  id?: string;
  placeholder?: string;
};

export const Textarea = ({ onChange, ...rest }: TextareaProps) => (
  <textarea className="stz-textarea" onChange={(e) => onChange(e.target.value)} {...rest} />
);

/* ---- Numbers ---- */

type NumberInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value' | 'type'> & {
  value: number | null;
  /** null is emitted for an empty box. */
  onChange: (value: number | null) => void;
};

export const NumberInput = ({ value, onChange, className = '', ...rest }: NumberInputProps) => (
  <input
    type="number"
    inputMode="numeric"
    className={`stz-input ${className}`}
    value={value ?? ''}
    onChange={(e) => {
      const raw = e.target.value;
      if (raw === '') return onChange(null);
      const parsed = Number(raw);
      onChange(Number.isFinite(parsed) ? parsed : null);
    }}
    {...rest}
  />
);

/* ---- Money (minor units in, minor units out; user types major units) ---- */

type MoneyInputProps = {
  value: number | null;
  onChange: (minor: number | null) => void;
  minorUnit: number;
  symbol: string;
  id?: string;
  placeholder?: string;
  'aria-invalid'?: boolean;
  'aria-describedby'?: string;
};

const toText = (minor: number | null, minorUnit: number): string =>
  minor === null ? '' : String(Math.round(minor) / minorUnit);

export const MoneyInput = ({ value, onChange, minorUnit, symbol, ...rest }: MoneyInputProps) => {
  const [text, setText] = useState(() => toText(value, minorUnit));
  const focused = useRef(false);

  // Follow external changes (e.g. reset/reload) but never fight the user while typing.
  useEffect(() => {
    if (!focused.current) setText(toText(value, minorUnit));
  }, [value, minorUnit]);

  return (
    <div className="stz-input-money">
      <span aria-hidden>{symbol}</span>
      <input
        type="text"
        inputMode="decimal"
        className="stz-input"
        value={text}
        onFocus={() => (focused.current = true)}
        onBlur={() => {
          focused.current = false;
          setText(toText(value, minorUnit));
        }}
        onChange={(e) => {
          const next = e.target.value.replace(/[^\d.]/g, '');
          setText(next);
          const parsed = parseFloat(next);
          onChange(next === '' || Number.isNaN(parsed) ? null : Math.round(parsed * minorUnit));
        }}
        {...rest}
      />
    </div>
  );
};

/* ---- Select / Checkbox ---- */

type SelectProps<T extends string> = {
  value: T;
  onChange: (value: T) => void;
  options: { value: T; label: string }[];
  id?: string;
  'aria-label'?: string;
};

export const Select = <T extends string>({ onChange, options, ...rest }: SelectProps<T>) => (
  <select className="stz-select" onChange={(e) => onChange(e.target.value as T)} {...rest}>
    {options.map((option) => (
      <option key={option.value} value={option.value}>
        {option.label}
      </option>
    ))}
  </select>
);

type CheckboxProps = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label: string;
  hint?: string;
};

export const Checkbox = ({ checked, onChange, label, hint }: CheckboxProps) => (
  <label className="stz-row" style={{ alignItems: 'flex-start' }}>
    <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} style={{ marginTop: 3 }} />
    <span>
      <strong>{label}</strong>
      {hint && <span className="stz-hint" style={{ display: 'block' }}>{hint}</span>}
    </span>
  </label>
);
