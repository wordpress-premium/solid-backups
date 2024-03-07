<?php
namespace Solid_Backups\Strauss\Aws\Api\Serializer;

use Solid_Backups\Strauss\Aws\Api\Service;
use Solid_Backups\Strauss\Aws\Api\StructureShape;

/**
 * Serializes requests for the REST-JSON protocol.
 * @internal
 */
class RestJsonSerializer extends RestSerializer
{
    /** @var JsonBody */
    private $jsonFormatter;

    /** @var string */
    private $contentType;

    /**
     * @param Service  $api           Service API description
     * @param string   $endpoint      Endpoint to connect to
     * @param JsonBody $jsonFormatter Optional JSON formatter to use
     */
    public function __construct(
        Service $api,
        $endpoint,
        JsonBody $jsonFormatter = null
    ) {
        parent::__construct($api, $endpoint);
        $this->contentType = JsonBody::getContentType($api);
        $this->jsonFormatter = $jsonFormatter ?: new JsonBody($api);
    }

    protected function payload(StructureShape $member, array $value, array &$opts)
    {
        $body = isset($value) ?
            ((string) $this->jsonFormatter->build($member, $value))
            : "{}";
        $opts['headers']['Content-Type'] = $this->contentType;
        $opts['body'] = $body;
    }
}
