<?php

namespace WPMediaStorage;


use EttoreDN\PHPObjectStorage\Exception\ObjectStoreException;
use EttoreDN\PHPObjectStorage\ObjectStorage;
use EttoreDN\PHPObjectStorage\ObjectStore\ObjectStoreInterface;
use EttoreDN\PHPObjectStorage\ObjectStore\SwiftObjectStore;
use EttoreDN\PHPObjectStorage\StreamWrapper\SwiftStreamWrapper;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use OpenCloud\Common\Error\BadResponseError;
use Psr\Log\LoggerInterface;

class MediaStoragePlugin
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var ObjectStoreInterface */
    protected static $objectStore;

    /**
     * @throws \EttoreDN\PHPObjectStorage\Exception\ObjectStorageException
     */
    public static function getObjectStore(): ObjectStoreInterface
    {
        // Retrieve config and instantiace the correct class
        $options = self::getOptions();

        if (!self::$objectStore) {
            if ($options['objectStore'] == 'openstack')
                self::$objectStore = ObjectStorage::getInstance(SwiftObjectStore::class, $options['openstack']);
        }

        return self::$objectStore;
    }

    /**
     * MediaStoragePlugin constructor.
     */
    public function __construct()
    {
        $this->logger = new Logger('Media Storage');
        $this->logger->pushHandler(new ErrorLogHandler());
    }

    public function getOption(string $name, string $default = '') {
        $options = get_option('mediastorage', []);

        if (!array_key_exists($name, $options))
            return $default;

        return $options[$name];
    }

    public static function getOptions(): array {
        $o = get_option('mediastorage', []);

        if (!in_array($o['objectStore'], ['openstack']))
            $o['objectStore'] = false;

        return $o;
    }

    public function monkeyPathHandleUpload()
    {
        $stupidWP = ABSPATH . '/wp-admin/includes/file.php';
        $monkey = file_get_contents($stupidWP);

        if (preg_match('/if \( \'wp_handle_upload\' === \$action \) \{/i', $monkey)) {
            $patchedMonkey = preg_replace(
                '/if \( \'wp_handle_upload\' === \$action \) \{/i', 
                'if ( \'wp_handle_upload\' != $action ) {', 
                $monkey, 
                1
            );
            file_put_contents($stupidWP, $patchedMonkey);
        }
    }

    public function registerFilters()
    {
        if ($this->getOption('objectStore', false)) {
//            if (!has_filter('wp_handle_upload', [$this, 'uploadFilter']))
//                add_filter('wp_handle_upload', [$this, 'uploadFilter'], 10, 2);
//
//            if (!has_filter('wp_image_editors', [$this, 'imageEditorFilter']))
//                add_filter('wp_image_editors', [$this, 'imageEditorFilter']);
//
//            if (!has_filter('wp_delete_file', [$this, 'deleteFileFilter']))
//                add_filter('wp_delete_file', [$this, 'deleteFileFilter']);
//
//            if (!has_filter('wp_unique_filename', [$this, 'uniqueFilenameFilter']))
//                add_filter('wp_unique_filename', [$this, 'uniqueFilenameFilter'], 10, 3);
            
//            if ($this->getOption('useWrapper', false)) {
                switch ($this->getOption('objectStore')) {
                    case 'openstack':
                        ObjectStorage::registerStreamWrapper(SwiftStreamWrapper::class, $this->getOption('openstack'));
                        break;
                }

                if (!has_filter('upload_dir', [$this, 'prependWrapperToUploadPath']))
                    add_filter('upload_dir', [$this, 'prependWrapperToUploadPath']);
//            }
        }

//        if ($this->getOption('rewriteAttachmentUrls', false)) {
            // TODO use wp option 'upload_url_path' with stream wrappers ?
            // Images get tagged with 'wp-image-<id>' class
            // Attachments with 'wp-att-<id'> class
            // Videos don't get tagged :(
//            if (!has_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrlFilter']))
//                add_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrlFilter'], 10, 2);
//
//            if (!has_filter('wp_calculate_image_srcset', [$this, 'rewriteImageSourceFilter']))
//                add_filter('wp_calculate_image_srcset', [$this, 'rewriteImageSourceFilter'], 10, 5);
//        }
    }
    
    public function addActions()
    {
        if (!has_action('admin_init', [$this, 'monkeyPathHandleUpload']))
            add_action('admin_init', [$this, 'monkeyPathHandleUpload']);
    }

    public function prependWrapperToUploadPath(array $uploadPaths)
    {
        $store = $this->getOption('objectStore');
        if ($store == 'openstack') {
            // swift://<container>/<uploadPath>/<objectName>
            //   <container> set as configured in the plugin
            //   <uploadPath> as set by the user
            //   <objectName> = 2016/02/foo.jpeg
            $container = $this->getOption('openstack')['container'];
            
            $baseDir = preg_replace('/\/{2,}/', '/', $container .'/'. get_option('upload_path'));
            $path =  preg_replace('/\/{2,}/', '/', $baseDir .'/'. $uploadPaths['subdir']);
    
            $uploadPaths['basedir'] = 'swift://' . $baseDir;
            $uploadPaths['path'] = 'swift://' . $path;
        }
        
        return $uploadPaths;
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
            $objectStorage->upload($objectName, $objectContent);

            // TODO
            $media['url'] = 'TODO';
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
            // TODO
//            $url = $objectStorage->getObjectUrl($objectName);
            $url = 'TODO';
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
                // TODO
                $url = 'TODO';
//                $url = $objectStorage->getObjectUrl($objectName);
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
            $objectStorage->delete($objectName);
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
        while ($objectStorage->exists($objectName)) {
            if ('' == "$number$ext") {
                $objectName = "$objectName-" . ++$number;
            } else {
                $objectName = str_replace( array( "-$number$ext", "$number$ext" ), "-" . ++$number . $ext, $objectName );
            }
        }
        return wp_basename("/$objectName");
    }
}