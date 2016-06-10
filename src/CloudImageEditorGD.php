<?php

namespace WPMediaStorage;


use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Psr\Log\LoggerInterface;
use WPMediaStorage\ObjectStorageFactory;

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
            $this->logger = new Logger('media-cloud/CloudImageEditorGD');
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
        
        $result = parent::_save($image, $filename, $mime_type);

        $objectStorage = ObjectStorageFactory::getInstance();

        $uploadsDir = wp_get_upload_dir();
        $objectName = substr($result['path'], strlen($uploadsDir['basedir']) + 1);
        $objectContent = file_get_contents($result['path']);

        $objectStorage->storeObject($objectName, $objectContent);

        return $result;
    }
}
