# TICKET-008: Add `vue-tsc --noEmit` step to `npm run build`

**Priority:** Medium
**Source:** frontend-designer audit (2026-08-22) — 🟡 Deviation from role canon
**Scope:** `frontend/package.json`, potentially `frontend/tsconfig.json`

## Problem

`tsconfig.json` has `strict: true` and the codebase uses `<script setup lang="ts">` throughout — but there is no type-check step in `package.json`. Vite's default `build` command runs the Vue SFC plugin which transpiles TypeScript syntax but does NOT enforce type checking. Type errors slip through the build.

The recent fix for `e.message` on `unknown` catch clause in `ReportCanvas.vue:45` would have been caught by `vue-tsc` at build time.

## Proposed fix

Add `vue-tsc` as a devDependency and wire it into `npm run build`:

```json
{
  "devDependencies": {
    "vue-tsc": "^2.0.0"
  },
  "scripts": {
    "typecheck": "vue-tsc --noEmit",
    "build": "npm run typecheck && vite build"
  }
}
```

`vue-tsc` respects `tsconfig.json` and understands `.vue` SFC types.

## Acceptance criteria

- [ ] `vue-tsc` added to devDependencies
- [ ] `npm run typecheck` script defined
- [ ] `npm run build` runs typecheck before vite build; fails the build on type errors
- [ ] Existing TypeScript sources pass `vue-tsc --noEmit` (may reveal latent issues to fix in the same PR)

## Notes

- If existing sources have latent type errors that surface, fix them in the same PR — otherwise the type-check step is toothless and gets disabled next time it fails.
- Check pinned versions align with the TypeScript version pinned by [Ticket 010 fix](../) — currently `^5.3.3` after the audit-pass correction. `vue-tsc@^2` requires TypeScript 5.x.
