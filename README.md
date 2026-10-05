# Nicode Resource Hub

An independent Joomla resource module and the practical project for the planned
Professional Joomla 6 Development course by Nicolau Roca.

This is educational pilot 0.1.0, not the complete product or a stable release.
One module instance displays one manually configured HTTPS resource. Multiple
instances can coexist. No custom component, database table or service is needed.

Tested locally with Joomla 6.1.4, PHP 8.4.26, MariaDB 11.8.9 and Cassiopeia on
Windows. See TESTED.txt for evidence boundaries and untested combinations.

Build from this repository directory with Python 3:

    python -B tools/build.py

This creates dist/mod_nicoderesources-0.1.0.zip and dist/manifest.json.
The generated module ZIP is installable; GitHub's whole-repository source ZIP
is not a Joomla extension installer. Python is only a build dependency.

In a disposable Joomla site, upload the module ZIP through System > Install >
Extensions. Open Nicode Resources under Content > Site Modules. Enter a resource
title, an HTTPS URL and optionally a plain-text description. Choose a template
position (sidebar-right in Cassiopeia), Published, Public and On all pages under
Menu Assignment, then save. Check the frontend. With Registered access an
anonymous visitor must not see it; Unpublished must hide it from everyone.
Invalid stored configuration produces no resource markup.

Reinstalling the same ZIP preserves instances. System > Manage > Extensions >
Uninstall removes the module and its instance settings. Use disposable data.

Run the helper/template tests using your local Joomla PHP executable:

    php tests/module.php /absolute/path/to/joomla

Expected result: JSON with 15 passed checks. Native installation and browser
checks are separate; this command does not modify the site.

Code: GPL-2.0-or-later, see LICENSE.txt. Copyright 2026 Nicolau Roca.
No code or dependency from Campus Engine or Nicode Web Monitor is included.
Never commit CMS configuration, credentials, databases or personal data.

Course programme: https://nicolauroca.dev/nicode-resource-hub/
Course chapters are being prepared separately and have not been released here.
