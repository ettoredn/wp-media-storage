<?php
/**
 * Created by PhpStorm.
 * User: ettore
 * Date: 10/06/16
 * Time: 18:47
 */

namespace WPMediaStorage;


use GuzzleHttp\ClientInterface;
use OpenCloud\Common\Api\ApiInterface;
use OpenCloud\Common\Resource\OperatorResource;
use OpenStack\ObjectStore\v1\Params;

/**
 * @property \OpenStack\ObjectStore\v1\Api $api
 */
class Archive extends OperatorResource
{
    /**
     * @var Params
     */
    protected $params;

    /** @var string */
    public $containerName;

    public function __construct(ClientInterface $client, ApiInterface $api)
    {
        parent::__construct($client, $api);
        $this->params = new Params();
    }

    /**
     * https://github.com/openstack/swift/blob/master/swift/common/middleware/bulk.py
     * 
     * @param array $data
     * @return \OpenCloud\Common\Resource\AbstractResource
     */
    public function upload(array $data)
    {
        $definition = [
            'method' => 'PUT',
            'path'   => '{containerName}/{+path}',
            'params' => [
                'containerName'      => $this->params->containerName(),
                'path' => [
                    'location'    => Params::URL,
                    'description' => 'Pseudo-directory where the archive will be extracted e.g. "foo"',
                ],
                'extract-archive' => [
                    'location' => Params::QUERY,
                    'type' => Params::STRING_TYPE,
                    'description' => 'Archive format'
                ],
                'content'            => $this->params->content(),
                'stream'             => $this->params->stream(),
                'contentType'        => $this->params->contentType(),
                'detectContentType'  => $this->params->detectContentType(),
                'metadata'           => $this->params->metadata('object'),
            ],
        ];

        $response = $this->execute($definition, $data);
        return $this->populateFromResponse($response);
    }
}