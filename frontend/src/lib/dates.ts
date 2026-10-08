const pad = (n: number) => String(n).padStart(2, '0');

/** Parses a strict Y-m-d string into a UTC Date, or null. */
export const parseYmd = (ymd: string): Date | null => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd);
  if (!match) return null;

  const [, y, m, d] = match.map(Number) as [number, number, number, number];
  const date = new Date(Date.UTC(y, m - 1, d));

  return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d
    ? date
    : null;
};

export const toYmd = (date: Date): string =>
  `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;

/** Adds whole days to a Y-m-d string (timezone-free). Returns the input when it is not a date. */
export const addDays = (ymd: string, days: number): string => {
  const date = parseYmd(ymd);
  if (!date) return ymd;

  date.setUTCDate(date.getUTCDate() + days);
  return toYmd(date);
};

/** Whole days from `a` to `b` (negative when b is earlier). NaN when either is invalid. */
export const daysBetween = (a: string, b: string): number => {
  const from = parseYmd(a);
  const to = parseYmd(b);
  if (!from || !to) return NaN;

  return Math.round((to.getTime() - from.getTime()) / 86_400_000);
};
