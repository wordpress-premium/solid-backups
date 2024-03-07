<?php
namespace Solid_Backups\Strauss\Aws\Api\Parser;

use Solid_Backups\Strauss\Aws\Api\StructureShape;
use Solid_Backups\Strauss\Aws\Api\Service;
use Solid_Backups\Strauss\Psr\Http\Message\ResponseInterface;
use Solid_Backups\Strauss\Psr\Http\Message\StreamInterface;

/**
 * @internal Implements REST-XML parsing (e.g., S3, CloudFront, etc...)
 */
class RestXmlParser extends AbstractRestParser
{
    use PayloadParserTrait;

    /**
     * @param Service   $api    Service description
     * @param XmlParser $parser XML body parser
     */
    public function __construct(Service $api, XmlParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new XmlParser();
    }

    protected function payload(
        ResponseInterface $response,
        StructureShape $member,
        array &$result
    ) {
        $result += $this->parseMemberFromStream($response->getBody(), $member, $response);
    }

    public function parseMemberFromStream(
        StreamInterface $stream,
        StructureShape $member,
        $response
    ) {
        $xml = $this->parseXml($stream, $response);
        return $this->parser->parse($member, $xml);
    }
}
