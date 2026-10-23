# Vitalog

A [Nextcloud](https://nextcloud.com) app to keep track of your medical appointments.

- **Appointments**: date, duration, reason, notes, practitioner and place. There is no status: "upcoming" and "past" only depend on the date.
- **Practitioners and places**: created once (or on the fly from an appointment) and reused everywhere.
  A practitioner can optionally be linked to a card of the **Contacts** app: the card is the source of truth for the name, specialty
  and phone number, the local copy stays usable if the card disappears, and the contacts menu offers a "Vitalog appointments" action.
- **Labels**: tags (with an optional colour) that bring appointments together across practitioners and places ("ankle", "blood
  donation"…). An appointment can carry several of them. The list can be filtered by label and by practitioner, and both filters
  combine (filter button next to the search field, or the "Labels" page). Labels show up as the categories of the calendar events.
- **Navigation**: the view, the selected appointment and the filters live in the URL
  (`?view=…&appointment=…&label=…&practitioner=…`); back / forward, reload and shared links work, and "back" closes an open dialog.
- **Documents**: prescriptions, reports, results… attached from your Nextcloud files.
- **Calendar**: your appointments appear in a read-only "Vitalog" calendar, in the Calendar app and in any CalDAV client, with a
  reminder the day before. It is generated from your appointments: there is nothing to link or synchronise, and changing an
  appointment, a practitioner, a place or a label shows up right away. The events of an existing calendar can also be imported as
  appointments in one go (past ones included; recurring events are ignored, and later changes to the events are not reflected).
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

It drops the six `vitalog_*` tables and the users' settings, so the "Vitalog" calendar disappears with them. Attached files stay in
the users' files.

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
| `composer test` | PHPUnit: unit tests of the services (Vitalog calendar, import, attachments, cascades, validation) |
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
- `lib/Service`: business logic, including the one-shot import of a calendar (`CalendarImportService`).
- `lib/Dav`: the read-only "Vitalog" calendar (`CalendarPlugin`, `Calendar`, `CalendarObject`), declared in `appinfo/info.xml`.
- `lib/Calendar`: `EventFactory` and `IcsBuilder` build the events of the Vitalog calendar; `CalendarGateway` and
  `DavCalendarGateway` read the user's calendars for the import.
- `lib/Contacts`: `ContactGateway` and `ManagerContactGateway` (public `OCP\Contacts\IManager` API, address books of the signed-in
  user); `lib/ContactsMenu`: the "Vitalog appointments" action on linked cards.
- `lib/Controller`: OCS API under `/ocs/v2.php/apps/vitalog/api/` (`appointments`, `practitioners`, `places`, `labels`, `settings`,
  `appointments/{id}/attachments`). `PUT` replaces the whole object.
- `src`: Vue 3 + `@nextcloud/vue` 9 + Pinia (`stores/data.js`), Vite build (`@nextcloud/vite-config`).

## Calendar integration and Nextcloud's public API

Vitalog **does not write to the user's calendars**. It provides its own calendar, "Vitalog", the way the Deck app provides its
boards: a calendar plugin declared in `appinfo/info.xml` (`<sabre><calendar-plugins>`) adds it to the user's CalDAV home
(`/remote.php/dav/calendars/<user>/app-generated--vitalog--appointments/`). Its events are built on each request from the
appointments, in the user's time zone, with a reminder the day before (`VALARM`, triggered by the clients that sync the calendar;
Nextcloud's own reminder notifications only cover stored events). The calendar is read-only: creating, changing or deleting an event
from a client is refused. Only its owner sees it. Hiding it or changing its colour in the Calendar app works as for any calendar.

The import reads the events of one of the user's calendars once and creates the missing appointments; each appointment keeps the UID
and calendar of its source event so that importing again skips it. Nothing is written back to the source calendar.

The App Store guidelines say apps should use only Nextcloud's public API. Two internal APIs of the DAV app are used, both also used by
official apps: the calendar plugin interface (`OCA\DAV\CalDAV\Integration\ICalendarProvider` and `ExternalCalendar`, as Deck does),
isolated in `lib/Dav`, and `OCA\DAV\CalDAV\CalDavBackend` to read a calendar for the import, because the public `OCP\Calendar` API
does not list all of a calendar's events. The latter is isolated behind `lib/Calendar/CalendarGateway.php`. The CI exercises both
against Nextcloud 34 and 35.

## Known limitations (dependencies)

Observed with `@nextcloud/vue` 9.14.0; not fixed in this repository:

- the `splitpanes` separator of `NcAppContent` is focusable with `role="separator"` but has no `aria-valuenow` (axe: `aria-required-attr`);
  the accessibility tests exclude it;
- `NcModal` ignores Escape when the focus is in an input field: a form does not close with Escape from a field;
- on a narrow screen Nextcloud's own styles (`core/css/mobile.scss`) remove the `max-height` of the appointment list while the
  `NcAppContent` wrapper does not scroll, so the list could not be scrolled: worked around in `src/views/AppointmentsView.vue`.

## License

AGPL-3.0-or-later
