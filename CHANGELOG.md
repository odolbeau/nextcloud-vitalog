# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- Calendar: instead of writing events into a calendar you link, Vitalog now provides its own read-only "Vitalog" calendar, generated from your appointments (like Deck's boards), in your time zone and with a reminder the day before. There is nothing to link or unlink any more, and renaming a practitioner, a place or a label shows up right away. Events already written into a linked calendar by an earlier version are left there untouched.
- Calendar import is now a one-shot action from the settings (past events included, already imported events skipped); events added to a calendar afterwards are no longer imported automatically.
- When picking a practitioner for an appointment, the ones with the most recent appointment (by its date) come first; those without any follow by name.
- The practitioners page groups them by specialty (regardless of case), those without one last.

### Fixed
- The appointment list can be scrolled again on a narrow screen (Nextcloud's mobile styles let it grow past the screen).

### Added
- A practitioner can have a usual place. Picking them for an appointment fills an empty place with it (in the form, also when the place came from the previously picked practitioner); a place picked by hand is never replaced. On upgrade, practitioners whose appointments were all at one place get it as their usual place.
- The appointment list shows a heading at each change of year, kept at the top while scrolling.
- The title (reason) of an appointment can be edited straight from its details.
- Practical information on places (intercom, door code, floor…), shown in the appointment details.
- An optional price on appointments, set or changed straight from the details like the notes. It is stored in cents without a currency.
- The practitioner, place and notes of an appointment can be set or changed straight from its details, without opening the edit dialog.
- The specialty of a practitioner is picked among the ones already used, or typed to add a new one.
- Searching the appointment list and the selects ignores accents and case ("helene" finds "Hélène").
- The labels of an appointment can be added, created and removed from its details.
- The label color is picked with Nextcloud's color picker (the one of the Calendar app), with any color allowed; the label text turns black or white to stay readable.

## 0.1.0

First release.

### Added
- Appointments with date, duration, reason, notes, practitioner and place; "upcoming" and "past" depend only on the date.
- Practitioners and places, created once or on the fly, reused everywhere.
- Optional link of a practitioner to a card of the Contacts app, with a "Vitalog appointments" action in the contacts menu.
- Labels (optional colour) that group appointments across practitioners and places, with a label and a practitioner filter.
- Documents attached from the user's Nextcloud files.
- Calendar mirroring with a reminder the day before. A calendar is linked in the settings and cannot be changed without unlinking it
  first; unlinking keeps or deletes the events, and linking the same calendar again finds them again. Only creating, changing or
  deleting an appointment writes to the calendar. Optional import of events added to the calendar, and one-shot import of the events
  already in it, when linking. Labels are written as event categories.
- The view, selection and filters live in the URL: browser back / forward, reload and shared links work.
- `occ vitalog:purge` erases all the data the app stored, to be run before removing it.
- English and French interface.
