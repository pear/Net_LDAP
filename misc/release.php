<?php

require_once 'PEAR/PackageFileManager.php';

$version = '0.6.5';
$notes = 'Bugfix release';
$changelog = '
fixed Bug #890 (proper usuage of get_class)
fixed Bug #952 (Broken Net_LDAP_Entry::add() method)
changed license to LGPL for now (ongoing discussion on pear-dev)
';


$options = array('changelogoldtonew' => false,
                 'simpleoutput' => true,
                 'packagedirectory' => './',
                 'baseinstalldir' => 'Net',
                 'ignore' => array('tests/', 'CVS/', 'project.index', 'release.php', '*~'),
                 'dir_roles' => array('doc' => 'doc'),
                 'version' => $version,
                 'state' => 'beta',
                 'notes' => $notes,
                 'changelognotes' => $changelog,
                 'deps' => array()
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