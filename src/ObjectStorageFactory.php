<?php

namespace WPMediaStorage;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class ObjectStorageFactory
{
    /**
     * @var ObjectStorage
     */
    protected static $instance;

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @return ObjectStorage
     */
    public static function getInstance()
    {
        // Retrieve config and instantiace the correct class
        $options = get_option('mediastorage_settings');

        $swiftOptions = [];
        foreach ($options as $name => $value) {
            if (substr($name, 0, 6) === 'swift_')
                $swiftOptions[substr($name, 6)] = $value;
        }

        $swiftOptions['debugLog'] = boolval($swiftOptions['debugLog']);
        
        if (!self::$logger) {
            self::$logger = new Logger('media-storage/ObjectStorage');
            self::$logger->pushHandler(new ErrorLogHandler());
        }
        
        if (!self::$instance) {
            $class = new \ReflectionClass(SwiftObjectStorage::class);
            self::$instance = $class->newInstance($swiftOptions, self::$logger);
        }

        return self::$instance;
    }
}