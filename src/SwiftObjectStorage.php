<?php

namespace WPMediaStorage;


use GuzzleHttp\Psr7\Stream;
use OpenCloud\Common\Error\BadResponseError;
use OpenStack\ObjectStore\v1\Models\Object;
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
     * @var string
     */
    protected $storageUrl;

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

        list(, $this->storageUrl) = $identityService->authenticate(array_merge($config, [
            'urlType' => 'publicURL',
            'catalogName' => 'swift',
            'catalogType' => 'object-store'
        ]));

        $options = array_merge($config, [
            'identityService' => $identityService,
            'logger' => $logger,
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
     * @param array $files
     * @return void
     */
    function storeObjects(array $files)
    {
        $archive = new \PharData('/tmp/buld-extract.tar');
        foreach ($files as $objectName => $spl) {
            /** @var \SplFileInfo $spl */
            if (!($spl instanceof \SplFileInfo))
                throw new \RuntimeException('Expecting an instance of \SplFileInfo');
            
            $archive->addFile($spl->getPathname(), $objectName);
        }

        
        if (!($handle = fopen('/tmp/buld-extract.tar', 'r')))
            throw new \RuntimeException(sprintf('Unable to open the tar archive we just created??'));

        $data = [
            'extract-archive' => 'tar',
            'stream' => new Stream($handle),
            'containerName' => $this->container->name
        ];
        $this->container->model(Archive::class)->upload($data);
        
        fclose($handle);
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
     * @return Object
     */
    function storeObject(string $name, $content, bool $overwrite = true)
    {
        return $this->container->createObject([
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
        /** @var \OpenStack\ObjectStore\v1\Models\Object $object */
        $object = $this->container->getObject($objectName);

        $url = (string) $object->getPublicUri();
        return $url;
//        return sprintf('%s/%s/%s', $this->storageUrl, $this->config['container'], $objectName);
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

    /**
     * @param array $config
     * @return array
     */
    function listObjects(array $config = [])
    {
//        'name'      => $this->params->containerName(),
//        'format'    => $this->params->format(),
//        'limit'     => $this->params->limit(),
//        'marker'    => $this->params->marker(),
//        'endMarker' => $this->params->endMarker(),
//        'prefix'    => $this->params->prefix(),
//        'path'      => $this->params->path(),
//        'delimiter' => $this->params->delimiter(),
//        'newest'    => $this->params->newest(),

        $hasLimit = intval($config['limit']) > 0;
        $options = [];

        if (array_key_exists('limit', $config) && intval($config['limit']) > 0)
            $options['limit'] = intval($config['limit']);
        if (array_key_exists('prefix', $config) && !empty($config['prefix']))
            $options['prefix'] = $config['prefix'];

        $objects = [];
        do {
            $results = 0;
            foreach ($this->container->listObjects($options) as $object) {
                /** @var \OpenStack\ObjectStore\v1\Models\Object $object */
                $objects[$object->name] = $object;

                $results++;
                $options['marker'] = $object->name;

                if ($hasLimit && count($objects) >= $options['limit'])
                    break;
            }
        } while ($results > 0 && (!$hasLimit || count($objects) < $options['limit']));

        return $objects;
    }
}