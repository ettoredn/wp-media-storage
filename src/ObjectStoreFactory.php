<?php

namespace WPMediaStorage;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class ObjectStoreFactory
{
    /**
     * @var ObjectStore
     */
    protected static $instance;

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @param LoggerInterface $logger
     * @return ObjectStore
     */
    public static function getInstance(LoggerInterface $logger = null)
    {
        // Retrieve config and instantiace the correct class
        $options = MediaStoragePlugin::getOptions();

        $swiftOptions = [];
        foreach ($options as $name => $value) {
            if (substr($name, 0, 6) === 'swift_')
                $swiftOptions[substr($name, 6)] = $value;
        }

        $swiftOptions['debugLog'] = boolval($swiftOptions['debugLog']);
        
        if (!$logger) {
            $logger = new Logger('media-storage/ObjectStore');
            $logger->pushHandler(new ErrorLogHandler());
        }
        
        if (!self::$instance) {
            $class = new \ReflectionClass(SwiftObjectStore::class);
            self::$instance = $class->newInstance($swiftOptions, $logger);
        }

        return self::$instance;
    }
}