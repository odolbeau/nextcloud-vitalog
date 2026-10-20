# Vitalog

A [Nextcloud](https://nextcloud.com) app to keep track of your medical appointments.

- **Appointments**: date, duration, reason, notes, practitioner and place. There is no status: "upcoming" and "past" only depend on the date.
- **Practitioners and places**: created once (or on the fly from an appointment) and reused everywhere.
  A practitioner can optionally be linked to a card of the **Contacts** app: the card is the source of truth for the name, specialty
  and phone number, the local copy stays usable if the card disappears, and the contacts menu offers a "Vitalog appointments" action.
- **Labels**: tags (with an optional colour) that bring appointments together across practitioners and places ("ankle", "blood
  donation"…). An appointment can carry several of them. The list can be filtered by label and by practitioner, and both filters
  combine (filter button next to the search field, or the "Labels" page). Labels are written to the calendar event's categories (one
  way: categories edited in Calendar do not come back).
- **Navigation**: the view, the selected appointment and the filters live in the URL
  (`?view=…&appointment=…&label=…&practitioner=…`); back / forward, reload and shared links work, and "back" closes an open dialog.
- **Documents**: prescriptions, reports, results… attached from your Nextcloud files.
- **Calendar**: appointments are mirrored to the calendar you link (with a reminder the day before); moving or deleting one is
  propagated. Optionally (off by default), an event added to that calendar also creates an appointment. When you link the calendar you
  can also import the events it already contains in one go (past ones included).
  One way only: later changes to the event are not propagated back, and recurring events are ignored.
- English and French interface. Your data stays on your instance and is strictly private to each user.

Compatible with Nextcloud **34 and 35** (PHP 8.2+; PHP 8.3+ for Nextcloud 35). Nextcloud 33 is deliberately not supported: its end of
life is 2027-02-18.

## Installation

Until the app is on the Nextcloud App Store, install a release archive by hand:

```sh
tar -xzf vitalog-X.Y.Z.tar.gz -C /path/to/nextcloud/custom_apps/
chown -R www-data:www-data /path/to/nextcloud/custom_apps/vitalog
sudo -u www-data php occ app:enable vitalog
```

`custom_apps` must be one of the `apps_paths` of your `config.php` (the default). The first activation creates the `vitalog_*` tables.
Back up your database beforehand, as for any app installation.

## Uninstalling

Disabling or removing the app keeps your data: Nextcloud runs an app's uninstall steps on every disable (including automatic ones
after an upgrade), so Vitalog never deletes anything by itself. To erase everything it stored, run the purge **before** removing it:

```sh
sudo -u www-data php occ vitalog:purge    # lists what will be deleted and asks for confirmation (--force to skip it)
sudo -u www-data php occ app:remove vitalog
```

It drops the six `vitalog_*` tables and the users' settings. Calendar events created by Vitalog stay in the calendars, and attached
files stay in the users' files.

## Development

Requirements: PHP ≥ 8.2, Composer, Node ≥ 20, Docker.

```sh
composer install && npm install
npm run dev          # front-end build in watch mode
dev/up.sh            # Nextcloud 35 instance on http://localhost:8480 (admin / admin), app enabled
dev/up.sh 34         # same with Nextcloud 34
NC_PORT=8500 dev/up.sh
docker compose -f dev/docker-compose.yml down -v   # remove everything
```

`dev/up.sh` starts an isolated stack (`vitalog-dev`: Nextcloud + PostgreSQL, prefixed ports and container names): it does not touch
your other containers. The repository is mounted in `custom_apps/vitalog`: after `npm run build`, reload the page with a hard refresh
(Ctrl+Shift+R, Nextcloud caches assets). The script disables the *first run wizard* and rate limiting to ease testing, and installs
the Calendar and Contacts apps.

### Tests and quality

| Command | Content |
| --- | --- |
| `composer test` | PHPUnit: unit tests of the services (calendar sync, attachments, cascades, validation) |
| `composer lint` / `composer cs:fix` | php-cs-fixer |
| `composer psalm` | static analysis |
| `npm run lint` | ESLint (src, e2e, scripts) |
| `npm run l10n:check` | every string has its French translation (and no stale key); `npm run l10n:build` regenerates `l10n/fr.js` |
| `npm run e2e` | Playwright against `dev/up.sh`: API (CRUD, isolation between users, attachments, CalDAV events), UI (full journeys, French, axe accessibility, mobile) |

GitHub CI (`.github/workflows/ci.yml`) runs the lint, Psalm, PHPUnit on PHP 8.2 to 8.5, the build and the e2e suite on Nextcloud 34
and 35.

### Releasing

Bump `<version>` in `appinfo/info.xml` and `CHANGELOG.md`. The store screenshots in `docs/screenshots` are regenerated with
`node scripts/screenshots.mjs` (it replaces the dev admin's data with English demo data).

`make package` builds the front end and produces `build/vitalog-<version>.tar.gz`: only what runs on a server, under a top-level
`vitalog/` folder. Pushing a tag `vX.Y.Z` (matching `<version>` in `appinfo/info.xml`) runs `.github/workflows/release.yml`, which
builds the same archive and attaches it to a GitHub release.

### Architecture

- `lib/Db`: entities and mappers (`vitalog_*` tables, migration in `lib/Migration`). Every query is filtered by `user_id`.
- `lib/Service`: business logic. `CalendarSyncService` mirrors an appointment into the target calendar; a synchronisation failure is
  logged but never prevents saving.
- `lib/Calendar`: `CalendarGateway` (interface) and `DavCalendarGateway`. The public `OCP\Calendar` API can only **create** an event,
  so updating and deleting go through `OCA\DAV\CalDAV\CalDavBackend` (an internal class, though used by the official apps), isolated
  behind the interface.
- `lib/Contacts`: `ContactGateway` and `ManagerContactGateway` (public `OCP\Contacts\IManager` API, address books of the signed-in
  user); `lib/ContactsMenu`: the "Vitalog appointments" action on linked cards.
- `lib/Controller`: OCS API under `/ocs/v2.php/apps/vitalog/api/` (`appointments`, `practitioners`, `places`, `labels`, `settings`,
  `appointments/{id}/attachments`). `PUT` replaces the whole object.
- `src`: Vue 3 + `@nextcloud/vue` 9 + Pinia (`stores/data.js`), Vite build (`@nextcloud/vite-config`).

## Calendar integration and Nextcloud's public API

In the settings, the user **links** one calendar (and chooses the import options). Once linked, the calendar cannot be changed
directly: the user must **unlink** it first, and chooses at that point whether the events Vitalog wrote stay in the calendar or are
deleted. Appointments are never deleted by unlinking, and they keep the UID of their event: linking the same calendar again finds the
events that were kept and updates them instead of duplicating them.

While a calendar is linked, Vitalog writes to it only when an **appointment** is created, changed or deleted: it creates the event,
updates it, or deletes it (for an appointment imported from the calendar, it is the user's own event that gets updated). Renaming or
deleting a practitioner, a place or a label does **not** touch the events already written; they show the new values the next time the
appointment itself is saved. Unlinking with "delete" removes the events of the appointments still linked; an event kept earlier, whose
appointment was deleted while unlinked, stays in the calendar.

The App Store guidelines say apps should use only Nextcloud's public API. The public `OCP\Calendar` API can only **create** an event
(creating one whose UID already exists fails), and can neither update nor delete one. Updating and deleting therefore go through
`OCA\DAV\CalDAV\CalDavBackend`, an internal class of the DAV app that the official apps also use. This is the only non-public API
Vitalog uses; it is isolated behind `lib/Calendar/CalendarGateway.php` (implemented by `DavCalendarGateway`), which is the one file to
adapt if a Nextcloud release changes it. A calendar failure is logged and never prevents an appointment from being saved. The CI
exercises this integration against Nextcloud 34 and 35.

## Known limitations (dependencies)

Observed with `@nextcloud/vue` 9.13.1; not fixed in this repository:

- the tab title shows `[object Object]`: `NcContent` provides `appName` as a `computed` while `useAppName()` expects a string;
- the `splitpanes` separator of `NcAppContent` is focusable with `role="separator"` but has no `aria-valuenow` (axe: `aria-required-attr`);
- `NcSelect` dims the selected value with `opacity` (2.4:1 contrast) and its spinner is an unnamed `role="img"` (axe: `color-contrast`,
  `role-img-alt`). Fixed upstream by [nextcloud-vue#9098](https://github.com/nextcloud-libraries/nextcloud-vue/pull/9098).
  Workarounds here, to remove once a release containing it is installed: the global `<style>` block in `src/App.vue`, and
  `.exclude('.loading-icon')` in `e2e/ui/contacts.spec.js`;
- `NcModal` ignores Escape when the focus is in an input field: a form does not close with Escape from a field.

## License

AGPL-3.0-or-later
