<?php

// these should be valid connection parameters for your ldap server
$ldap_config= array('host' => '192.168.123.253',
                    'version' => 3,                    
                    'base' => 'o=netsols,c=de',
                    'dn' => 'cn=admin,o=netsols,c=de',
                    'password' => '******',
                    'filter' => '(objectClass=*)');

// this should be an existing dn which can be fetched with the above connection parameters
$existing_dn = 'uid=wagner,ou=mailAccounts,cn=netsols.de,ou=Domains,o=netsols,c=de';
$rename_dn = 'uid=wagner2,ou=mailAccounts,cn=netsols.de,ou=Domains,o=netsols,c=de';

// these should be parameters for an ldap query that returns at least one entry with one attribute
$search2 = array('filter' => '(&(objectClass=domainRelatedObject)(domainStatus=active))',
                 'base'   => 'ou=Domains,o=netsols,c=de',
                 'parms'  => array('scope' => 'one', 'attributes' => array('cn')));

?>