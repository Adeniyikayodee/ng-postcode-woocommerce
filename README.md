# NG Postcode for WooCommerce

[![CI](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/actions/workflows/ci.yml)

A WooCommerce plugin for Nigeria's National Digital Alphanumeric Postcode System (NDAPS), the building-level postcode NIPOST launched in October 2026. A postcode has 11 characters in five segments, written `EK-01-A03-FK-01`: state, LGA, district, area, and building unit.

The plugin does not add a field. It teaches WooCommerce's own postcode field about Nigerian codes, so the postcode reaches everything that already reads that field: orders, emails, exports, the REST API, couriers, and shipping zones.

## What it does

- **Shows the field.** WooCommerce hides the postcode for Nigerian addresses. The plugin shows it and keeps it optional, as most customers do not know their code yet.
- **Checks the code.** A code with the wrong shape is refused at checkout, in both the classic and the block checkout. The check is offline and instant. The block checkout also checks postcodes in the browser, where it still expects Nigeria's old six-digit code; the plugin corrects that, so a digital postcode is accepted there too.
- **Tidies the code.** `ek 01 a03 fk 01` and `EK01A03FK01` are both stored as `EK-01-A03-FK-01`.
- **Works with shipping zones.** Add a postcode rule such as `EK-01*` to a zone to price delivery for one LGA, or `EK-01-A03*` for one district. Write the prefix with its hyphens.

A code with the right shape is not necessarily assigned to a building. Only NIPOST knows that, which is what **Check orders** below is for.

## Find my postcode

Customers who do not know their code can press **Find my postcode** under the postcode field, in either checkout. The browser asks for their location, and the field is filled with the nearest building's code and a note such as "Nearest building: EK-01-A29-KR-36, about 16 m away. Check that it is yours." A phone's location can land on the building next door, so the customer confirms; nothing is submitted for them. Browsers share a location only on HTTPS sites.

Behind it is an endpoint, `POST /wp-json/ng-postcode/v1/locate`, that takes a latitude and longitude and returns only the postcode and its distance.

To switch it on, go to **WooCommerce**, **Settings**, **General**, and enter a key from the [NIPOST developer dashboard](https://dashboard.postcode.gov.ng) under **Nigerian postcode**. You can instead define `NG_POSTCODE_API_KEY` in `wp-config.php`, which keeps the key out of the database.

The endpoint is public, because shoppers are not logged in. Three limits protect your NIPOST quota: a location outside Nigeria is refused without asking NIPOST, each visitor gets at most 5 calls a minute, and the store at most 600 an hour.

## Check orders

After each order with a Nigerian postcode, the plugin asks NIPOST whether that code belongs to a building and adds the answer as an order note: confirmed, no building found, or could not be checked. It runs in the background after the order is placed, so the customer never waits on NIPOST and a failure there cannot stop a sale. It uses the same key, and a level 1 lookup, which is free and returns no personal data. Switch it off under the same settings.

## What leaves your site

Two features contact NIPOST's API at `api.postcode.gov.ng`, with your key, and only once you have entered one. When a customer presses "find my postcode", their latitude and longitude are sent to find the nearest building; the plugin does not store or log the location, and it returns only the postcode and distance to the browser. When orders are checked, the order's postcode is sent, and nothing else about the order or the customer. See NIPOST's [privacy policy](https://postcode.gov.ng/privacy). Checking and tidying a typed postcode happens on your server and contacts nobody.

## Requirements

WordPress 6.7, WooCommerce 10.0, and PHP 7.4, or later. It is tested on PHP 7.4 to 8.5.

## Install

Download the zip from [Releases](https://github.com/Adeniyikayodee/ng-postcode-woocommerce/releases), then in WordPress go to **Plugins**, **Add New Plugin**, **Upload Plugin**. No settings are needed.

## Development

```sh
composer install && composer test      # the postcode core, against the shared spec
sh tests/integration/run.sh 7.4        # the plugin inside WordPress and WooCommerce
npm ci && npx playwright install chromium
sh tests/browser/run.sh                # a shopper in a real browser, in both checkouts
```

The last two need Node 24; they start a throwaway WordPress with [WordPress Playground](https://wordpress.github.io/wordpress-playground/), so no Docker or database is required.

The postcode rules come from [ng-postcode](https://github.com/Adeniyikayodee/ng-postcode), which has the same behaviour in Rust, Python, JavaScript, and Java. `spec/` is a copy of its shared test cases: do not edit it here. `sh scripts/spec_sync.sh` proves the copy is untouched, and a weekly job reports when upstream has moved on.

## License

GPL-2.0-or-later. An independent project, not affiliated with NIPOST or WooCommerce.
