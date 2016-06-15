<?php

/*
Plugin Name: Media Storage
Plugin URI: https://github.com/ettoredn/wp-media-storage
Description: Store media on cloud object stores.
Version: 1.0
Author: Ettore Del Negro
Author URI: http://ettoredelnegro.me
License: GPLv2
*/

require __DIR__ . '/vendor/autoload.php';

use EttoreDN\PHPObjectStorage\ObjectStorage;
use WPMediaStorage\Media_Storage_Command;
use WPMediaStorage\MediaStoragePlugin;

if (defined('ABSPATH')) {
	add_action('init', function () {
		$plugin = new MediaStoragePlugin();
		$plugin->registerFilters();
		$plugin->monkeyPathHandleUpload();
	});

	include __DIR__ . '/settings.php';
}

else if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/cli/media-storage.php';
	WP_CLI::add_command('media storage', Media_Storage_Command::class);
}

else if (array_key_exists('test', $_GET)) {
	/** WordPress Administration Bootstrap */
	require_once( __DIR__ . '/../../../wp-load.php' );

	$storeMap = [
		'openstack' => \EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore::class
	];
	$options = $_POST['mediastorage'];

	check_admin_referer('mediastorage-options');

	if (array_key_exists($options['objectStore'], $storeMap)) {
		$errors = [];
		$objectCount = 0;
		$store = ObjectStorage::getInstance($storeMap[$options['objectStore']], $options[$options['objectStore']]);

		header('Content-Type: application/json');
		try {
			$store->getAuthenticatedClient();
			$objectCount = count($store);
			http_response_code(200);
			print json_encode(['objectCount' => $objectCount]);
		} catch (Exception $e) {
			$errors = [$e->getMessage()];
			http_response_code(500);
			print json_encode(['errors' => $errors]);
		}
	}
}
