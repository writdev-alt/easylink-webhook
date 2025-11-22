# Changelog

All notable changes to this project will be documented in this file.

## 2025-11-08
### Fixed
- Reworked webhook queue handling by introducing dedicated jobs for storing payloads and updating transaction stats, improving reliability of asynchronous processing (`cf553db`).
- Corrected payment amount calculations in the payment handler to align with gateway expectations (`9de4b79`).

## 2025-11-07
### Added
- Introduced console commands for recalculating transaction statistics and triggering webhook dispatches, replacing the previous event-driven flow. Added the `TransactionStat` model and bootstrap registrations (`34e9214`).

### Removed
- Deprecated the old statistic generation event/listener pipeline and redundant scaffold migrations to simplify deployment (`34e9214`).

## 2025-11-06
### Added
- Added `GenerateStatisticListener` to keep transaction metrics up to date following payment lifecycle events (`3378975`).

### Fixed
- Adjusted statistic listener registration and logic for the main workflow (`a47e32f`).
- Removed leftover Netzme test hooks and secrets; centralized webhook secret configuration via `config/webhook-server.php` (`320b1b0`, `a16e816`, `4151e9c`).

## 2025-11-05
### Added
- Enabled Laravel Octane configuration and dependency updates (`1cde78f`).
- Expanded wallet infrastructure with Elasticsearch indexing, a bulk-indexing artisan command, and richer IPN tests covering wallet/webhook flows end-to-end (`8040024`).
- Added comprehensive unit coverage for domain models, payment gateways, and transaction services to support withdraw scenarios (`7ac0f40`).

### Fixed
- Iterated on RRN response handling, wallet balance management, and webhook service adjustments for more robust reconciliation (`ffea7fe`, `72cadf1`, `33b5876`, `7f6bb82`, `193ef27`).
- Patched Easylink withdraw webhook handling to return correct payloads (`95bfaa1`).

## 2025-11-03
### Fixed
- Hardened wallet rounding logic with new unit coverage (`4e17b07`).
- Applied Pint formatting and minor consistency fixes across models, handlers, and migrations (`784d613`).
- Tweaked console handler logic for deposit and withdraw flows (`f6c4453`, `564add8`).

## 2025-11-02
### Added
- Initial project scaffold delivering webhook ingestion, wallet and transaction domain models, Easylink and Netzme payment gateway integrations, along with API routing, configuration, and a comprehensive automated test suite (`e2e7c25`).


