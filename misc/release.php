<?php

require_once 'PEAR/PackageFileManager.php';

$version = '0.6.6';
$notes = 'Bugfix release';
$changelog = '
Fixed bug #1060 (Wrong array_push_call in Net_LDAP_Search)
Fixed bug #1136,#2985 (Recursive delete not working)
Fixed bug #2391 (Implemented getOption()/SetOption() for LDAP_OPT_REFERRALS)
Fixed bug #2860 (Call-time pass-by-reference)
Fixed bug #2986 (Missing parameter to ldap_error in LDAP.php)
Fixed bug #3560 (Missing test.php in docs)
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