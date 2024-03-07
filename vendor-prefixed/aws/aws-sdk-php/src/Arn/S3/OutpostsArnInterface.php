<?php
namespace Solid_Backups\Strauss\Aws\Arn\S3;

use Solid_Backups\Strauss\Aws\Arn\ArnInterface;

/**
 * @internal
 */
interface OutpostsArnInterface extends ArnInterface
{
    public function getOutpostId();
}
