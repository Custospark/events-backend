# Changelog

Version is the same everywhere: frontend `package.json` version (injected at
build time by Vite), and backend `APP_VERSION` (served by
`/api/v1/health`). Bump with `npm version patch|minor|major` in the frontend
repo and set the same value as `APP_VERSION` in the backend `.env` /
`.env.production`.

## 5.4.13 - 2026-11-14

### Added
- Payout request workflow for organisers: request payouts up to the available
  balance, add priority and notes, upload payment evidence, platform team
  processes (initiate -> processing -> paid) with audit trail.
- Tenant suspension by the platform team: reason required, tenant admins are
  emailed with the reason, login blocked, staff sessions revoked, full
  suspension history exposed to the platform admin console.
- Platform team management (`/platform/users`): platform admins can create,
  edit, suspend and delete platform console members and roles, with scoped
  permissions (Overview, Organisers, Payouts, Platform team). At least one
  active full-access platform administrator must always remain.
- Access-denied UX: users are redirected to the first page they have access
  to; "Access restricted" is the last resort only.
- Branded loader used app-wide, with a static pre-boot splash in `index.html`.

### Changed
- Frontend build pipeline keeps the SPA under `public/production`; deploys now
  ship version 5.4.13.

## 0.1.0 - Initial release

Initial public release: event discovery, ticket purchase, organiser workspace,
payout settlement, multi-currency support and the public/event tenant pages.
