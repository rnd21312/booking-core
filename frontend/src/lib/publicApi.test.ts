import { describe, expect, it } from 'vitest';
import { toQueryString } from './publicApi';

describe('toQueryString', () => {
  it('drops empty values and serializes booleans as 1', () => {
    expect(toQueryString({ destination: 'phuket', search: '', discount: true, last_minute: false })).toBe(
      'destination=phuket&discount=1',
    );
  });

  it('joins arrays with commas and skips empty arrays', () => {
    expect(toQueryString({ include: [3, 5, 9], style: [] })).toBe('include=3%2C5%2C9');
  });

  it('keeps zero-like numbers that are meaningful', () => {
    expect(toQueryString({ page: 2, duration_min: 7 })).toBe('page=2&duration_min=7');
  });
});
