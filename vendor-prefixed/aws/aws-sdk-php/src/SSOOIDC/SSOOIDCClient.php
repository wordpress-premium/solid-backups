<?php
namespace Solid_Backups\Strauss\Aws\SSOOIDC;

use Solid_Backups\Strauss\Aws\AwsClient;

/**
 * This client is used to interact with the **AWS SSO OIDC** service.
 * @method \Solid_Backups\Strauss\Aws\Result createToken(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise createTokenAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result registerClient(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise registerClientAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result startDeviceAuthorization(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise startDeviceAuthorizationAsync(array $args = [])
 */
class SSOOIDCClient extends AwsClient {}
