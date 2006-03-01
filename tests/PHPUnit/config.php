<?php

// these should be valid connection parameters for your ldap server
$ldap_config= array('host' => 'localhost',
                    'version' => 3,
                    'starttls' => false,
                    'basedn' => 'o=Medemail,c=AU',
                    'binddn' => 'cn=Directory Manager',
                    'bindpw' => 'theCORRECTpassword',
                    'filter' => '(objectClass=*)');

// Invalid connection parameters, possibly wrong password or similar.
$ldap_invalid_config= array('host' => 'localhost',
                    'version' => 3,
                    'starttls' => false,
                    'basedn' => 'o=Medemail,c=AU',
                    'binddn' => 'cn=Directory Manager',
                    'bindpw' => 'theWRONGpassWORD',
                    'filter' => '(objectClass=*)');

// An array of hosts, only one of which needs to be valid.
$ldap_array_config= array('host' => array('ford.babel.office', 'eddie.babel.office', 'localhost'),
                    'version' => 3,
                    'starttls' => false,
                    'basedn' => 'o=Medemail,c=AU',
                    'binddn' => 'cn=Directory Manager',
                    'bindpw' => 'theCORRECTpassword',
                    'filter' => '(objectClass=*)');

// An array of hosts, all of which are invalid.
$ldap_invalid_array_config= array('host' => array('ford.babel.office', 'eddie.babel.office'),
                    'version' => 3,
                    'starttls' => false,
                    'basedn' => 'o=Medemail,c=AU',
                    'binddn' => 'cn=Directory Manager',
                    'bindpw' => 'hakeswill',
                    'filter' => '(objectClass=*)');

// this should be an existing dn which can be fetched with the above connection parameters
$existing_dn = 'uid=delp,ou=People,o=Medemail,c=AU';
$existing_dn_changes = array('add' => array('cn' => array('Testing')),
                             'replace' => array('mobile' => array('01709442191'))
                             );

$rename_dn = 'uid=delp2,ou=People,o=Medemail,c=AU';

// these should be parameters for an ldap query that returns at least one entry with one attribute
$search = array('filter' => '(&(objectClass=posixAccount)(uid=delp))',
                'base'   => 'ou=People,o=Medemail,c=AU',
                'parms'  => array('scope' => 'one', 'attributes' => array('cn')));

// these should be parameters for an ldap query that returns at least 3 entries with one attribute
$multi_search = array('filter' => '(objectClass=posixAccount)',
                'base'   => 'ou=People,o=Medemail,c=AU',
                'parms'  => array('scope' => 'one', 'attributes' => array('cn')));

?>