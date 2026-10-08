import type { Currency } from './types';

/**
 * Formats integer minor units using the currency settings from WordPress.
 * Symbols/separators are never hard-coded: the admin can change them in Suntourz → Settings.
 */
export const formatMoney = (minor: number, currency: Currency): string => {
  const major = minor / currency.minor_unit;
  const [whole = '0', fraction = ''] = Math.abs(major).toFixed(currency.decimals).split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousand_separator);
  const number =
    currency.decimals > 0 ? `${grouped}${currency.decimal_separator}${fraction}` : grouped;
  const sign = major < 0 ? '-' : '';

  return currency.position === 'after'
    ? `${sign}${number} ${currency.symbol}`
    : `${sign}${currency.symbol}${number}`;
};

/** Formats a Y-m-d string (site-local date) without timezone shifts. */
export const formatDate = (
  ymd: string,
  locale: string,
  options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' },
): string => {
  const [y, m, d] = ymd.split('-').map(Number);
  if (!y || !m || !d) return ymd;

  return new Intl.DateTimeFormat(locale.replace('_', '-'), { ...options, timeZone: 'UTC' }).format(
    new Date(Date.UTC(y, m - 1, d)),
  );
};
