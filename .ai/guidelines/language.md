# Language

- This application is **Thai-only** (`th`). All user-facing text must be written in Thai.
- Set `APP_LOCALE=th` and `APP_FALLBACK_LOCALE=th`.
- Backend messages use Laravel lang files in `lang/th/` (`__()` / `trans()`).
- Frontend (React/Inertia) uses hardcoded Thai strings in JSX — no i18n library.
- Code (variables, methods, routes, file names) remains in English.
- Accessibility labels (`aria-label`, `sr-only`) must also be in Thai.
