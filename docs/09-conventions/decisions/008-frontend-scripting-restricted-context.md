# ADR-008: Frontend scripting in scope with restricted-context capability grant

**Status:** Accepted
**Date:** 2026-08-22
**Related:** [`docs/architecture/user-scripting.md`](../../architecture/user-scripting.md), [ADR-007](007-imports-over-directory-discovery-for-scripts.md)

## Context

The user-scripting future requirement has two surfaces: backend-authored (server-trusted, drops files into a scripts pool) and frontend/template-authored (untrusted, embedded inline in JSON templates). Initial framing had the template surface deferred entirely. During the session the framing evolved:

1. Backend primary, template deferred
2. Both surfaces in scope, template capabilities restricted (no third-party API retrieval)
3. Trust boundary is "what the backend has made available to this report," not "who authored the script"

## Decision

**Frontend/template scripting is in scope alongside backend scripting.** The two surfaces share the same sandbox runtime; what differs is the **capability grant** — the set of objects and functions injected into the sandbox environment for each context.

**Frontend/template scripts:**

- CAN read the current row, aggregate rows, params, group value, and named data sources registered for the report
- CAN invoke any backend-registered script the report has been granted access to (formatters, hooks, data source accessors)
- CAN operate on the objects those backend scripts return (row values, aggregate results)
- CAN use safe stdlib: math, string manipulation, date parsing, JSON serialization
- CANNOT make network calls of any kind (`fetch`, `curl_*`, `Http::*`, `XMLHttpRequest`)
- CANNOT access data sources not registered for this report
- CANNOT access filesystem, environment, process, or any I/O primitive
- CANNOT register new backend-scope extension points

**Trust boundary:** at the level of "what backend has made available to this report." Not at the level of "who authored the script."

## Rationale

Restricting the template surface to a read-only context over already-approved data collapses the threat model to DoS-shaped (CPU / memory / output-size / preview-endpoint abuse) rather than exfiltration-shaped:

- Frontend script can only read what the report already renders → **no data exfiltration beyond what the request was already going to expose**
- No network → no exfiltration to third-party endpoints
- No cross-report data-source access → no lateral reach into other reports' or tenants' data

The invocation surface a template gets for a backend script is what the *script's contract returns* — the already-computed rows, the formatted string, the transformed band. The template cannot re-parameterize the underlying I/O. `context.dataSource('sales').rows()` returns *the rows this report already fetched with its own params*, not `.query(sql)`.

Two-layer invariant: **accessors over already-approved data are permitted; primitives for new I/O are denied.**

## Rejected alternatives

- **Template surface deferred entirely.** The template surface is what unlocks operator-authored logic without a code deploy — deferring forever means the JSON template's power tops out well below what real reporting needs
- **Capability tier based on script author (backend author = full; template author = restricted).** Confusing — a template importing a formatter would fail if the formatter's author "was" a backend developer. The cleaner rule is: capability tier is set by the invoking context, not by the script's origin
- **Frontend authored data sources / param transforms.** Rejected. Those seams require I/O to fulfill their contract; the frontend sandbox never grants I/O. Provider-shaped scripts are backend-only for authoring even under this decision

## Consequences

- Same sandbox runtime, two capability contexts (backend vs frontend/template)
- `context.dataSource(name)` accessor injected into template sandbox exposes read-only access to already-registered data sources for the report; throws if `name` isn't registered
- Provider-shaped seams (data sources, param transforms with I/O, post-render rewrites) remain backend-authored-only. But they're referenceable from templates via imports — the report can name and use them, just not define new ones
- Preview endpoint (`POST /api/preview`) becomes the primary place hostile template scripts would be exercised; rate-limiting matters there specifically
- Security-scanner Rule R5 gets a subclause allowing the restricted-context sandbox path
