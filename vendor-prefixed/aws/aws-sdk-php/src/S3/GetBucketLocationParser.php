<?php
namespace Solid_Backups\Strauss\Aws\S3;

use Solid_Backups\Strauss\Aws\Api\Parser\AbstractParser;
use Solid_Backups\Strauss\Aws\Api\StructureShape;
use Solid_Backups\Strauss\Aws\CommandInterface;
use Solid_Backups\Strauss\Psr\Http\Message\ResponseInterface;
use Solid_Backups\Strauss\Psr\Http\Message\StreamInterface;

/**
 * @internal Decorates a parser for the S3 service to correctly handle the
 *           GetBucketLocation operation.
 */
class GetBucketLocationParser extends AbstractParser
{
    /**
     * @param callable $parser Parser to wrap.
     */
    public function __construct(callable $parser)
    {
        $this->parser = $parser;
    }

    public function __invoke(
        CommandInterface $command,
        ResponseInterface $response
    ) {
        $fn = $this->parser;
        $result = $fn($command, $response);

        if ($command->getName() === 'GetBucketLocation') {
            $location = 'us-east-1';
            if (preg_match('/>(.+?)<\/LocationConstraint>/', $response->getBody(), $matches)) {
                $location = $matches[1] === 'EU' ? 'eu-west-1' : $matches[1];
            }
            $result['LocationConstraint'] = $location;
        }

        return $result;
    }

    public function parseMemberFromStream(
        StreamInterface $stream,
        StructureShape $member,
        $response
    ) {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
