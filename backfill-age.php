<?php
require_once 'm:/Dev/Work/LCCL/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'lccl_de_spectacles';
$results = $wpdb->get_results("SELECT id, dob FROM {$table} WHERE age IS NULL OR age = ''");

$count = 0;
foreach ($results as $row) {
    if ($row->dob && $row->dob !== '0000-00-00') {
        $dob = new DateTime($row->dob);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        
        $wpdb->update(
            $table,
            array('age' => (string) $age),
            array('id' => $row->id)
        );
        $count++;
    }
}

echo "Updated {$count} records with computed age.\n";
