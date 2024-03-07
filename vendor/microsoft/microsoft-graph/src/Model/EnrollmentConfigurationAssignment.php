<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* EnrollmentConfigurationAssignment File
* PHP version 7
*
* @category  Library
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
namespace Microsoft\Graph\Model;

/**
* EnrollmentConfigurationAssignment class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class EnrollmentConfigurationAssignment extends Entity
{
    /**
    * Gets the target
    * Represents an assignment to managed devices in the tenant
    *
    * @return DeviceAndAppManagementAssignmentTarget The target
    */
    public function getTarget()
    {
        if (array_key_exists("target", $this->_propDict)) {
            if (is_a($this->_propDict["target"], "Microsoft\Graph\Model\DeviceAndAppManagementAssignmentTarget")) {
                return $this->_propDict["target"];
            } else {
                $this->_propDict["target"] = new DeviceAndAppManagementAssignmentTarget($this->_propDict["target"]);
                return $this->_propDict["target"];
            }
        }
        return null;
    }
    
    /**
    * Sets the target
    * Represents an assignment to managed devices in the tenant
    *
    * @param DeviceAndAppManagementAssignmentTarget $val The target
    *
    * @return EnrollmentConfigurationAssignment
    */
    public function setTarget($val)
    {
        $this->_propDict["target"] = $val;
        return $this;
    }
    
}