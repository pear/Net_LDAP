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
        $this->assertTrue(is_a($root_dse, 'net_ldap_rootdse'));
    }
    
    function testSchema() 
    {
        $schema = $this->ldap->schema();
        $this->assertTrue(is_a($schema, 'net_ldap_schema'));
    }
    
    function testUTF8()
    {
        $array1 = array('street' => 'B�rensteiner Str. 30');
        $test   = $this->ldap->utf8Encode($array1);
        $this->assertEquals(utf8_encode($array1['street']), $test['street'],
                            'Encoding an attribute that should be encoded, was not.');        
        
        $test = $this->ldap->utf8Decode($test);
        $this->assertEquals($array1['street'], $test['street'],
                            'An attribute that should have been decoded, was not');
                            
        $array2 = array('rfc822Mailbox' => 'kr�mer');
        $test   = $this->ldap->utf8Encode($array2);
        $this->assertEquals($array2['rfc822Mailbox'], $test['rfc822Mailbox'],
                            'An attribute that should not be encoded, was encoded');

        $test = $this->ldap->utf8Decode(array('rfc822Mailbox' => utf8_encode('kr�mer')));
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
    
    function testReBind()
    {
        $this->ldap->done();
        $config = $this->config;
        $binddn = $config["binddn"];
        $bindpw = $config["bindpw"];
        unset($config["binddn"]);
        unset($config["bindpw"]);
        $ldap =& new Net_LDAP($config);
        // bind anonymously
        $msg = $ldap->bind();
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        // bind with credentials
        $msg = $ldap->bind($binddn, $bindpw);
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        return true;        
    }
    
    function testRecursiveDelete()
    {
        // get existing entry for copy
        $entry = &$this->ldap->getEntry($GLOBALS['existing_dn']);
        if (Net_LDAP::isError($entry)) {
            $this->fail($entry->getMessage());
            return false;
        }
        // copy to rename dn
        $newentry = &$entry->copy($this->ldap, $GLOBALS['rename_dn'], false);
        if (Net_LDAP::isError($newentry)) {
            $this->fail($newentry->getMessage());
            return false;
        }
        // get rdn to prepend to newentry dn so it becomes a subentry
        $rdn = ldap_explode_dn($newentry->dn(), 0);
        if (isset($rdn['count'])) {
            unset($rdn['count']);
        }
        $rdn = array_shift($rdn);
        
        // add a copy of new entry as subentry to itself
        $msg = $newentry->copy($this->ldap, $rdn, true);
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        // test deleting newentry with its subentry
        $msg = $this->ldap->delete($newentry->dn(), true);
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        return true;
    }
    
    function testModifyAdd()
    {
        global $existing_dn, $existing_dn_changes;
        
        $msg = $this->ldap->modify($existing_dn,
                                   array('add' => $existing_dn_changes['add']));
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        
        $entry = &$this->ldap->getEntry($existing_dn);
        foreach($existing_dn_changes['add'] as $attr => $vals) {
            $values = $entry->getValue($attr, 'all');
            foreach ($vals as $val) {
                $this->assertTrue(in_array($val, $values),
                                  "$attr value: $val not in attribute values");
            }
        }
        return true;
    }
    
    function testModifyDelete()
    {
        global $existing_dn, $existing_dn_changes;
        
        $msg = $this->ldap->modify($existing_dn,
                                   array('delete' => $existing_dn_changes['add']));
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        
        $entry = &$this->ldap->getEntry($existing_dn);
        foreach($existing_dn_changes['add'] as $attr => $vals) {
            $values = $entry->getValue($attr, 'all');
            foreach ($vals as $val) {
                $this->assertFalse(in_array($val, $values),
                                  "$attr value: $val in attribute values");
            }
        }
        return true;
    }
    
    function testModifyReplace()
    {
        global $existing_dn, $existing_dn_changes;
        
        $original = &$this->ldap->getEntry($existing_dn);
        
        $msg = $this->ldap->modify($existing_dn,
                                   array('replace' => $existing_dn_changes['replace']));
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
                
        $entry = &$this->ldap->getEntry($existing_dn);
        
        foreach ($existing_dn_changes['replace'] as $attr => $vals) {
            $values = $entry->getValue($attr, 'all');
            $this->assertTrue(count($vals) == count($values));
            foreach ($vals as $val) {
                $this->assertTrue(in_array($val, $values));
            }
            $entry->replace(array($attr => $original->getValue($attr, 'all')));
        }
        
        $msg = $entry->update($this->ldap);
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        return true;
    }
    
    function testModifyChanges()
    {
        global $existing_dn, $existing_dn_changes;
        
        $original = &$this->ldap->getEntry($existing_dn);
        
        $msg = $this->ldap->modify($existing_dn, array('changes' =>
                            array('add' => $existing_dn_changes['add'],
                                  'replace' => $existing_dn_changes['replace'])));
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        $entry = &$this->ldap->getEntry($existing_dn);

        foreach($existing_dn_changes['add'] as $attr => $vals) {
            $values = $entry->getValue($attr, 'all');
            foreach ($vals as $val) {
                $this->assertTrue(in_array($val, $values),
                                  "$attr value: $val not in attribute values");
            }
            $entry->replace(array($attr => $original->getValue($attr, 'all')));
        }
        
        foreach ($existing_dn_changes['replace'] as $attr => $vals) {
            $values = $entry->getValue($attr, 'all');
            $this->assertTrue(count($vals) == count($values));
            foreach ($vals as $val) {
                $this->assertTrue(in_array($val, $values));
            }
            $entry->replace(array($attr => $original->getValue($attr, 'all')));
        }
        
        $msg = $entry->update($this->ldap);
        if (Net_LDAP::isError($msg)) {
            $this->fail($msg->getMessage());
            return false;
        }
        return true;
    }
}

?>