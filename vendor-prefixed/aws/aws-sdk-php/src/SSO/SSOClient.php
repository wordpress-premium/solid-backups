<?php
namespace Solid_Backups\Strauss\Aws\SSO;

use Solid_Backups\Strauss\Aws\AwsClient;

/**
 * This client is used to interact with the **AWS Single Sign-On** service.
 * @method \Solid_Backups\Strauss\Aws\Result getRoleCredentials(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise getRoleCredentialsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listAccountRoles(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listAccountRolesAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result listAccounts(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise listAccountsAsync(array $args = [])
 * @method \Solid_Backups\Strauss\Aws\Result logout(array $args = [])
 * @method \Solid_Backups\Strauss\GuzzleHttp\Promise\Promise logoutAsync(array $args = [])
 */
class SSOClient extends AwsClient {}
