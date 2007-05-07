<?php
/**
* This is a short example on how to add a new entry to your
* directory using Net_LDAP.
*/

// We use the connecting.php example to get a link to our server.
// This file will also include all required basic Net_LDAP classes.
include_once 'connecting.php';

// Okay, we should have a valid link now.
// We must define the DN of the new entry. The DN is the
// global unique path to the data in the directory server,
// similar to a path name in your filesystem.
// Since we want to be a little flexible, we make the base
// dynamic, so it is enough to change the base-dn in your
// $ldap_config array.
$dn = 'cn=Foo Bar,'.$ldap_config['base'];


// It is a good idea to first look if the entry, that should be added,
// is already present:
if ($ldap->dnExists($dn)) {
	die('Could not add entry! Entry already exists!');
}

// The entry does not exist so far, we can safely add him.
// But first, we must construct the entry.
// This is, because Net_LDAP was build to make changes only
// locally (in your script), not directly on the server.
$new_entry = new Net_LDAP_Entry(&$ldap, $dn);

// We add some basic attributes:
$new_entry->add( array(
	'sn'             => 'Foo',
	'gn'             => 'Bar',
	'employeeNumber' => 123456
));

// Finally add the entry in the server:
$result = $ldap->add($new_entry);
if (Net_LDAP::isError($result)) {
	die('Unable to add entry: '.$result->getMessage());
}

// The entry is now present in the directory server.
?>