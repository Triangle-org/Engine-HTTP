# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-11-20

### Changed
- Require Triangle Engine 9, Router 2, and Middleware 2 for Localzet Server 7.
- Preserve status, headers, and body when a handler returns a Localzet Server HTTP response.
- Read default response headers from the Engine 9 `app.http_headers` setting.
- Remove permissive wildcard CORS defaults; applications must configure allowed origins explicitly.

### Added
- Add regression coverage for response conversion, default headers, and a loopback HTTP request on Server 7.
- Add bilingual installation, migration, and security instructions.
- Expand CI coverage to PHP 8.1–8.5 and audit dependencies.

### Compatibility
- This is a major release. The 2.x line remains for applications using the previous Engine compatibility line.

## [1.0.0] - 2025-01-XX - LTS Release

### Added
- Initial LTS release
- Full documentation
- HTTP application layer
- Request handling
- Response generation
- Static file serving
- PHP file execution (optional)
- Automatic routing
- Controller-based routing

### Changed
- Removed deprecated `#[\AllowDynamicProperties]` from Request class
- Improved request processing
- Enhanced static file handling

### Security
- Enhanced URI validation
- Improved file access control

---

## [Unreleased]

### Planned
- HTTP/2 support improvements
- Request validation enhancements
- Performance optimizations

