<?php

// these should be valid connection parameters for your ldap server
$ldap_config= array('host' => 'localhost',
                    'version' => 3,
                    'starttls' => false,
                    'basedn' => 'o=netsols,c=de',
                    'binddn' => 'cn=admin,o=netsols,c=de',
                    'bindpw' => '',
                    'filter' => '(objectClass=*)');

// this should be an existing dn which can be fetched with the above connection parameters
$existing_dn = 'cn=Jan Wagner,ou=testing,o=netsols,c=de';
$existing_dn_changes = array('add' => array('cn' => array('Testing')),
                             'replace' => array('mobile' => array('01709442191'))
                             );

$rename_dn = 'cn=Kai Naumann,ou=testing,o=netsols,c=de';

// these should be parameters for an ldap query that returns at least one entry with one attribute
$search = array('filter' => '(&(objectClass=ispmanDomain)(ispmanStatus=active))',
                'base'   => 'ou=ispman,o=netsols,c=de',
                'parms'  => array('scope' => 'one', 'attributes' => array('cn')));

?>