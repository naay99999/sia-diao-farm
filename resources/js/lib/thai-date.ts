export function formatThaiDate(date: string): string {
    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Days after a problem is found or checked before it is due for another look.
 */
export const FOLLOW_UP_DAYS = 7;

/**
 * Adds days to a Y-m-d date in local time, so a date picker default never drifts across midnight.
 */
export function addDaysIso(date: string, days: number): string {
    if (!/^\d{4}-\d{2}-\d{2}/.test(date)) {
        return '';
    }

    const [year, month, day] = date.slice(0, 10).split('-').map(Number);
    const shifted = new Date(year, month - 1, day + days);
    const shiftedMonth = String(shifted.getMonth() + 1).padStart(2, '0');
    const shiftedDay = String(shifted.getDate()).padStart(2, '0');

    return `${shifted.getFullYear()}-${shiftedMonth}-${shiftedDay}`;
}
