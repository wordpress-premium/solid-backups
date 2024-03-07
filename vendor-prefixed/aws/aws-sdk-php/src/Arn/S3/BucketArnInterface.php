<?php
namespace Solid_Backups\Strauss\Aws\Arn\S3;

use Solid_Backups\Strauss\Aws\Arn\ArnInterface;

/**
 * @internal
 */
interface BucketArnInterface extends ArnInterface
{
    public function getBucketName();
}
