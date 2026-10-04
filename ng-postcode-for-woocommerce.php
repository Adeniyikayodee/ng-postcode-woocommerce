<?php
/**
 * Plugin Name: NG Postcode for WooCommerce
 * Plugin URI: https://github.com/Adeniyikayodee/ng-postcode-woocommerce
 * Description: Nigeria's NIPOST digital postcode at checkout: checked, tidied, and usable in shipping zones.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0
 * WC tested up to: 11.1
 * Author: Kayode Adeniyi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ng-postcode-for-woocommerce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/src/ParseError.php';
require_once __DIR__ . '/src/Corrected.php';
require_once __DIR__ . '/src/Postcode.php';
require_once __DIR__ . '/src/ApiError.php';
require_once __DIR__ . '/src/Api.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/nipost.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/locate.php';
require_once __DIR__ . '/includes/verify.php';
