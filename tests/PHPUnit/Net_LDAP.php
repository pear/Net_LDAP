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
        if (Net_LDAP::isError($this->ldap)) {
            die($this->ldap->getMessage());
        }
    }
    
    function tearDown()
    {
        $this->ldap->done();
    }
    
    function testConnection()
    {
        return $this->assertEquals('net_ldap', strtolower(get_class($this->ldap)));
    }
    
    function testgetLDAPVersion()
    {
        return $this->assertEquals($this->config['version'], $this->ldap->getLDAPVersion());
    }
    
    function testdnExists()
    {
        $this->assertTrue($this->ldap->dnExists($GLOBALS['existing_dn']));
    }
    
    function testgetEntry()
    {
        $entry = $this->ldap->getEntry($GLOBALS['existing_dn']);
        if (Net_LDAP::isError($entry)) {
            $this->fail($entry->getMessage());
            return false;
        }
        return true;
    }
    
    function testRootDSE()
    {
        $root_dse = $this->ldap->rootDSE();
        $this->assertEquals('net_ldap_rootdse', strtolower(get_class($root_dse)));
    }
    
    function testSchema() 
    {
        $schema = $this->ldap->schema();
        $this->assertEquals('net_ldap_schema', strtolower(get_class($schema)));
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
    
    function testSearch()
    {        
        $test = $GLOBALS['search'];
        $result = $this->ldap->search($test['base'], $test['filter'], $test['parms']);        
        if (Net_LDAP::isError($result)) {
            $this->fail($result->getMessage());
            return false;
        }        
        $entry = $result->shiftEntry();
        if (false === $entry) {
            $this->fail('No entry could be fetched');
            return false;
        }
        $value  = $entry->getValue($test['parms']['attributes'][0], 'single');
        $this->assertTrue(is_string($value) && $value != '', 'Attribute value was not a string or empty');
    }
    
    function testAdd()
    {
        $entry = $this->ldap->getEntry($GLOBALS['existing_dn']);
        if (Net_LDAP::isError($entry)) {
            $this->fail($entry->getMessage());
            return false;
        }
        $newdn = new Net_LDAP_Entry($GLOBALS['rename_dn']);
        if (Net_LDAP::isError($msg = $newdn->add($entry->getValues()))) {
            $this->fail($msg->getMessage());
            return false;
        }
        
        $parts = ldap_explode_dn($entry->dn(), 0);
        list($attr,$value) = explode('=', $parts[0]);
        $newdn->delete(array($attr => $value));
         
        $parts = ldap_explode_dn($newdn->dn(), 0);
        list($attr,$value) = explode('=', $parts[0]);
        $newdn->add(array($attr => $value));
        
        if (Net_LDAP::isError($msg = $this->ldap->add($newdn))) {
            $this->fail($msg->getMessage());
            return false;
        }      
        if (Net_LDAP::isError($msg = $this->ldap->delete($newdn))) {
            $this->fail($msg->getMessage());
            return false;
        }
        return true;
    }
}

?>