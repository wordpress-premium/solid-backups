<?php

// For older (pre-2.7.2) verions of google/apiclient
if (
    file_exists(__DIR__ . '/../apiclient/src/Google/Client.php')
    && !class_exists('Solid_Bckups_Solid_BackupsGoogle_Client', false)
) {
    require_once(__DIR__ . '/../apiclient/src/Google/Client.php');
    if (
        defined('Solid_Bckups_Solid_BackupsGoogle_Client::LIBVER')
        && version_compare(Solid_Bckups_Solid_BackupsGoogle_Client::LIBVER, '2.7.2', '<=')
    ) {
        $servicesClassMap = [
            'Solid_Backups\\Strauss\\Google\\Client' => 'Solid_Bckups_Solid_BackupsGoogle_Client',
            'Solid_Backups\\Strauss\\Google\\Service' => 'Solid_Bckups_Solid_BackupsGoogle_Service',
            'Solid_Backups\\Strauss\\Google\\Service\\Resource' => 'Solid_Bckups_Solid_BackupsGoogle_Service_Resource',
            'Solid_Backups\\Strauss\\Google\\Model' => 'Solid_Bckups_Solid_BackupsGoogle_Model',
            'Solid_Backups\\Strauss\\Google\\Collection' => 'Solid_Bckups_Solid_BackupsGoogle_Collection',
        ];
        foreach ($servicesClassMap as $alias => $class) {
            class_alias($class, $alias);
        }
    }
}
spl_autoload_register(function ($class) {
    if (0 === strpos($class, 'Google_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     Google_Service_Speech_Resource_Operations
        //      => Solid_Backups\Strauss\Google\Service\Speech\Resource\Operations
        $classExists = class_exists($newClass = str_replace('_', '\\', $class));
        if ($classExists) {
            return true;
        }
    }
}, true, true);
