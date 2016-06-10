<?php

/*
Plugin Name: Media Storage
Plugin URI: https://github.com/ettoredn/wp-media-storage
Description: A brief description of the Plugin.
Version: 1.0
Author: Ettore Del Negro
Author URI: http://ettoredelnegro.me
License: ISC
*/

namespace WPMediaStorage;

require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use OpenCloud\Common\Error\BadResponseError;


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$logger = new Logger('media-storage');
$logger->pushHandler(new ErrorLogHandler());

$options = get_option('mediastorage_settings');

/**
 * @param array|string $media
 * @param string $context 'upload' | 'sideload'
 * @return array
 * @throws BadResponseError
 */
$addUploadHandler = function (array $media, string $context) use ($logger) {
	// Assuming $media->file exists
	$uploadsDir = wp_get_upload_dir();
	$objectName = substr($media['file'], strlen($uploadsDir['basedir']) + 1);
	$objectContent = file_get_contents($media['file']);
	
	$logger->debug(sprintf('Storing uploaded media file %s as %s', $media['file'], $objectName), [$context]);

	try {
		$objectStorage = ObjectStorageFactory::getInstance();
		/** @var \OpenStack\ObjectStore\v1\Models\Object $obj */
		$obj = $objectStorage->storeObject($objectName, $objectContent);
		$media['url'] = $obj->getPublicUri();
	} catch (BadResponseError $e) {
		$logger->error(sprintf('Error storing file %s: %s', $objectName, $e), [$media, $context]);
		$media['error'] = (string) $e;
	}

	return $media;
};


/**
 * @param array $image_editors
 * @return array
 */
$overrideImageEditors = function (array $image_editors) use ($logger) {
	foreach ($image_editors as $key => $editor) {
		if ($editor == 'WP_Image_Editor_GD')
			$image_editors[$key] = CloudImageEditorGD::class;
	}

	return $image_editors;
};

/**
 * @param string $url
 * @param $postId
 * @return string
 */
$rewriteAttachmentUrl = function (string $url, int $postId) use ($logger) {
	$uploadsDir = wp_get_upload_dir();
	$objectName = substr($url, strlen($uploadsDir['baseurl']) + 1);

	$objectStorage = ObjectStorageFactory::getInstance();

	try {
		$url = $objectStorage->getObjectUrl($objectName);
	} catch (BadResponseError $e) {
		$logger->error(sprintf('Object %s does not exist', $objectName));
	}

	return $url;
};

$rewriteImageSources = function (array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id) use ($logger) {
	$objectStorage = ObjectStorageFactory::getInstance();

	foreach ($sources as $key => $source) {
		$uploadsDir = wp_get_upload_dir();
		$objectName = substr($source['url'], strlen($uploadsDir['baseurl']) + 1);

		try {
			$url = $objectStorage->getObjectUrl($objectName);
			$sources[$key]['url'] = $url;
		} catch (BadResponseError $e) {
			$logger->error(sprintf('Object %s does not exist', $objectName));
		}
	}
	
	return $sources;
};


$deleteFile = function (string $path) use ($logger) {
	$logger->debug(sprintf('Deleting file %s', $path), [$path]);

	$uploadsDir = wp_get_upload_dir();
	$objectName = substr($path, strlen($uploadsDir['basedir']) + 1);

	try {
		$objectStorage = ObjectStorageFactory::getInstance();
		$objectStorage->deleteObject($objectName);
	} catch (BadResponseError $e) {
		$logger->error(sprintf('Unable to delete object %s: %s', $objectName, $e), [$path]);
	}
	
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


if (array_key_exists('store', $options) && boolval($options['store'])) {
	if (!has_filter('wp_handle_upload', $addUploadHandler))
		add_filter( 'wp_handle_upload', $addUploadHandler, 10, 2);

	if (!has_filter('wp_image_editors', $overrideImageEditors))
		add_filter('wp_image_editors', $overrideImageEditors);

	if (!has_filter('wp_delete_file', $deleteFile))
		add_filter('wp_delete_file', $deleteFile);

	if (!has_filter('wp_unique_filename', $uniqueFilename))
		add_filter('wp_unique_filename', $uniqueFilename, 10, 3);
}

if (array_key_exists('rewriteUrl', $options) && boolval($options['rewriteUrl'])) {
	if (!has_filter('wp_get_attachment_url', $rewriteAttachmentUrl))
		add_filter('wp_get_attachment_url', $rewriteAttachmentUrl, 10, 2);

	if (!has_filter('wp_calculate_image_srcset', $rewriteImageSources))
		add_filter('wp_calculate_image_srcset', $rewriteImageSources, 10, 5);
}


//register_activation_hook(__FILE__, $initOptions);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/cli/media-sync.php';
} else {
	include __DIR__ . '/admin/admin-page.php';
}
