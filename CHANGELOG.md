# Changelog

All notable changes to this project will be documented in this file.

The format is loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project is pre-1.0 and does not yet follow semantic versioning.

## Unreleased

### Changed

- **Library CSS class prefix renamed from `fu-` to `rw-`.** The `Renderer\HtmlRenderer`
  now emits `rw-page`, `rw-el`, `rw-band-overlay`, `rw-band-overlay-<type>`, and
  `rw-band-<type>` classes on the report HTML it renders. The `rw-` prefix matches
  the PHP namespace (`ReportWriter\`) and the published Composer package name
  (`edgecase123/report-writer`).

  **Breaking change for downstream CSS consumers:** any consumer application that
  styles report elements via `.fu-*` selectors must update those selectors to
  `.rw-*`. Bundled test snapshots (`writer-app/tests/Snapshots/*.html`) and the
  Vue viewer (`frontend/src/components/ReportCanvas.vue`) have been updated in the
  same change.

  This change lands pre-1.0 with no compatibility shim.
