<?php
/**
 * Creates or updates the local /donate/ page with [lccl_donation_form] shortcode.
 */
require dirname( __FILE__, 5 ) . '/wp-load.php';

$slug    = 'donate';
$title   = 'Make a Donation';
$content = '[lccl_donation_form]';

// Check if page already exists (any status).
$existing = get_page_by_path( $slug, OBJECT, 'page' );

if ( $existing ) {
	$result = wp_update_post( array(
		'ID'          => $existing->ID,
		'post_title'  => $title,
		'post_content'=> $content,
		'post_status' => 'publish',
		'post_name'   => $slug,
	) );
	if ( is_wp_error( $result ) ) {
		echo 'Error updating page: ' . $result->get_error_message() . "\n";
	} else {
		echo 'Page updated. ID=' . $result . "\n";
		echo 'URL: ' . get_permalink( $result ) . "\n";
	}
} else {
	$id = wp_insert_post( array(
		'post_title'  => $title,
		'post_content'=> $content,
		'post_status' => 'publish',
		'post_type'   => 'page',
		'post_name'   => $slug,
	) );
	if ( is_wp_error( $id ) ) {
		echo 'Error creating page: ' . $id->get_error_message() . "\n";
	} else {
		echo 'Page created. ID=' . $id . "\n";
		echo 'URL: ' . get_permalink( $id ) . "\n";
	}
}
