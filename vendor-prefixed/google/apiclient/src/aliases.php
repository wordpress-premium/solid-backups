<?php

if (class_exists('Solid_Bckups_Solid_BackupsGoogle_Client', false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}

$classMap = [
    'Solid_Backups\\Strauss\\Google\\Client' => 'Solid_Bckups_Solid_BackupsGoogle_Client',
    'Solid_Backups\\Strauss\\Google\\Service' => 'Solid_Bckups_Solid_BackupsGoogle_Service',
    'Solid_Backups\\Strauss\\Google\\AccessToken\\Revoke' => 'Solid_Bckups_Solid_BackupsGoogle_AccessToken_Revoke',
    'Solid_Backups\\Strauss\\Google\\AccessToken\\Verify' => 'Solid_Bckups_Solid_BackupsGoogle_AccessToken_Verify',
    'Solid_Backups\\Strauss\\Google\\Model' => 'Solid_Bckups_Solid_BackupsGoogle_Model',
    'Solid_Backups\\Strauss\\Google\\Utils\\UriTemplate' => 'Solid_Bckups_Solid_BackupsGoogle_Utils_UriTemplate',
    'Solid_Backups\\Strauss\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle6AuthHandler',
    'Solid_Backups\\Strauss\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle7AuthHandler',
    'Solid_Backups\\Strauss\\Google\\AuthHandler\\Guzzle5AuthHandler' => 'Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle5AuthHandler',
    'Solid_Backups\\Strauss\\Google\\AuthHandler\\AuthHandlerFactory' => 'Solid_Bckups_Solid_BackupsGoogle_AuthHandler_AuthHandlerFactory',
    'Solid_Backups\\Strauss\\Google\\Http\\Batch' => 'Solid_Bckups_Solid_BackupsGoogle_Http_Batch',
    'Solid_Backups\\Strauss\\Google\\Http\\MediaFileUpload' => 'Solid_Bckups_Solid_BackupsGoogle_Http_MediaFileUpload',
    'Solid_Backups\\Strauss\\Google\\Http\\REST' => 'Solid_Bckups_Solid_BackupsGoogle_Http_REST',
    'Solid_Backups\\Strauss\\Google\\Task\\Retryable' => 'Solid_Bckups_Solid_BackupsGoogle_Task_Retryable',
    'Solid_Backups\\Strauss\\Google\\Task\\Exception' => 'Solid_Bckups_Solid_BackupsGoogle_Task_Exception',
    'Solid_Backups\\Strauss\\Google\\Task\\Runner' => 'Solid_Bckups_Solid_BackupsGoogle_Task_Runner',
    'Solid_Backups\\Strauss\\Google\\Collection' => 'Solid_Bckups_Solid_BackupsGoogle_Collection',
    'Solid_Backups\\Strauss\\Google\\Service\\Exception' => 'Solid_Bckups_Solid_BackupsGoogle_Service_Exception',
    'Solid_Backups\\Strauss\\Google\\Service\\Resource' => 'Solid_Bckups_Solid_BackupsGoogle_Service_Resource',
    'Solid_Backups\\Strauss\\Google\\Exception' => 'Solid_Bckups_Solid_BackupsGoogle_Exception',
];

foreach ($classMap as $class => $alias) {
    class_alias($class, $alias);
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Solid_Bckups_Solid_BackupsGoogle_Task_Composer extends \Solid_Backups\Strauss\Google\Task\Composer
{
}

/** @phpstan-ignore-next-line */
if (\false) {
    class Solid_Bckups_Solid_BackupsGoogle_AccessToken_Revoke extends \Solid_Backups\Strauss\Google\AccessToken\Revoke
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_AccessToken_Verify extends \Solid_Backups\Strauss\Google\AccessToken\Verify
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_AuthHandler_AuthHandlerFactory extends \Solid_Backups\Strauss\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle5AuthHandler extends \Solid_Backups\Strauss\Google\AuthHandler\Guzzle5AuthHandler
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle6AuthHandler extends \Solid_Backups\Strauss\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_AuthHandler_Guzzle7AuthHandler extends \Solid_Backups\Strauss\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Client extends \Solid_Backups\Strauss\Google\Client
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Collection extends \Solid_Backups\Strauss\Google\Collection
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Exception extends \Solid_Backups\Strauss\Google\Exception
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Http_Batch extends \Solid_Backups\Strauss\Google\Http\Batch
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Http_MediaFileUpload extends \Solid_Backups\Strauss\Google\Http\MediaFileUpload
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Http_REST extends \Solid_Backups\Strauss\Google\Http\REST
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Model extends \Solid_Backups\Strauss\Google\Model
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Service extends \Solid_Backups\Strauss\Google\Service
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Service_Exception extends \Solid_Backups\Strauss\Google\Service\Exception
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Service_Resource extends \Solid_Backups\Strauss\Google\Service\Resource
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Task_Exception extends \Solid_Backups\Strauss\Google\Task\Exception
    {
    }
    interface Solid_Bckups_Solid_BackupsGoogle_Task_Retryable extends \Solid_Backups\Strauss\Google\Task\Retryable
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Task_Runner extends \Solid_Backups\Strauss\Google\Task\Runner
    {
    }
    class Solid_Bckups_Solid_BackupsGoogle_Utils_UriTemplate extends \Solid_Backups\Strauss\Google\Utils\UriTemplate
    {
    }
}
