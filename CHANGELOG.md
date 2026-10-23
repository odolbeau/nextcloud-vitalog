# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed
- An event moved into the linked calendar from another one (changing an event's calendar in the Calendar app) is now imported too when importing is on; only newly created events were before.
- The appointment list can be scrolled again on a narrow screen (Nextcloud's mobile styles let it grow past the screen).
- Calendar events are no longer rewritten from scratch: only the title, location, description, labels and time Vitalog manages are updated, so an event's own time zone, reminders, attendees and other properties are kept. Events are written in the user's time zone instead of UTC (the Calendar app showed two times for each of them), and events written in UTC by an earlier version move to that time zone the next time they are saved.

### Added
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
- Practical information on places (intercom, door code, floor…), shown in the appointment details.
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
