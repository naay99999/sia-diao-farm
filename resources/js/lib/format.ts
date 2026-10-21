/**
 * Number formats shared by every farm screen: kilograms up to two decimals, baht with or without satang.
 */
export function formatNumber(
    value: number | string,
    maximumFractionDigits = 2,
): string {
    return Number(value).toLocaleString('th-TH', { maximumFractionDigits });
}

export function formatBaht(value: number | string): string {
    return Number(value).toLocaleString('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
