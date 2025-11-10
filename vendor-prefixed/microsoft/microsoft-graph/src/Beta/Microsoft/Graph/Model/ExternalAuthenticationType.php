<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* ExternalAuthenticationType File
* PHP version 7
*
* @category  Library
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
namespace Solid_Backups\Strauss\Beta\Microsoft\Graph\Model;

use Solid_Backups\Strauss\Microsoft\Graph\Core\Enum;

/**
* ExternalAuthenticationType class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class ExternalAuthenticationType extends Enum
{
    /**
    * The Enum ExternalAuthenticationType
    */
    const PASSTHRU = "passthru";
    const AAD_PRE_AUTHENTICATION = "aadPreAuthentication";
}