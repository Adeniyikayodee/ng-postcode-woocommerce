=== Adeniyikayode Nigerian Postcode for WooCommerce ===
Contributors: adeniyikayode
Tags: woocommerce, nigeria, postcode, postal code, zip code
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Nigeria's NIPOST digital postcode at checkout: checked, tidied, found from the customer's location, and usable in shipping zones.

== Description ==

Nigeria's National Digital Alphanumeric Postcode System (NDAPS) gives every building an 11-character code such as `EK-01-A03-FK-01`: state, LGA, district, area, and building unit. It is Nigeria's new postal code, or zip code.

This plugin does not add a field. It teaches WooCommerce's own postcode field about Nigerian codes, so the postcode reaches everything that already reads that field: orders, emails, exports, the REST API, couriers, and shipping zones.

* **Shows the field.** WooCommerce hides the postcode for Nigerian addresses. The plugin shows it and keeps it optional.
* **Checks the code.** A code with the wrong shape is refused, in both the classic and the block checkout.
* **Tidies the code.** `ek 01 a03 fk 01` and `EK01A03FK01` are both stored as `EK-01-A03-FK-01`.
* **Works with shipping zones.** Add a postcode rule such as `EK-01*` to a zone to price delivery for one LGA.
* **Find my postcode.** A button fills the field from the customer's location, and asks them to confirm it.
* **Check orders.** After each order, a note says whether NIPOST has a building for the postcode.

The first four need no account or key. The last two need a key from the NIPOST developer dashboard.

This is an independent project. It is not made or endorsed by NIPOST or by WooCommerce.

== External services ==

This plugin connects to the NIPOST Postcode API at `api.postcode.gov.ng`, and only after you enter an API key in its settings.

* When a customer presses "Find my postcode", the latitude and longitude from their browser are sent, with your key, to find the nearest building. The plugin does not store or log the location.
* After an order is placed, if "Check orders" is on, the order's postcode is sent, with your key, to ask whether it belongs to a building. Nothing else about the order or the customer is sent.

The service is provided by the Nigerian Postal Service: [terms](https://postcode.gov.ng/terms) and [privacy policy](https://postcode.gov.ng/privacy).

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. The postcode field now appears for Nigerian addresses at checkout. No settings are needed for that.
3. To switch on "Find my postcode" and "Check orders", go to WooCommerce, Settings, General, and enter your NIPOST API key under "Nigerian postcode".

== Frequently Asked Questions ==

= Is the postcode required at checkout? =

No. Most customers do not know their code yet, so the field is optional.

= How do I price delivery by area? =

In a shipping zone, add a postcode rule with a prefix and a star, written with its hyphens: `EK-01*` for one LGA, or `EK-01-A03*` for one district.

= Where does my API key go? =

It is stored on your server and used only there. You can instead define `NG_POSTCODE_API_KEY` in `wp-config.php`, which keeps it out of the database.

= Can a visitor use up my NIPOST quota? =

"Find my postcode" refuses locations outside Nigeria without asking NIPOST, allows each visitor at most 5 searches a minute, and allows the store at most 600 an hour.

== Screenshots ==

1. The checkout after "Find my postcode" fills the field.
2. The settings, under WooCommerce, Settings, General.
3. The order note once NIPOST confirms the postcode.

== Changelog ==

= 0.1.2 =
* Added screenshots and a link on the author's name.

= 0.1.1 =
* Renamed to Adeniyikayode Nigerian Postcode for WooCommerce.

= 0.1.0 =
* First release.
