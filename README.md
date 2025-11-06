# GlobalLandingPage (Omeka S Module)

GlobalLandingPage gives Omeka S installations a polished, self-contained landing experience. When enabled, the module intercepts the global index route (`/`), renders its own controller, layout, view templates, and static assets, and provides a curated front door for your collections—no public site or theme dependency required.

Use this module when you need:
- A global entry point that looks consistent across all public sites.
- A curated set of featured sites, navigation, and branding managed from a single form.
- Quick toggling between the custom landing page and the default Omeka S homepage without editing themes.

## Features
- Toggle the bundled landing page on or off with a single checkbox in the module configuration.
- Override `omeka/index/index` with module-provided layout, header/footer partials, and hero/feature sections.
- Serve CSS, JavaScript, fonts, and imagery from the module’s `asset/` directory via `assetUrl()` helpers.
- Fall back to Omeka S’s default behaviour whenever the override is disabled.
- Built for Omeka S 4.x following Laminas best practices.

## Installation
1. Copy this directory into `modules/GlobalLandingPage` inside your Omeka S installation.
2. From the Omeka S admin dashboard, open **Modules** and install **GlobalLandingPage**.
3. The module installs with the override disabled so Omeka continues to use its default landing page until you opt in.

## Configuration
Open *Modules → GlobalLandingPage → Configure* to manage the landing page. The form includes:

- **Use custom landing page** (`globallandingpage_use_custom`): Master switch that enables the module’s landing experience. Leave unchecked to keep the default Omeka homepage.
- **Featured sites** (`globallandingpage_featured_sites`): Multi-select list of sites to highlight in the featured panel. Uses Select2 for easier searching; order follows Omeka’s sort-by-title response.
- **Base site for navigation** (`globallandingpage_base_site`): Pick the site whose pages populate the global header navigation. Leaving it empty hides the navigation menu.
- **Navigation pages** (`globallandingpage_nav_pages`): After choosing a base site, select the pages (slugs) that should appear in the header menu. The list auto-refreshes when the base site changes.
- **Footer Copyright HTML** (`globallandingpage_footer_html`): Optional HTML snippet rendered inside the footer for legal notices or custom links.
- **Display top bar** (`globallandingpage_show_top_bar`): Adds a slim bar above the main header—ideal for alerts or institutional branding.
- **Top bar logo** (`globallandingpage_top_bar_logo`): Optional asset displayed within the top bar. A preview appears under the form when a logo is already stored.
- **Primary / Secondary / Accent color** (`globallandingpage_primary_color`, `..._secondary_color`, `..._accent_color`): Hex colour pickers that drive the header, buttons, and highlight accents.
- **Header logo, Header logo 2, Header logo 3** (`globallandingpage_logo_1`, `..._logo_2`, `..._logo_3`): Up to three assets shown side-by-side in the main header. Logo 1 is required for branding; logos 2 and 3 are optional extras (for partner seals, etc.). Existing selections are previewed below the form.

Logo previews refresh automatically when you save, giving a quick check on current branding assets.

## Module Structure Highlights
- `Module.php` – Boots the route listener, handles install/uninstall, and wires the configuration checkbox.
- `src/Controller/LandingController.php` – Renders `view/omeka/index/index.phtml` with the custom layout.
- `view/layout/layout.phtml` and `view/common/*.phtml` – Layout and reusable partials.
- `asset/` – Static resources organised under `css/`, `sass/`, `js/`, `fonts/`, and `img/` for use with `assetUrl()`.
- `config/config_form.php` – Admin configuration view that renders the form described above.

## Development Notes
This repository includes optional helpers for Docker-based development, Composer tooling, and translation utilities inherited from the original template. Handy commands:

- `make up` / `make down` – Start and stop the local Docker stack.
- `make test` – Run the PHPUnit suite in `test/`.
- `make package VERSION=x.y.z` – Build a distributable ZIP while preserving the original version in `config/module.ini`.

Feel free to adapt or remove the development tooling to match your workflow.