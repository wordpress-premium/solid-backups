<?php
namespace Solid_Backups\Strauss\Aws\Handler\GuzzleV5;

use Solid_Backups\Strauss\GuzzleHttp\Stream\StreamDecoratorTrait;
use Solid_Backups\Strauss\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use Solid_Backups\Strauss\Psr\Http\Message\StreamInterface as Psr7StreamInterface;

/**
 * Adapts a PSR-7 Stream to a Guzzle 5 Stream.
 *
 * @codeCoverageIgnore
 */
class GuzzleStream implements GuzzleStreamInterface
{
    use StreamDecoratorTrait;

    /** @var Psr7StreamInterface */
    private $stream;

    public function __construct(Psr7StreamInterface $stream)
    {
        $this->stream = $stream;
    }
}
