# ADR-002: SQLite + coffee-shop toy domain for the standalone runtime

**Status:** Accepted
**Date:** 2026-08-22

## Context

Sub-project A needs a data store for its sample reports. Two axes: **database** (SQLite / MySQL / Postgres / in-memory) and **domain** (mirror foreUP's business examples / neutral toy / both).

## Decision

- **Database:** SQLite 3, file-backed at `writer-app/data/report-writer.sqlite`
- **Domain:** Neutral coffee-shop point-of-sale (categories, items, staff, orders, order_items, payments, template_drafts)

## Rationale

**SQLite:**

- Zero setup — one file, no server, no auth, no port
- PDO ships with every PHP install
- Fast enough for a demo with tens of thousands of seeded rows
- Easy to reset (`rm data/*.sqlite && seed`)
- Same test-DB pattern that most Laravel/Symfony projects use for their PHPUnit suites

**Coffee-shop domain:**

- Neutral — no foreUP-specific vocabulary in the demo (per the foreUP-neutralisation goal captured in [ADR-009](009-library-rename-to-edgecase.md))
- Point-of-sale data naturally exercises every report shape the pipeline supports: flat detail, single-level grouping (by category), nested grouping (category → item), aggregates (sum totals), an "open state" (unclosed tabs) analogous to unsettled-captures
- Legible to any reader in under a minute — no need to explain business rules
- Fixed `mt_srand(1)` in the seed makes runs deterministic → snapshot tests + screenshots are stable

## Rejected alternatives

- **MySQL / Postgres.** Would require a container, a service, credentials, migrations. Overkill for a testbed and adds "did you `docker compose up mysql`?" friction.
- **In-memory SQLite.** Fine for tests but the builder UI needs persistent template drafts; file-backed SQLite serves both.
- **Mirror foreUP's business examples** (unsettled-captures, sales-by-category as first-class reports). Would tie the demo to foreUP vocabulary, harder to hand off as a neutral product. The coffee-shop domain covers the same report shapes without the coupling.
- **Bookstore / library / other toy.** Coffee-shop hits detail + grouped + summary shapes most naturally with "orders" as the parent aggregate. Alternatives would work but felt less natural.

## Consequences

- SQLite is a runtime dependency (PHP `pdo_sqlite` extension; universally present)
- The `writer-app/database/seed.php` script is the source of truth for what data exists at demo time; changes to schema require re-seeding
- Anyone porting the sample reports back to foreUP swaps the `SqliteXProvider` classes for `DbalXProvider` — same `ReportDataSourceInterface` contract
