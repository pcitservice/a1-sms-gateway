// Central date/time formatting so the whole app renders in Denmark local
// time regardless of the viewer's browser timezone. Laravel serializes
// timestamps as ISO 8601 UTC (e.g. "2026-09-27T08:52:00.000000Z") — but
// some rows also come back as space-separated naive strings from Postgres
// timestamp columns, so we tolerate both.

const TZ = 'Europe/Copenhagen';
const LOCALE = 'da-DK';

function toDate(input: string | null | undefined): Date | null {
  if (!input) return null;
  // Normalise "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM:SSZ" so Safari + JS
  // parse it as UTC rather than as the browser's local time.
  let s = input.trim();
  if (s.includes(' ') && !s.includes('T')) s = s.replace(' ', 'T');
  const hasTz = /Z$|[+-]\d{2}:?\d{2}$/.test(s);
  if (!hasTz) s += 'Z';
  const d = new Date(s);
  return isNaN(d.getTime()) ? null : d;
}

export function formatDateTime(input: string | null | undefined): string {
  const d = toDate(input);
  return d
    ? d.toLocaleString(LOCALE, {
        timeZone: TZ,
        dateStyle: 'short',
        timeStyle: 'short',
      })
    : '';
}

export function formatDate(input: string | null | undefined): string {
  const d = toDate(input);
  return d
    ? d.toLocaleDateString(LOCALE, { timeZone: TZ })
    : '';
}

export function formatTime(input: string | null | undefined): string {
  const d = toDate(input);
  return d
    ? d.toLocaleTimeString(LOCALE, { timeZone: TZ, hour: '2-digit', minute: '2-digit' })
    : '';
}

/** Build the value= string for a datetime-local input from an ISO string. */
export function toInputDateTimeLocal(input: string | null | undefined): string {
  const d = toDate(input);
  if (!d) return '';
  // datetime-local wants local wall-clock time. We use en-CA which gives
  // "YYYY-MM-DD, HH:MM" and slice it into the ISO-ish shape the input wants.
  const iso = d.toLocaleString('sv-SE', { timeZone: TZ, hour12: false }); // 2026-09-27 10:52:00
  return iso.replace(' ', 'T').slice(0, 16);
}
