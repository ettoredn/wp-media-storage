<?php

namespace WPMediaStorage;

use Symfony\Component\Finder\SplFileInfo;
use WP_CLI;
use WP_CLI_Command;
use WPMediaStorage\ObjectStorageFactory;
use Symfony\Component\Finder\Finder;

class Media_Storage_Command extends WP_CLI_Command
{
    protected $storage;

    public function __construct()
    {
        $this->storage = ObjectStorageFactory::getInstance();
    }

    public function sync($args) {
        $finder = new Finder();
        $finder->files()->in(wp_get_upload_dir()['basedir']);

        foreach ($finder as $media) {
            /** @var SplFileInfo $media */
            $objectName = $media->getRelativePathname();

            if (!$this->storage->existsObject($objectName)) {
                WP_CLI::log(sprintf('Uploading media %s', $objectName));

                $this->storage->storeObject($objectName, $media->getContents());
            } else {
//                WP_CLI::log(sprintf('Media %s exists', $objectName));
            }
        }

        WP_CLI::success($args[0]);
    }
}

WP_CLI::add_command('media storage', Media_Storage_Command::class);