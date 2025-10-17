# GlobalLandingPage (Omeka S Module)

GlobalLandingPage ships a complete, self-contained landing experience for Omeka S. When enabled, the module intercepts the global index route (`/`) and renders its own controller, layout, view templates, and static assets—no public site or theme dependency required.

## Features
- Toggle the bundled landing page on or off with a single checkbox in the module configuration.
- Override `omeka/index/index` with module-provided layout, header/footer partials, and hero/feature sections.
- Serve CSS, JavaScript, fonts, and imagery from the module’s `asset/` directory via `assetUrl()` helpers.
- Fall back to Omeka S’s default behaviour whenever the override is disabled.
- Built for Omeka S 4.x using Laminas best practices.

## Installation
1. Copy this directory into `modules/GlobalLandingPage` inside your Omeka S installation.
2. From the Omeka S admin dashboard, open **Modules** and install **GlobalLandingPage**.
3. The module installs with the override disabled so Omeka continues to use its default landing page until you opt in.

## Configuration
1. Navigate to *Modules → GlobalLandingPage → Configure*.
2. Check **Use custom landing page** to enable the override (leave it unchecked to retain the Omeka default).
3. Save. Requests to `/` immediately render the module’s landing page using the bundled layout and partials.

## Module Structure Highlights
- `Module.php` – boots the route listener, handles install/uninstall, and wires the configuration checkbox.
- `src/Controller/LandingController.php` – renders `view/omeka/index/index.phtml` with the custom layout.
- `view/layout/layout.phtml` and `view/common/*.phtml` – layout and reusable partials.
- `asset/` – static resources organised under `css/`, `sass/`, `js/`, `fonts/`, and `img/` for use with `assetUrl()`.
- `config/config_form.php` – single-checkbox admin configuration view.

## Development Notes
This repository includes optional helpers for Docker-based development, Composer tooling, and translation utilities inherited from the original template. The most relevant commands are:

- `make up` / `make down` – start and stop the local Docker stack.
- `make test` – run the PHPUnit suite in `test/`.
- `make package VERSION=x.y.z` – build a distributable ZIP while preserving the original version in `config/module.ini`.

Feel free to adapt or remove the development tooling to match your workflow.
