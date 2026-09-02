# Changelog

All notable changes to Reviewer Certificate are documented in this file.

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

