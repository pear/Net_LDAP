<?php

class Net_LDAP_Test extends PHPUnit_TestCase
{
    var $ldap;
    var $config;
    
    function Net_LDAP_Test($name)
    {
        $this->PHPUnit_TestCase($name);
    }
    
    function setUp()
    {                            
        $this->config = $GLOBALS['ldap_config'];
        $this->ldap = Net_LDAP::connect($this->config);
    }
    
    function testConnection()
    {
        return $this->assertEquals('net_ldap', get_class($this->ldap));
    }
    
    function testgetLDAPVersion()
    {
        return $this->assertEquals($this->config['version'], $this->ldap->getLDAPVersion());
    }
    
    function testRootDSE()
    {
        $root_dse = $this->ldap->rootDSE();
        $this->assertEquals('net_ldap_rootdse', get_class($root_dse));
    }
    
    function testSchema() 
    {
        $schema = $this->ldap->schema();
        $this->assertEquals('net_ldap_schema', get_class($schema));
    }
    
    function testUTF8()
    {
        $array1 = array('street' => 'Bärensteiner Str. 30');
        $test   = $this->ldap->utf8Encode($array1);
        $this->assertEquals(utf8_encode($array1['street']), $test['street'],
                            'Encoding an attribute that should be encoded, was not.');        
        
        $test = $this->ldap->utf8Decode($test);
        $this->assertEquals($array1['street'], $test['street'],
                            'An attribute that should have been decoded, was not');
                            
        $array2 = array('rfc822Mailbox' => 'krämer');
        $test   = $this->ldap->utf8Encode($array2);
        $this->assertEquals($array2['rfc822Mailbox'], $test['rfc822Mailbox'],
                            'An attribute that should not be encoded, was encoded');

        $test = $this->ldap->utf8Decode(array('rfc822Mailbox' => utf8_encode('krämer')));
        $this->assertFalse($array2['rfc822Mailbox'] == $test['rfc822Mailbox'],
                           'An attribute that should not be decoded, was decoded');
        
    }
    
    function testSearch1()
    {        
        $base = $GLOBALS['existing_dn'];
        $parms = array('scope' => 'base', 'attributes' => array('objectClass'));

        $result = $this->ldap->search($base, null, $parms);
        $this->assertFalse(Net_Ldap::isError($result), 'Error searching for a specific entry');

        $this->assertFalse( $result->count() == 0, 'Specified exisiting dn could not be found');
        
        $entry = $result->shiftEntry();
        $this->assertEquals('net_ldap_entry', get_class($entry), 'Could not fetch specified entry');
        
        $oc = $entry->get_value('objectClass');
        $this->assertTrue(is_array($oc), 'objectClass attribute value was no array');
    }
    
    function testSearch2()
    {
        $test = $GLOBALS['search2'];
        
        $result = $this->ldap->search($test['base'], $test['filter'], $test['parms']);        
        $this->assertFalse(Net_Ldap::isError($result), 'Search failed');
        
        if(!Net_LDAP::isError($result)) {
            $this->assertTrue($result->count() >= 1, 'Empty Result set');
            
            $entry = $result->shiftEntry();
            $this->assertTrue($entry, 'No entry could be fetched');
            
            $attrs = array_keys($test['parms']['attributes']);
            $value  = $entry->get_value($attrs[0], 'single');
            $this->assertTrue(is_string($value) && $value != '', 'Attribute value was not a string or empty');
        }
    }
    
    function testRename()
    {
        if ($this->assertTrue(isset($GLOBALS['existing_dn']), 'exisiting dn not set in config')) {
            return false;
        }
        if ($this->assertTrue(isset($GLOBALS['rename_dn']), 'rename dn not set in config')) {
            return false;
        }
        
        $entry = $this->ldap->getEntry($GLOBALS['existing_dn']);        
        $this->assertEquals('net_ldap_entry', get_class($entry));

        foreach (array($GLOBALS['rename_dn'], $GLOBALS['existing_dn']) as $dn) {
            $entry->dn($dn);
            $msg = $entry->update();        
            $this->assertFalse(Net_LDAP::isError($msg), 'Could not rename entry');
        }
    }
}

?>