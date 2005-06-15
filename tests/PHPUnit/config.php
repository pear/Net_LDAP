<?php

// these should be valid connection parameters for your ldap server
$ldap_config= array('host' => 'localhost',
                    'version' => 3,
		            'options' => array('LDAP_OPT_REFERRALS' => 0),
                    'base' => 'o=netsols,c=de',
                    'dn' => 'cn=admin,o=netsols,c=de',
                    'password' => '********',
                    'filter' => '(objectClass=*)');

// this should be an existing dn which can be fetched with the above connection parameters
$existing_dn = 'cn=Jan Wagner,ou=testing,o=netsols,c=de';
$rename_dn = 'cn=Jan Wagner2,ou=testing,o=netsols,c=de';
$delete_attr = array('mail' => array('wagner@netsols.de', 'skywalker@wh2.tu-dresden.de'));

// these should be parameters for an ldap query that returns at least one entry with one attribute
$search2 = array('filter' => '(objectClass=*)',
                 'base'   => 'ou=testing,o=netsols,c=de',
                 'parms'  => array('scope' => 'one', 'attributes' => array('cn')));

?>