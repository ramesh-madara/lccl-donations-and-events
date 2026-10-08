<?php
require_once 'm:/Dev/Work/LCCL/wp-load.php';
global $wpdb;

$results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '%logo%'");
foreach ($results as $row) {
    if (is_serialized($row->option_value)) {
        $val = maybe_unserialize($row->option_value);
        if (is_array($val) || is_object($val)) {
            echo "Option: {$row->option_name} -> [Serialized array/object]\n";
        } else {
            echo "Option: {$row->option_name} -> {$val}\n";
        }
    } else {
        echo "Option: {$row->option_name} -> {$row->option_value}\n";
    }
}

// Also check kalium theme options
$kalium_options = get_option('kalium_options');
if (is_array($kalium_options)) {
    foreach ($kalium_options as $k => $v) {
        if (strpos($k, 'logo') !== false) {
            if (is_array($v) && isset($v['url'])) {
                echo "Kalium Option: {$k} -> " . $v['url'] . "\n";
            } else if (is_string($v)) {
                echo "Kalium Option: {$k} -> {$v}\n";
            }
        }
    }
}

