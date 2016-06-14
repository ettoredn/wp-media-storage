<?php

namespace WPMediaStorage;


use EttoreDN\PHPObjectStorage\ObjectStorage;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use OpenCloud\Common\Error\BadResponseError;
use Psr\Log\LoggerInterface;
use WP_Error;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

class CloudImageEditorGD extends \WP_Image_Editor_GD
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    private function getLogger() {
        if (!$this->logger) {
            $this->logger = new Logger('media-storage/CloudImageEditorGD');
            $this->logger->pushHandler(new ErrorLogHandler());
        }

        return $this->logger;
    }

    /**
     * @param resource $image
     * @param string|null $filename
     * @param string|null $mime_type
     * @return WP_Error|array
     */
    protected function _save($image, $filename = null, $mime_type = null) {
        $logger = $this->getLogger();

        // Call parent to save locally
        $result = parent::_save($image, $filename, $mime_type);

        $uploadsDir = wp_get_upload_dir();
        $objectName = substr($result['path'], strlen($uploadsDir['basedir']) + 1);
        $objectContent = file_get_contents($result['path']);
        
        $logger->debug(sprintf('Storing image editor result %s as %s', $result['path'], $objectName));

        try {
            $objectStorage = MediaStoragePlugin::getObjectStore();
            $objectStorage->upload($objectName, $objectContent);
        } catch (BadResponseError $e) {
            $logger->error(sprintf('Error storing file %s: %s', $objectName, $e), [$image, $filename, $mime_type]);
            return new WP_Error('image_save_error', __('Image Editor Save Failed'));
        }

        return $result;
    }
}
