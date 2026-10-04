# NG Postcode for WooCommerce

[![CI](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/actions/workflows/ci.yml)

A WooCommerce plugin for Nigeria's National Digital Alphanumeric Postcode System (NDAPS), the building-level postcode NIPOST launched in October 2026. A postcode has 11 characters in five segments, written `EK-01-A03-FK-01`: state, LGA, district, area, and building unit.

The plugin does not add a field. It teaches WooCommerce's own postcode field about Nigerian codes, so the postcode reaches everything that already reads that field: orders, emails, exports, the REST API, couriers, and shipping zones.

## What it does

- **Shows the field.** WooCommerce hides the postcode for Nigerian addresses. The plugin shows it and keeps it optional, as most customers do not know their code yet.
- **Checks the code.** A code with the wrong shape is refused at checkout, in both the classic and the block checkout. The check is offline and instant.
- **Tidies the code.** `ek 01 a03 fk 01` and `EK01A03FK01` are both stored as `EK-01-A03-FK-01`.
- **Works with shipping zones.** Add a postcode rule such as `EK-01*` to a zone to price delivery for one LGA, or `EK-01-A03*` for one district. Write the prefix with its hyphens.

A code with the right shape is not necessarily assigned to a building. Confirming that needs the NIPOST API, which is planned below.

## Find my postcode

The plugin adds an endpoint, `POST /wp-json/ng-postcode/v1/locate`, that takes a latitude and longitude and returns the postcode of the nearest building and how far away it is. The checkout button that calls it is the next release.

To switch it on, go to **WooCommerce**, **Settings**, **General**, and enter a key from the [NIPOST developer dashboard](https://dashboard.postcode.gov.ng) under **Nigerian postcode**. You can instead define `NG_POSTCODE_API_KEY` in `wp-config.php`, which keeps the key out of the database.

The endpoint is public, because shoppers are not logged in. Three limits protect your NIPOST quota: a location outside Nigeria is refused without asking NIPOST, each visitor gets 5 calls a minute, and the store gets 600 an hour.

## What leaves your site

Only "find my postcode" contacts another service. When a customer uses it, their latitude and longitude are sent to NIPOST's API at `api.postcode.gov.ng`, with your key, to find the nearest building. The plugin does not store or log the location, and it returns only the postcode and distance to the browser. See NIPOST's [privacy policy](https://postcode.gov.ng/privacy). Checking and tidying a typed postcode happens on your server and contacts nobody.

## Requirements

WordPress 6.7, WooCommerce 10.0, and PHP 7.4, or later. It is tested on PHP 7.4 to 8.5.

## Install

Download the zip from [Releases](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/releases), then in WordPress go to **Plugins**, **Add New Plugin**, **Upload Plugin**. No settings are needed.

## Planned

- **Find my postcode button:** fills the field at checkout from the customer's location.
- **Confirm after the order:** a free NIPOST lookup, recorded as an order note, that never blocks checkout.

## Development

```sh
composer install && composer test      # the postcode core, against the shared spec
sh tests/integration/run.sh 7.4        # the plugin inside WordPress and WooCommerce
```

The integration test needs Node 24; it starts a throwaway WordPress with [WordPress Playground](https://wordpress.github.io/wordpress-playground/), so no Docker or database is required.

The postcode rules come from [ng-postcode](https://github.com/Adeniyikayodee/ng-postcode), which has the same behaviour in Rust, Python, JavaScript, and Java. `spec/` is a copy of its shared test cases: do not edit it here. `sh scripts/spec_sync.sh` proves the copy is untouched, and a weekly job reports when upstream has moved on.

## License

GPL-2.0-or-later. An independent project, not affiliated with NIPOST or WooCommerce.
