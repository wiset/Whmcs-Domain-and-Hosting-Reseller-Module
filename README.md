# RH Hosting & Domain Reseller Module for WHMCS

A seamless WHMCS module designed to connect child reseller panels to a parent master panel for automated hosting provisioning, domain registration, and single sign-on form logins. 

> **Important Note:** This module can only be connected to **RealHostPro**. To use this module, you must obtain your API credentials and API key directly from your RealHostpro account dashboard. https://realhostpro.com

## Features
* Automated hosting service creation and domain registration.
* Direct cPanel & WHM auto-login form integration.
* Dynamic client area template switching (Hosting, VPS, Licenses).
* Parent reseller balance tracking widget for the admin dashboard.

## Installation & Configuration
1. Download the latest release ZIP from GitHub.
2. Upload the `modules/` directory into your WHMCS root installation folder.
3. Log into your RealHost account to retrieve your **API Key**.
4. Navigate to **Setup > Products/Services > Servers** (for hosting) and **Registrars** (for domains) in your WHMCS admin panel, add the module, and input your RealHost credentials and API key.

## Customization & Modification
You are free to fork, modify, and adapt this module to fit your infrastructure:
* **Templates:** Edit `.tpl` files under `modules/servers/rh_hosting_reseller/templates/` to change the client area look and feel.

## License
Distributed under the [MIT License](LICENSE). Feel free to use and modify for personal or commercial use.
