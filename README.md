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

## Requirements

WordPress 6.7, WooCommerce 10.0, and PHP 7.4, or later. It is tested on PHP 7.4 to 8.5.

## Install

Download the zip from [Releases](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/releases), then in WordPress go to **Plugins**, **Add New Plugin**, **Upload Plugin**. No settings are needed.

## Planned

- **Find my postcode:** a button that fills the field from the customer's location.
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
