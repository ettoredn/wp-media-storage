<?php

namespace WPMediaStorage;


use OpenCloud\Common\Error\BadResponseError;
use Psr\Log\LoggerInterface;
use OpenStack\OpenStack;
use OpenStack\Identity\v2\Service;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class SwiftObjectStorage implements ObjectStorage
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \OpenStack\ObjectStore\v1\Models\Container
     */
    protected $container;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $config;

        $identityService = Service::factory(new Client([
            'base_uri' => $config['authUrl'],
            'handler'  => HandlerStack::create(),
        ]));

        $options = array_merge($config, [
            'identityService' => $identityService,
            'logger' => $logger
        ]);

        $stack = new OpenStack($options);
        $swift = $stack->objectStoreV1($options);

        if (!$swift->containerExists($config['container'])) {
            $logger->error('Container does not exist', [$config['container']]);
            throw new \RuntimeException(sprintf('Container %s does not exist', $config['container']));
        }

        $this->container = $swift->getContainer($config['container']);
    }

    /**
     * @param string $name
     * @return mixed
     */
    function existsObject(string $name)
    {
        return $this->container->objectExists($name);
    }

    /**
     * @param string $name
     * @param mixed $content
     * @param bool $overwrite
     * @return mixed
     */
    function storeObject(string $name, $content, bool $overwrite = true)
    {
        $this->container->createObject([
            'name' => $name,
            'content' => $content,
            'ETag' => hash('md5', $content)
        ]);
    }

    /**
     * @param string $objectName
     * @return string
     */
    function getObjectUrl(string $objectName)
    {
//        if (!$this->hasObject($objectName))
//            throw new \RuntimeException(sprintf('Cannot generate URL for object %s because it does not exist', $objectName));

        return sprintf('%s/%s/%s', $this->config['publicUrl'], $this->config['container'], $objectName);
    }

    /**
     * @param string $objectName
     * @return mixed
     * @throws BadResponseError
     */
    function deleteObject(string $objectName)
    {
        /** @var \OpenStack\ObjectStore\v1\Models\Object $object */
        try {
            $object = $this->container->getObject($objectName);
            $object->delete();
        } catch (BadResponseError $e) {
            if ($e->getResponse()->getStatusCode() != 404)
                throw $e;
        }
    }
}