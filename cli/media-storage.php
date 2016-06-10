<?php

namespace WPMediaStorage;

use Symfony\Component\Finder\SplFileInfo;
use WP_CLI;
use WP_CLI_Command;
use WPMediaStorage\ObjectStorageFactory;
use Symfony\Component\Finder\Finder;

/**
 * Manages the configured remote Object Store
 */
class Media_Storage_Command extends WP_CLI_Command
{
    protected $storage;

    public function __construct()
    {
        $this->storage = ObjectStorageFactory::getInstance();
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
     * Uploads local media to the object store.
     *
     * ## OPTIONS
     *
     * [--dry]
     * : Do not perform any operation on the object store.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function upload(array $args, array $assoc_args) {
        $dry = $assoc_args['dry'] ?? false;

        $finder = new Finder();
        $finder->ignoreDotFiles(true)->ignoreUnreadableDirs()->files()->in(wp_get_upload_dir()['basedir']);

        $localMedia = [];
        foreach ($finder as $file)
            /** @var SplFileInfo $file */
            $localMedia[$file->getRelativePathname()] = $file;

        WP_CLI::line(sprintf('Listing objects on the object store, this might take a while...'));
        $storeObjects = $this->storage->listObjects();

        // Do not overwrite existing media by default as the store should be the master version
        $toUpload = array_diff_key($localMedia, $storeObjects);

        if (count($toUpload) > 0) {
            WP_CLI::line(sprintf('%d local media files not present in the object store, uploading...', count($toUpload)));
            if (!$dry)
                $this->storage->storeObjects($toUpload);
        }
        else
            WP_CLI::line(sprintf('All local media files are present in the store'));


        WP_CLI::success(sprintf('Uploaded %d files to the object store', count($toUpload)));
    }
}

WP_CLI::add_command('media storage', Media_Storage_Command::class);