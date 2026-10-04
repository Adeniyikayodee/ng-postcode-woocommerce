<?php
// Runs WordPress.org's Plugin Check, the same static checks its reviewers run, and prints the findings.
require __DIR__ . '/wp-load.php';

use WordPress\Plugin_Check\Checker\Check_Context;
use WordPress\Plugin_Check\Checker\Check_Repository;
use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Default_Check_Repository;

$plugin = WP_PLUGIN_DIR . '/ng-postcode-for-woocommerce/ng-postcode-for-woocommerce.php';
$result = new Check_Result(new Check_Context($plugin));
$checks = (new Default_Check_Repository())->get_checks(Check_Repository::TYPE_STATIC)->to_map();
foreach ($checks as $check) {
    $check->run($result);
}

$findings = [];
foreach (['error' => $result->get_errors(), 'warning' => $result->get_warnings()] as $kind => $files) {
    foreach ($files as $file => $lines) {
        foreach ($lines as $line => $columns) {
            foreach (array_merge(...array_values($columns)) as $found) {
                $findings[] = "$kind $file:$line {$found['code']}: " . wp_strip_all_tags($found['message']);
            }
        }
    }
}
echo wp_json_encode(['checks' => count($checks), 'findings' => $findings]);
