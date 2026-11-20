# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-20

### Changed
- Require Triangle Engine 9, Router 2, and Middleware 2 for Localzet Server 7.
- Convert Localzet Server HTTP responses to Triangle responses without losing status, headers, or body.
- Clear Engine request and connection context after handshake, frame, and close callbacks.
- Keep route callbacks out of the handshake and run route middleware before upgrade.

### Added
- Add a live loopback WebSocket handshake and frame exchange to the test suite.
- Add response compatibility, callback lifecycle, and PHP 8.1–8.5 CI coverage.
- Add bilingual install, lifecycle, security, and compatibility instructions.

### Compatibility
- This is a major release. The 1.x line remains for applications using the previous Engine compatibility line.

## [1.0.0] - 2025-01-XX - LTS Release

### Added
- Initial LTS release
- Full documentation
- WebSocket application layer
- Real-time connection management
- Connection UUID system
- Group messaging support
- Broadcast messaging support
- Route-based WebSocket handling

### Changed
- Replaced dynamic properties with static UUID mapping for better PHP 8.2+ compatibility
- Removed deprecated `#[\AllowDynamicProperties]` from Request class
- Improved connection management
- Enhanced message handling

### Fixed
- Fixed connection UUID handling to avoid dynamic properties
- Improved connection cleanup on disconnect

### Security
- Enhanced WebSocket handshake validation
- Improved connection security

---

## [Unreleased]

### Planned
- WebSocket compression support
- Connection authentication improvements
- Performance optimizations

