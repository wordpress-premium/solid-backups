<?php
namespace Solid_Backups\Strauss\Aws\Handler\GuzzleV5;

use Solid_Backups\Strauss\GuzzleHttp\Stream\StreamDecoratorTrait;
use Solid_Backups\Strauss\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use Solid_Backups\Strauss\Psr\Http\Message\StreamInterface as Psr7StreamInterface;

/**
 * Adapts a Guzzle 5 Stream to a PSR-7 Stream.
 *
 * @codeCoverageIgnore
 */
class PsrStream implements Psr7StreamInterface
{
    use StreamDecoratorTrait;

    /** @var GuzzleStreamInterface */
    private $stream;

    public function __construct(GuzzleStreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function rewind()
    {
        $this->stream->seek(0);
    }

    public function getContents()
    {
        return $this->stream->getContents();
    }
}
