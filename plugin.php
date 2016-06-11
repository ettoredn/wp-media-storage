<?php

/*
Plugin Name: Media Storage
Plugin URI: https://github.com/ettoredn/wp-media-storage
Description: Store media files on cloud object stores.
Version: 1.0
Author: Ettore Del Negro
Author URI: http://ettoredelnegro.me
License: ISC
*/

require __DIR__ . '/vendor/autoload.php';

use WPMediaStorage\Media_Storage_Command;
use WPMediaStorage\MediaStoragePlugin;

add_action('init', function () {
	$plugin = new MediaStoragePlugin();
	$plugin->registerFilters();
});

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/cli/media-storage.php';
	WP_CLI::add_command('media storage', Media_Storage_Command::class);
} else {
	include __DIR__ . '/settings.php';
}
