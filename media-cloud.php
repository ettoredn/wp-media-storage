<?php

/*
Plugin Name: Media Cloud
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: Ettore Del Negro
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

namespace WPMediaCloud;

require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;


if ( ! defined( 'ABSPATH' ) ) exit;

$logger = new Logger('media-cloud');
$logger->pushHandler(new ErrorLogHandler());

/**
 * @param array|string $media
 * @param string $context
 * @return array
 */
$addUploadHandler = function (array $media, string $context) use ($logger) {
	$logger->debug('Storing uploaded media files on the cloud', [$media, $context]);
	$uploadsDir = wp_get_upload_dir();

	// Assuming $media->file exists
	$objectName = substr($media['file'], strlen($uploadsDir['basedir']) + 1);
	$objectContent = file_get_contents($media['file']);

	// $context := 'upload' | 'sideload'

	$objectStorage = ObjectStorageFactory::getInstance();
	$objectStorage->storeObject($objectName, $objectContent);

	return array_merge($media, ['url' => 'https://storage.gra1.cloud.ovh.net/v1/AUTH_83cd2bf7ff8d40aca02b0448154cf794/media/' . $objectName]);
};

/**
 * @param string $url
 * @param $postId
 * @return string
 */
$rewriteAttachmentUrl = function (string $url, int $postId) use ($logger) {
	$logger->debug(sprintf('Rewriting attachment url %s for post %d', $url, $postId));

	$uploadsDir = wp_get_upload_dir();
	$objectName = substr($url, strlen($uploadsDir['baseurl']) + 1);

	$objectStorage = ObjectStorageFactory::getInstance();

	$url = $objectStorage->getObjectUrl($objectName);

	return $url;
};

$rewriteImageSources = function (array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id) use ($logger) {
//	$logger->debug('Storing uploaded media files on the cloud', [$ources]);

	$objectStorage = ObjectStorageFactory::getInstance();

	
	foreach ($sources as $key => $source) {
		$uploadsDir = wp_get_upload_dir();
		$objectName = substr($source['url'], strlen($uploadsDir['baseurl']) + 1);
		$sources[$key]['url'] = $objectStorage->getObjectUrl($objectName);
	}
	
	return $sources;
};


/**
 * @param array $image_editors
 * @return array
 */
$overrideImageEditors = function (array $image_editors) use ($logger) {
	$logger->debug('Storing uploaded media files on the cloud', [$image_editors]);

	foreach ($image_editors as $key => $editor) {
		if ($editor == 'WP_Image_Editor_GD')
			$image_editors[$key] = CloudImageEditorGD::class;
	}

	return $image_editors;
};

$deleteFile = function (string $path) use ($logger) {
	$logger->debug('Deleting file ', [$path]);

	$uploadsDir = wp_get_upload_dir();
	$objectName = substr($path, strlen($uploadsDir['basedir']) + 1);

	$objectStorage = ObjectStorageFactory::getInstance();
	$objectStorage->deleteObject($objectName);
	
	return $path;
};

$uniqueFilename = function ($filename, $ext, $dir) use ($logger) {
	$objectStorage = ObjectStorageFactory::getInstance();

	$uploadsDir = wp_get_upload_dir();
	$objectName = substr("$dir/$filename", strlen($uploadsDir['basedir']) + 1);

	$number = '';
	while ($objectStorage->existsObject($objectName)) {
		if ('' == "$number$ext") {
			$objectName = "$objectName-" . ++$number;
		} else {
			$objectName = str_replace( array( "-$number$ext", "$number$ext" ), "-" . ++$number . $ext, $objectName );
		}
	}
	return wp_basename("/$objectName");
};

if (!has_filter('wp_handle_upload', $addUploadHandler))
	add_filter( 'wp_handle_upload', $addUploadHandler, 10, 2);

if (!has_filter('wp_image_editors', $overrideImageEditors))
	add_filter('wp_image_editors', $overrideImageEditors);

if (!has_filter('wp_get_attachment_url', $rewriteAttachmentUrl))
	add_filter('wp_get_attachment_url', $rewriteAttachmentUrl, 10, 2);

if (!has_filter('wp_calculate_image_srcset', $rewriteImageSources))
	add_filter('wp_calculate_image_srcset', $rewriteImageSources, 10, 5);

if (!has_filter('wp_delete_file', $deleteFile))
	add_filter('wp_delete_file', $deleteFile);

if (!has_filter('wp_unique_filename', $uniqueFilename))
	add_filter('wp_unique_filename', $uniqueFilename, 10, 3);



// Create tables
function setupDbTables() {
	global $wpdb;

	$tableName = $wpdb->prefix . "media_cloud";

	// TODO
}

// Triggered when the plugin is activated
register_activation_hook( __FILE__, 'setupDbTables' );

include __DIR__ . '/admin-page.php';
