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
use Symfony\Component\Finder\SplFileInfo;
use WP_CLI;

class WPSwiftObjectStore
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
     * @param array $options
     */
    function storeObjects(array $files, array $options)
    {
        $files = array_filter($files, function ($file, $objectName) {
            /** @var SplFileInfo $file */
            if (strlen(basename($objectName)) <= 100)
                return true;

            if (!($file instanceof \SplFileInfo))
                throw new \RuntimeException('Expecting an instance of \SplFileInfo');

            $this->storeObject($objectName, file_get_contents($file->getPathname()));

            if (defined('WP_CLI') && WP_CLI)
                WP_CLI::line(sprintf('Store: uploaded %s outside of the archive', $objectName));

            return false;
        }, ARRAY_FILTER_USE_BOTH);

        $chunkSize = array_key_exists('chunkSize', $options) ? intval($options['chunkSize']) : 10 * 1024 * 1024;

        $chunks = [];
        $count = 0;
        $size = 0;
        foreach ($files as $objectName => $file) {
            /** @var \SplFileInfo $spl */
            if (!($file instanceof \SplFileInfo))
                throw new \RuntimeException('Expecting an instance of \SplFileInfo');

            $size += $file->getSize();

            if ($size > $chunkSize) {
                $count++;
                $size = 0;
            }

            if (!array_key_exists($count, $chunks))
                $chunks[$count] = [];

            $chunks[$count][$objectName] = $file;
        }

        foreach ($chunks as $id => $chunk) {
            $archivePathname = sprintf('%s/wp-content/media-storage-tmp-%d.tar', ABSPATH, $id);
            $archive = new \PharData($archivePathname);
            $archive->buildFromIterator(new \ArrayIterator($chunk), wp_get_upload_dir()['basedir']);
            $this->storeArchive($archivePathname);
        }
    }
    /**
     * @param string $archive
     */
    protected function storeArchive(string $archive)
    {
        if (!is_file($archive))
            throw new \RuntimeException(sprintf('!is_file(%s', $archive));

        if (!($handle = fopen($archive, 'r')))
            throw new \RuntimeException(sprintf('Unable to open the tar archive we just created??'));

        if ( defined( 'WP_CLI' ) && WP_CLI )
            WP_CLI::line(sprintf('Store: uploading files in archive %s', $archive));

        $data = [
            'extract-archive' => 'tar',
            'stream' => new Stream($handle),
            'containerName' => $this->container->name
        ];
        $this->container->model(Archive::class)->upload($data);

        fclose($handle);
        @unlink($archive);
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
        $hasLimit = array_key_exists('limit', $config) && intval($config['limit']) > 0;
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