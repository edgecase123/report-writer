# ADR-003: Docker Compose orchestration, non-conflicting ports, namespaced container names

**Status:** Accepted
**Date:** 2026-08-22

## Context

Sub-project A needs a local run model. Considered: composer + npm scripts (host-native), Docker Compose, both. The user's leagues project already runs a Docker stack on this workstation using standard ports (`:8080` for the web app, `:5173` for Vite).

## Decision

- **Local run model:** Docker Compose (`docker compose up`)
- **Ports:**
  - `:8090` — PHP web (Slim 4 in Apache)
  - `:5174` — Vite dev server
- **Container names:** `report-writer-*` (specifically `report-writer-php`, `report-writer-vite`)

## Rationale

- **Docker Compose** removes the "do you have PHP 7.4 installed?" friction and matches how the sibling leagues stack is orchestrated locally — familiar tooling for the operator
- **Non-conflicting ports** — leagues uses `:8080` (web) and `:5173` (Vite); overlap would prevent both stacks from running simultaneously. `:8090` and `:5174` are the natural non-collision choices adjacent to leagues' defaults, and are unlikely to conflict with other common tools
- **Namespaced container names** — `docker ps` needs to be readable when both stacks are running; unnamespaced names like `php` or `vite` become ambiguous

## Rejected alternatives

- **Composer + npm scripts only.** Fastest iteration but requires PHP 7.4 + Node on the host. Since the sibling project already uses Docker, forcing dual toolchains was worse than reusing the pattern.
- **Both** (Compose + host scripts). Sounds nice but doubles the surface to keep in sync; a Dockerfile that also runs standalone is not free maintenance.
- **Standard ports (`:8080`, `:5173`).** Conflicts with leagues.

## Consequences

- Anyone contributing needs Docker Desktop or equivalent installed
- `docker compose up` is the canonical "run it" command; `composer install` and `npm install` run inside their respective containers via `docker exec`
- The port choices are documented so users don't need to guess when opening a browser
- The container-name convention (`report-writer-*`) applies to any future service added to the compose stack
