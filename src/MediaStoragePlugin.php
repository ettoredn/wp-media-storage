<?php

namespace WPMediaStorage;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use OpenCloud\Common\Error\BadResponseError;
use Psr\Log\LoggerInterface;

class MediaStoragePlugin
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var ObjectStore */
    protected $objectStore;

    public function __construct()
    {
        $this->logger = new Logger('media-storage');
        $this->logger->pushHandler(new ErrorLogHandler());
    }

    public function getOption(string $name, string $default = '')
    {
        $options = get_option('mediastorage_settings');

        if (!array_key_exists($name, $options))
            return $default;

        return $options[$name];
    }
    
    public static function getOptions()
    {
        return get_option('mediastorage_settings', []);
    }
    
    protected function getObjectStore()
    {
        if (!$this->objectStore)
            $this->objectStore = ObjectStoreFactory::getInstance($this->logger);
        
        return $this->objectStore;
    }
    
    public function registerFilters()
    {
        if ($this->getOption('store', false)) {
            if (!has_filter('wp_handle_upload', [$this, 'uploadFilter']))
                add_filter('wp_handle_upload', [$this, 'uploadFilter'], 10, 2);

            if (!has_filter('wp_image_editors', [$this, 'imageEditorFilter']))
                add_filter('wp_image_editors', [$this, 'imageEditorFilter']);

            if (!has_filter('wp_delete_file', [$this, 'deleteFileFilter']))
                add_filter('wp_delete_file', [$this, 'deleteFileFilter']);

            if (!has_filter('wp_unique_filename', [$this, 'uniqueFilenameFilter']))
                add_filter('wp_unique_filename', [$this, 'uniqueFilenameFilter'], 10, 3);
        }

        if ($this->getOption('rewriteUrl', false)) {
            if (!has_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrlFilter']))
                add_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrlFilter'], 10, 2);

            if (!has_filter('wp_calculate_image_srcset', [$this, 'rewriteImageSourceFilter']))
                add_filter('wp_calculate_image_srcset', [$this, 'rewriteImageSourceFilter'], 10, 5);
        }
    }

    public function uploadFilter(array $media, string $context)
    {
        // Assuming $media->file exists
        $uploadsDir = wp_get_upload_dir();
        $objectName = substr($media['file'], strlen($uploadsDir['basedir']) + 1);
        $objectContent = file_get_contents($media['file']);

        $this->logger->debug(sprintf('Storing uploaded media file %s as %s', $media['file'], $objectName), [$context]);

        try {
            $objectStorage = $this->getObjectStore();
            /** @var \OpenStack\ObjectStore\v1\Models\Object $obj */
            $obj = $objectStorage->storeObject($objectName, $objectContent);
            $media['url'] = $obj->getPublicUri();
        } catch (BadResponseError $e) {
            $this->logger->error(sprintf('Error storing file %s: %s', $objectName, $e), [$media, $context]);
            $media['error'] = (string) $e;
        }

        return $media;
    }
    
    public function imageEditorFilter(array $image_editors)
    {
        foreach ($image_editors as $key => $editor) {
            if ($editor == 'WP_Image_Editor_GD')
                $image_editors[$key] = CloudImageEditorGD::class;
        }

        return $image_editors;
    }
    
    public function rewriteAttachmentUrlFilter(string $url, int $postId)
    {
        $uploadsDir = wp_get_upload_dir();
        $objectName = substr($url, strlen($uploadsDir['baseurl']) + 1);

        $objectStorage = $this->getObjectStore();

        try {
            $url = $objectStorage->getObjectUrl($objectName);
        } catch (BadResponseError $e) {
            $this->logger->error(sprintf('Object %s does not exist', $objectName));
        }

        return $url;
    }
    
    public function rewriteImageSourceFilter(array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id)
    {
        $objectStorage = $this->getObjectStore();

        foreach ($sources as $key => $source) {
            $uploadsDir = wp_get_upload_dir();
            $objectName = substr($source['url'], strlen($uploadsDir['baseurl']) + 1);

            try {
                $url = $objectStorage->getObjectUrl($objectName);
                $sources[$key]['url'] = $url;
            } catch (BadResponseError $e) {
                $this->logger->error(sprintf('Object %s does not exist', $objectName));
            }
        }

        return $sources;
    }
    
    public function deleteFileFilter(string $path)
    {
        $this->logger->debug(sprintf('Deleting file %s', $path), [$path]);

        $uploadsDir = wp_get_upload_dir();
        $objectName = substr($path, strlen($uploadsDir['basedir']) + 1);

        try {
            $objectStorage = $this->getObjectStore();
            $objectStorage->deleteObject($objectName);
        } catch (BadResponseError $e) {
            $this->logger->error(sprintf('Unable to delete object %s: %s', $objectName, $e), [$path]);
        }

        return $path;
    }
    
    public function uniqueFilenameFilter($filename, $ext, $dir)
    {
        $objectStorage = $this->getObjectStore();

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
    }
}