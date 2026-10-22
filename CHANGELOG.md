# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- The practitioner, place and notes of an appointment can be set or changed straight from its details, without opening the edit dialog.

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
