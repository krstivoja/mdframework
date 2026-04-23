---
title: Changelog
layout: default
---

# Changelog

All notable changes to MD Framework are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- _Upcoming changes go here._

## [1.0.0] — 2026-04-23

### Added
- Initial public release.
- Flat-file content under `content/` with folder-based collections.
- YAML front matter support: `title`, `date`, `categories`, `tags`, `draft`, `excerpt`, plus arbitrary custom fields.
- URL routing: `/`, `/page`, `/folder`, `/folder/slug`, with `_index.md` override for archives.
- Post index + filter via global `posts()` helper.
- Per-page HTML cache (`cache/html/`) with automatic invalidation on source change.
- Admin UI at `/admin/` with EasyMDE editor, image uploads, CSRF protection, bcrypt-hashed credentials in `.env`.
- PHP template system with `render()` helper and `_layout.php` output-buffer pattern.

[Unreleased]: https://github.com/krstivoja/mdframework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/krstivoja/mdframework/releases/tag/v1.0.0
