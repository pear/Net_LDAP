<?php

require_once 'PEAR/PackageFileManager.php';

$version = '0.6.7';
$notes = 'Bugfix release';
$changelog = '
Fixed bug #4453 (static connect function made call to $this)
Fixed bug #4483 (changed default behavior of Net_LDAP::getEntry to return all attributes)
Fixed bug #4589 (empty array for attribute values in delete will now delete all values)
Fixed another bug in Net_LDAP::add while working on bug#4589 (add would not add new values at the attribute level due to ldap_modify being called)
';

$options = array('changelogoldtonew' => false,
                 'simpleoutput' => true,
                 'packagedirectory' => './',
                 'baseinstalldir' => 'Net',
                 'ignore' => array('tests/PHPUnit/', 'CVS/', 'project.index', 'release.php', '*~'),
		         'exceptions' => array('tests/tests.php' => 'doc'),
                 'dir_roles' => array('doc' => 'doc'),
                 'version' => $version,
                 'state' => 'beta',
                 'notes' => $notes,
                 'changelognotes' => $changelog,
                 'deps' => array(),
		         'license' => 'LGPL'
                 );


$package = new PEAR_PackageFileManager;

$e = $package->setOptions($options);
if (PEAR::isError($e)) {
    die($e->getMessage());
}

$e = $package->addRole('', 'doc');
if (PEAR::isError($e)) {
    die($e->getMessage());
}

$package->debugPackageFile();
$package->writePackageFile();

?>