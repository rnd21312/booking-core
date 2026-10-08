import { describe, expect, it } from 'vitest';
import { formatDate, formatMoney } from './format';
import type { Currency } from './types';

const thb: Currency = {
  code: 'THB',
  symbol: '฿',
  position: 'before',
  decimals: 0,
  thousand_separator: ',',
  decimal_separator: '.',
  minor_unit: 100,
};

describe('formatMoney', () => {
  it('converts minor units and groups thousands', () => {
    expect(formatMoney(1800000, thb)).toBe('฿18,000');
    expect(formatMoney(12345600, thb)).toBe('฿123,456');
  });

  it('honours decimals and separators from settings', () => {
    const eur: Currency = {
      ...thb,
      symbol: '€',
      position: 'after',
      decimals: 2,
      thousand_separator: '.',
      decimal_separator: ',',
    };
    expect(formatMoney(123456, eur)).toBe('1.234,56 €');
  });

  it('handles zero and negatives', () => {
    expect(formatMoney(0, thb)).toBe('฿0');
    expect(formatMoney(-150000, thb)).toBe('-฿1,500');
  });
});

describe('formatDate', () => {
  it('formats a Y-m-d date without timezone drift', () => {
    expect(formatDate('2030-01-05', 'en_US')).toBe('Jan 5, 2030');
  });

  it('returns the input when it is not a date', () => {
    expect(formatDate('nope', 'en_US')).toBe('nope');
  });
});
