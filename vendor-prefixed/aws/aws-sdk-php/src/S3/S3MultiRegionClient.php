<?php
namespace Solid_Backups\Strauss\Aws\S3;

use Solid_Backups\Strauss\Aws\CacheInterface;
use Solid_Backups\Strauss\Aws\CommandInterface;
use Solid_Backups\Strauss\Aws\LruArrayCache;
use Solid_Backups\Strauss\Aws\MultiRegionClient as BaseClient;
use Solid_Backups\Strauss\Aws\Exception\AwsException;
use Solid_Backups\Strauss\Aws\S3\Exception\PermanentRedirectException;
use Solid_Backups\Strauss\GuzzleHttp\Promise;

/**
 * **Amazon Simple Storage Service** multi-region client.
 *
 * @method \Solid_Backups\Strauss\Aws\Result abortMultipartUpload(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise abortMultipartUploadAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result completeMultipartUpload(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise completeMultipartUploadAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result copyObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise copyObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result createBucket(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise createBucketAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result createMultipartUpload(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise createMultipartUploadAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucket(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketAnalyticsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketCors(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketCorsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketEncryption(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketEncryptionAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketIntelligentTieringConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketInventoryConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketInventoryConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketLifecycle(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketLifecycleAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketMetricsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketMetricsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketOwnershipControls(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketOwnershipControlsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketPolicy(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketPolicyAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketReplication(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketReplicationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteBucketWebsite(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteBucketWebsiteAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteObjectTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteObjectTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deleteObjects(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deleteObjectsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result deletePublicAccessBlock(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise deletePublicAccessBlockAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketAccelerateConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketAccelerateConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketAcl(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketAclAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketAnalyticsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketCors(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketCorsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketEncryption(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketEncryptionAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketIntelligentTieringConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketInventoryConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketInventoryConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketLifecycle(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketLifecycleAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketLifecycleConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketLifecycleConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketLocation(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketLocationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketLogging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketLoggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketMetricsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketMetricsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketNotification(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketNotificationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketNotificationConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketNotificationConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketOwnershipControls(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketOwnershipControlsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketPolicy(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketPolicyAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketPolicyStatus(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketPolicyStatusAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketReplication(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketReplicationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketRequestPayment(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketRequestPaymentAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketVersioning(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketVersioningAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getBucketWebsite(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getBucketWebsiteAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectAcl(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectAclAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectAttributes(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectAttributesAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectLegalHold(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectLegalHoldAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectLockConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectLockConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectRetention(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectRetentionAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getObjectTorrent(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getObjectTorrentAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result getPublicAccessBlock(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getPublicAccessBlockAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result headBucket(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise headBucketAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result headObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise headObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listBucketAnalyticsConfigurations(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listBucketAnalyticsConfigurationsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listBucketIntelligentTieringConfigurations(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listBucketIntelligentTieringConfigurationsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listBucketInventoryConfigurations(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listBucketInventoryConfigurationsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listBucketMetricsConfigurations(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listBucketMetricsConfigurationsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listBuckets(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listBucketsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listMultipartUploads(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listMultipartUploadsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listObjectVersions(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listObjectVersionsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listObjects(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listObjectsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listObjectsV2(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listObjectsV2Async(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listParts(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listPartsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketAccelerateConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketAccelerateConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketAcl(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketAclAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketAnalyticsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketCors(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketCorsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketEncryption(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketEncryptionAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketIntelligentTieringConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketInventoryConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketInventoryConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketLifecycle(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketLifecycleAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketLifecycleConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketLifecycleConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketLogging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketLoggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketMetricsConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketMetricsConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketNotification(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketNotificationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketNotificationConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketNotificationConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketOwnershipControls(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketOwnershipControlsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketPolicy(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketPolicyAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketReplication(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketReplicationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketRequestPayment(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketRequestPaymentAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketVersioning(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketVersioningAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putBucketWebsite(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putBucketWebsiteAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObjectAcl(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectAclAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObjectLegalHold(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectLegalHoldAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObjectLockConfiguration(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectLockConfigurationAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObjectRetention(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectRetentionAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putObjectTagging(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putObjectTaggingAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result putPublicAccessBlock(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise putPublicAccessBlockAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result restoreObject(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise restoreObjectAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result selectObjectContent(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise selectObjectContentAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result uploadPart(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise uploadPartAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result uploadPartCopy(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise uploadPartCopyAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result writeGetObjectResponse(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise writeGetObjectResponseAsync(array $args = [])
 */
class S3MultiRegionClient extends BaseClient implements S3ClientInterface
{
    use S3ClientTrait;

    /** @var CacheInterface */
    private $cache;

    public static function getArguments()
    {
        $args = parent::getArguments();
        $regionDef = $args['region'] + ['default' => function (array &$args) {
            $availableRegions = array_keys($args['partition']['regions']);
            return end($availableRegions);
        }];
        unset($args['region']);

        return $args + [
            'bucket_region_cache' => [
                'type' => 'config',
                'valid' => [CacheInterface::class],
                'doc' => 'Cache of regions in which given buckets are located.',
                'default' => function () { return new LruArrayCache; },
            ],
            'region' => $regionDef,
        ];
    }

    public function __construct(array $args)
    {
        parent::__construct($args);
        $this->cache = $this->getConfig('bucket_region_cache');

        $this->getHandlerList()->prependInit(
            $this->determineRegionMiddleware(),
            'determine_region'
        );
    }

    private function determineRegionMiddleware()
    {
        return function (callable $handler) {
            return function (CommandInterface $command) use ($handler) {
                $cacheKey = $this->getCacheKey($command['Bucket']);
                if (
                    empty($command['@region']) &&
                    $region = $this->cache->get($cacheKey)
                ) {
                    $command['@region'] = $region;
                }

                return Promise\Coroutine::of(function () use (
                    $handler,
                    $command,
                    $cacheKey
                ) {
                    try {
                        yield $handler($command);
                    } catch (PermanentRedirectException $e) {
                        if (empty($command['Bucket'])) {
                            throw $e;
                        }
                        $result = $e->getResult();
                        $region = null;
                        if (isset($result['@metadata']['headers']['x-amz-bucket-region'])) {
                            $region = $result['@metadata']['headers']['x-amz-bucket-region'];
                            $this->cache->set($cacheKey, $region);
                        } else {
                            $region = (yield $this->determineBucketRegionAsync(
                                $command['Bucket']
                            ));
                        }

                        $command['@region'] = $region;
                        yield $handler($command);
                    } catch (AwsException $e) {
                        if ($e->getAwsErrorCode() === 'AuthorizationHeaderMalformed') {
                            $region = $this->determineBucketRegionFromExceptionBody(
                                $e->getResponse()
                            );
                            if (!empty($region)) {
                                $this->cache->set($cacheKey, $region);

                                $command['@region'] = $region;
                                yield $handler($command);
                            } else {
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }
                });
            };
        };
    }

    public function createPresignedRequest(CommandInterface $command, $expires, array $options = [])
    {
        if (empty($command['Bucket'])) {
            throw new \InvalidArgumentException('The S3\\MultiRegionClient'
                . ' cannot create presigned requests for commands without a'
                . ' specified bucket.');
        }

        /** @var S3ClientInterface $client */
        $client = $this->getClientFromPool(
            $this->determineBucketRegion($command['Bucket'])
        );
        return $client->createPresignedRequest(
            $client->getCommand($command->getName(), $command->toArray()),
            $expires
        );
    }

    public function getObjectUrl($bucket, $key)
    {
        /** @var S3Client $regionalClient */
        $regionalClient = $this->getClientFromPool(
            $this->determineBucketRegion($bucket)
        );

        return $regionalClient->getObjectUrl($bucket, $key);
    }

    public function determineBucketRegionAsync($bucketName)
    {
        $cacheKey = $this->getCacheKey($bucketName);
        if ($cached = $this->cache->get($cacheKey)) {
            return Promise\Create::promiseFor($cached);
        }

        /** @var S3ClientInterface $regionalClient */
        $regionalClient = $this->getClientFromPool();
        return $regionalClient->determineBucketRegionAsync($bucketName)
            ->then(
                function ($region) use ($cacheKey) {
                    $this->cache->set($cacheKey, $region);

                    return $region;
                }
            );
    }

    private function getCacheKey($bucketName)
    {
        return "aws:s3:{$bucketName}:location";
    }
}
