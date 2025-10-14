# GlobalLandingPage (Omeka S Module)

GlobalLandingPage allows administrators to override the Omeka S global landing page (`omeka/index/index`) with the `index.phtml` template provided by an installed theme. It adds a simple configuration screen where you can enable the override and choose an eligible theme.

## Features
- Toggle the landing page override on or off at any time.
- Automatically discover installed themes that include `view/omeka/index/index.phtml`.
- Update the Laminas `template_map` to load the selected theme when the override is enabled.
- Restore the default Omeka S behaviour whenever the override is disabled.
- Compatible with Omeka S 4.x.

## Installation
1. Copy the module directory into `modules/GlobalLandingPage` inside your Omeka S installation.
2. From the Omeka S admin dashboard, go to *Modules* and install **GlobalLandingPage**.
3. The module installs in a disabled state so Omeka S keeps its default landing page until you configure it.

## Configuration
1. Open *Modules → GlobalLandingPage → Configure*.
2. Check **Enable landing page override** to activate the feature.
3. Choose a theme from the dropdown. Only themes that expose `view/omeka/index/index.phtml` appear.
4. Save the form. The selected theme renders the global landing page immediately.

If you disable the override, the module removes its template mapping to restore the core Omeka S layout.

## Development Notes
This repository includes optional helpers for Docker-based development, Composer tooling, and translation utilities inherited from the original template. The most relevant commands are:

- `make up` / `make down` – start and stop the local Docker stack.
- `make test` – run the PHPUnit suite in `test/`.
- `make package VERSION=x.y.z` – build a distributable ZIP while preserving the original version in `config/module.ini`.

Feel free to adapt or remove the development tooling to match your workflow.
