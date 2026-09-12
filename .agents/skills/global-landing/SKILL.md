---
name: global-landing
description: "Change global-home routing, landing-page configuration, navigation, or rendering."
---

# Global landing page

Inspect `Module.php`, `src/Controller/`, `src/View/Helper/`, `src/Form/`, and `view/`.
Trace the bootstrap route override to its controller before changing navigation.

- `SETTING_USE_CUSTOM` is disabled by default. Preserve the core index when disabled and keep the
  override scoped to the global landing page; public-site and admin routes remain independent.
- Keep the landing page self-contained: configured featured sites, base-site navigation pages, logos,
  colors and footer content must not require a particular active theme.
- Validate configured resource IDs and handle deleted/inaccessible sites or pages gracefully.
  Generate links through existing route/helper code so subdirectory installs work.
- Preserve the existing color/URL helpers and intentional trusted administrator HTML boundaries;
  escape resource metadata for its output context.

Run relevant controller/helper tests and the full PHPUnit suite. For route/view changes, check enabled
and disabled states, a subpath deployment, missing configured resources, and keyboard navigation.
