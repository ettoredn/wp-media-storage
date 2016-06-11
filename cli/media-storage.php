<?php

namespace WPMediaStorage;

use OpenStack\ObjectStore\v1\Models\Object;
use Symfony\Component\Finder\SplFileInfo;
use WP_CLI;
use WP_CLI_Command;
use WPMediaStorage\ObjectStoreFactory;
use Symfony\Component\Finder\Finder;

/**
 * Manages the configured remote Object Store
 */
class Media_Storage_Command extends WP_CLI_Command
{
    protected $storage;

    public function __construct()
    {
        $this->storage = ObjectStoreFactory::getInstance();
    }

    /**
     * List objects in the object store.
     *
     * ## OPTIONS
     *
     * [<prefix>]
     * : List only objects whose name starts with <prefix>
     *
     * [--limit=<n>]
     * : Limit listing to <n> objects. Defaults to 100.
     *
     * @subcommand list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function _list(array $args, array $assoc_args)
    {
        $options = array_merge([
            'limit' => 100,
            'prefix' => count($args) > 0 ? $args[0] : null,
        ], $assoc_args);
        
        $objects = $this->storage->listObjects($options);
        
        foreach ($objects as $name => $object)
            WP_CLI::line($name);
        
        WP_CLI::success(sprintf('found %d items', count($objects)));
    }

    /**
     * Remove objects not associated with any attachment from the store.
     *
     * ## OPTIONS
     *
     * [--confirm]
     * : Do actually delete objects
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function clean(array $args, array $assocArgs)
    {
        global $post;
        
        $dry = !($assocArgs['confirm'] ?? false);

        $q = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit,private',
            'nopaging' => true
        ]);

        $attachments = [];
        while ($q->have_posts()) {
            $q->the_post();

            // Add attachment
            if ($pathname = get_attached_file($post->ID)) {
                $name = substr($pathname, strlen(wp_get_upload_dir()['basedir']) + 1);
                $attachments[] = $name;
            }

            // Add attachment's thumbnail
            $meta = wp_get_attachment_metadata($post->ID);
            if ($meta && array_key_exists('sizes', $meta) && is_array($meta['sizes'])) {
                foreach ($meta['sizes'] as $size) {
                    $thumbName = sprintf('%s/%s', dirname($name), $size['file']);
                    $attachments[] = $thumbName;
                }
            }
        }
        WP_CLI::line(sprintf('Database: %d attachments found', count($attachments)));

        $storeObjects = $this->storage->listObjects();
        WP_CLI::line(sprintf('Store: %d objects found', count($storeObjects)));

        // Store - database
        $toRemove = array_diff(array_keys($storeObjects), $attachments);

        // Remove objects from the store
        foreach ($toRemove as $name) {
            if (!$dry)
                $this->storage->deleteObject($name);

            WP_CLI::line(sprintf('Store: removed attachment %s', $name));
        }

        WP_CLI::success(sprintf('removed %d objects from the store', count($toRemove)));
    }

    /**
     * Uploads local media to the object store.
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Media file to upload. It must be inside the uploads directory.
     *
     * [--dry]
     * : Do not perform any operation on the object store.
     * 
     * [--chunk-size=<s>]
     * : Chunk size in MiB (default 10)
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function upload(array $args, array $assoc_args) {
        $dry = $assoc_args['dry'] ?? false;
        $chunkSize = array_key_exists('chunk-size', $assoc_args) ? intval($assoc_args['chunk-size']) : 10;
        $chunkSize *= 1024*1024;

        if (count($args) > 0) {
            // Upload each file at once. Used as a workaround to Phar which limits filenames to 100 chars.
            foreach ($args as $name) {
                $pathname = realpath($name);
                if (!$pathname)
                    WP_CLI::error(sprintf('Unable to locate file %s', $name));

                $relPath = wp_get_upload_dir()['basedir'];
                $objectName = substr($pathname, strlen($relPath) + 1);

                $this->storage->storeObject($objectName, file_get_contents($pathname));
                WP_CLI::success(sprintf('Upload object %s', $objectName));
            }

            return;
        }

        $finder = new Finder();
        $finder->ignoreDotFiles(true)->ignoreUnreadableDirs()->files()->in(wp_get_upload_dir()['basedir']);

        $localMedia = [];
        foreach ($finder as $file)
            /** @var SplFileInfo $file */
            $localMedia[$file->getRelativePathname()] = $file;

        WP_CLI::line(sprintf('Local: %d objects found', count($localMedia)));

        WP_CLI::debug(sprintf('Listing objects on the object store, this might take a while...'));
        $storeObjects = $this->storage->listObjects();

        WP_CLI::line(sprintf('Store: %d objects found', count($storeObjects)));

        // Do not overwrite existing media by default as the store should be the master version
        $toUpload = array_diff_key($localMedia, $storeObjects);

        if (count($toUpload) > 0) {
            WP_CLI::line(sprintf('%d local media files not present in the store, uploading...', count($toUpload)));
            if (!$dry)
                $this->storage->storeObjects($toUpload, ['chunkSize' => $chunkSize]);
        }
        else
            WP_CLI::line(sprintf('No local local media file missing from the store'));


        WP_CLI::success(sprintf('Uploaded %d files to the object store', count($toUpload)));
    }
}
