# RH Hosting & Domain Reseller Module for WHMCS

A seamless WHMCS module designed to connect child reseller panels to a parent master panel for automated hosting provisioning, domain registration, and single sign-on form logins.

## Features
* Automated hosting service creation and domain registration.
* Direct cPanel & WHM auto-login form integration.
* Dynamic client area template switching (Hosting, VPS, Licenses).
* Parent reseller balance tracking widget for the admin dashboard.

## Installation
1. Download the latest release ZIP.
2. Upload the `modules/` directory into your WHMCS root installation folder.
3. Navigate to **Setup > Products/Services > Servers** and **Registrars** in your WHMCS admin panel to configure.

## Customization & Modification
You are free to fork, modify, and adapt this module to fit your infrastructure:
* **Templates:** Edit `.tpl` files under `modules/servers/rh_hosting_reseller/templates/` to change the client area look and feel.

## License
Distributed under the [MIT License](LICENSE). Feel free to use and modify for personal or commercial use.
