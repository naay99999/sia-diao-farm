export function formatThaiDate(date: string): string {
    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
