import { describe, expect, it } from 'vitest';
import { addDays, daysBetween, parseYmd } from './dates';

describe('dates', () => {
  it('adds days across month and year boundaries', () => {
    expect(addDays('2030-01-30', 3)).toBe('2030-02-02');
    expect(addDays('2030-12-30', 5)).toBe('2031-01-04');
    expect(addDays('2032-02-28', 1)).toBe('2032-02-29');
    expect(addDays('2030-03-01', -1)).toBe('2030-02-28');
  });

  it('returns invalid input untouched', () => {
    expect(addDays('', 3)).toBe('');
    expect(addDays('2030-02-31', 1)).toBe('2030-02-31');
  });

  it('rejects impossible dates', () => {
    expect(parseYmd('2030-02-31')).toBeNull();
    expect(parseYmd('2030-2-3')).toBeNull();
    expect(parseYmd('2030-02-03')).not.toBeNull();
  });

  it('counts days between dates', () => {
    expect(daysBetween('2030-01-01', '2030-01-15')).toBe(14);
    expect(daysBetween('2030-01-02', '2030-01-01')).toBe(-1);
    expect(daysBetween('nope', '2030-01-01')).toBeNaN();
  });
});
