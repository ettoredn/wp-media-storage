<?php
/**
 * Created by PhpStorm.
 * User: ettore
 * Date: 09/06/16
 * Time: 22:25
 */

namespace WPMediaStorage;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

interface ObjectStorage
{
    /**
     * @param string $name
     * @return mixed
     */
    function existsObject(string $name);

    /**
     * @param string $name
     * @param mixed $content
     * @param bool $overwrite
     * @return mixed
     */
    function storeObject(string $name, $content, bool $overwrite = true);

    /**
     * @param array $files
     * @param array $options
     */
    function storeObjects(array $files, array $options);

    /**
     * @param string|null $objectName
     * @return string
     */
    function getObjectUrl(string $objectName);

    /**
     * @param string $objectName
     * @return mixed
     */
    function deleteObject(string $objectName);

    /**
     * @param array $options
     * @return array
     */
    function listObjects(array $options = []);
}