# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

