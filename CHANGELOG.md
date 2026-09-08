# Changelog

All notable changes to Reviewer Certificate are documented in this file.

## [1.9.0] - 2026-09-08

### Added

- Added an optional public reviewer and editor statistics panel on the journal homepage.
- Added a responsive, accessible world map and ranked country chart without an external map service.
- Added aggregate totals for reviewers, completed reviews, editors, and represented countries.
- Added display controls, homepage placement, role filters, a custom panel title, and a privacy threshold.
- Added manager-facing country-data quality indicators and reviewer profile reminders for missing country data.
- Added an in-dashboard settings modal so configuration no longer opens as a separate OJS page.
- Added an Upgrade action for Journal Managers with version information and the official package workflow; Site Administrators continue to use OJS's native secured upgrade form.

### Changed

- Country values are validated against ISO 3166-1 alpha-2 codes already used by OJS registration and profile forms.
- Invalid or free-text country values are excluded instead of being guessed or merged incorrectly.
- Public statistics expose aggregate counts only; reviewer/editor names and contact details remain private.
- Updated frontend asset cache versions and plugin metadata to 1.9.0.

## [1.8.0] - 2026-09-02

### Security

- Replaced sequential public verification links with journal-specific HMAC tokens.
- Rejected legacy or incomplete verification URLs without exposing reviewer or manuscript data.
- Added `no-store`, `noindex`, `nofollow`, `noarchive`, and `no-referrer` protections to sensitive pages.
- Added additional journal-context checks and safer handling when a journal context is unavailable.

### Changed

- QR codes are now generated locally in the browser; verification URLs are no longer sent to QuickChart or another external QR service.
- Updated plugin and JavaScript version metadata to 1.8.0.
- Improved upload-directory creation during concurrent requests.
- Escaped the reviewer profile link before rendering it.

### Documentation

- Added GNU GPL v3 license information.
- Added security reporting guidance and third-party attribution.
- Updated installation, compatibility, privacy, and upgrade documentation.

### Upgrade notice

QR codes created by version 1.7.0 used legacy verification URLs without a security token. Reviewers should open and save their certificates again after upgrading to 1.8.0.

## [1.7.0] - 2026-08-30

- Added the integrated Certificates tab, search, and client-side pagination.
- Added certificate image and signatory settings.
- Added certificate printing, QR verification, favicon support, and theme matching.
