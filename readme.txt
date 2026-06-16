=== Tour Service Date Selector ===
Contributors:      yourname
Tags:              woocommerce, booking, tour, date, time, theme-park
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds service booking date and time selection to WooCommerce products for tour operators and theme parks.

== Description ==

Tour Service Date Selector adds a flexible booking date and time picker to any WooCommerce simple or variable product without requiring categories, tags, or third-party option plugins.

**Service Types**

* **Open Dated** — Works like a normal WooCommerce product; no date or time fields displayed.
* **Just Date, No Time** — Displays an inline Flatpickr calendar; customer must select a date before purchasing.
* **Date and Time** — Displays both an inline calendar and a time-slot selector driven by your weekly operating schedule.

**Weekly Schedule**

For each product you can configure per-weekday availability with start and end times. Unavailable days are automatically greyed out in the calendar. Time slots are generated in 15-minute intervals within the configured range.

**Variable Product Support**

Each product variation can override the parent's service type and schedule. The booking fields update instantly when a shopper selects a variation.

**Security**

Every add-to-cart request is validated on the server with nonce verification, sanitization, and escaping. Checkout validation runs a second pass to reject manipulated cart items.

**Booking Data**

Selected date and time appear in:
* Cart and checkout
* Admin order screen
* Customer confirmation emails
* My Account order history

== Installation ==

1. Upload the `tour-service-date-selector` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Edit any product, open the **Tour Service Settings** tab, and choose a Service Type.

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes. Each variation can have its own service type and weekly schedule that overrides the parent product.

= Which themes are supported? =

The plugin is theme-independent. It is tested with Shoptimizer, Storefront, Astra, Kadence, GeneratePress, OceanWP, Flatsome, and block themes.

= Is it compatible with Elementor? =

Yes. The plugin uses only WooCommerce hooks and never modifies theme templates. It works with Elementor and Elementor Pro.

= Is it HPOS compatible? =

Yes. WooCommerce High Performance Order Storage (custom order tables) compatibility is declared.

== Screenshots ==

1. Tour Service Settings product tab with weekly schedule.
2. Inline calendar on the single product page.
3. Date and time selector with time-slot dropdown.
4. Booking details displayed in the cart.
5. Booking details in the admin order screen.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
