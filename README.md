# Tour Service Date Selector

WooCommerce-based WordPress plugin to make products bookable with date and time selection.

## Overview

Tour Service Date Selector adds booking date and time fields to WooCommerce products using WordPress and WooCommerce hooks for broad compatibility with themes, builders, and third-party plugins.

This plugin supports:

- Open-dated products (no date/time selection)
- Date-only booking products
- Date-and-time booking products with time slots
- Simple and variable product booking flows

## Key Features

- Product-level service type configuration
- Inline date picker for booking dates
- Time-slot generation based on weekly schedules
- Variation-level overrides for service type and schedule
- Booking metadata shown in cart, checkout, order admin, emails, and My Account
- HPOS compatibility declaration

## Security and Compatibility

The plugin follows WooCommerce and WordPress best practices:

- Nonce verification on booking-related requests
- Input sanitization and output escaping
- Server-side validation for add-to-cart and checkout
- Hook/filter-based integration for maximum compatibility
- OOP-based class structure for maintainability

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 7.0+

## Installation

1. Copy this plugin folder to `/wp-content/plugins/tour-service-date-selector`.
2. Activate the plugin in WordPress Admin under Plugins.
3. Open a WooCommerce product and configure booking behavior in the Tour Service Settings tab.

## Development Notes

- Follow project standards in [PROJECT_INSTRUCTIONS.md](PROJECT_INSTRUCTIONS.md).
- Copilot workspace guidance is in [.github/copilot-instructions.md](.github/copilot-instructions.md).

## Author and Ownership

- Author: Imran Bajwa
- Company: INT SERVICES LLC
- Copyright: All rights reserved

## License

GPL-2.0-or-later. See plugin header and WordPress license references for details.
