/**
 * Builds a short, readable passkey name such as "iPhone · Safari" from a user agent.
 */
export function deviceName(userAgent: string): string {
    const platform = /iPhone/.test(userAgent)
        ? 'iPhone'
        : /iPad/.test(userAgent)
          ? 'iPad'
          : /Android/.test(userAgent)
            ? 'Android'
            : /Macintosh|Mac OS X/.test(userAgent)
              ? 'Mac'
              : /Windows/.test(userAgent)
                ? 'Windows'
                : null;

    const browser = /SamsungBrowser/.test(userAgent)
        ? 'Samsung Internet'
        : /Edg(e|A|iOS)?\//.test(userAgent)
          ? 'Edge'
          : /Firefox|FxiOS/.test(userAgent)
            ? 'Firefox'
            : /Chrome|CriOS/.test(userAgent)
              ? 'Chrome'
              : /Safari/.test(userAgent)
                ? 'Safari'
                : null;

    const parts = [platform, browser].filter(
        (part): part is string => part !== null,
    );

    return parts.length > 0 ? parts.join(' · ') : 'อุปกรณ์นี้';
}
