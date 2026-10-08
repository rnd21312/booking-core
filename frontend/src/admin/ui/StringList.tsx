import { useRef } from 'react';
import { Plus, X } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Button, TextInput } from './controls';

type StringListProps = {
  label: string;
  items: string[];
  onChange: (items: string[]) => void;
  placeholder?: string;
  addLabel?: string;
};

/** Editable list of short strings (highlights, includes, excludes). Enter adds a row. */
export const StringList = ({ label, items, onChange, placeholder, addLabel }: StringListProps) => {
  const refs = useRef<(HTMLInputElement | null)[]>([]);

  const update = (index: number, value: string) =>
    onChange(items.map((item, i) => (i === index ? value : item)));

  const add = () => {
    onChange([...items, '']);
    requestAnimationFrame(() => refs.current[items.length]?.focus());
  };

  return (
    <fieldset className="stz-field" style={{ border: 0, padding: 0, margin: 0 }}>
      <legend className="stz-label">{label}</legend>
      {items.map((item, index) => (
        <div className="stz-list-item" key={index}>
          <TextInput
            ref={(node: HTMLInputElement | null) => {
              refs.current[index] = node;
            }}
            value={item}
            placeholder={placeholder}
            aria-label={`${label} ${index + 1}`}
            onChange={(value) => update(index, value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                add();
              }
            }}
          />
          <Button
            icon
            variant="ghost"
            aria-label={__('Remove', 'suntourz')}
            onClick={() => onChange(items.filter((_, i) => i !== index))}
          >
            <X size={16} aria-hidden />
          </Button>
        </div>
      ))}
      <Button onClick={add}>
        <Plus size={14} aria-hidden /> {addLabel ?? __('Add', 'suntourz')}
      </Button>
    </fieldset>
  );
};
