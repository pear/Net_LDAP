<?php
// Call Net_LDAP_LDIFTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "Net_LDAP_LDIFTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'Net/LDAP/LDIF.php';

/**
 * Test class for Net_LDAP_LDIF.
 * Generated by PHPUnit_Util_Skeleton on 2007-12-20 at 10:11:52.
 */
class Net_LDAP_LDIFTest extends PHPUnit_Framework_TestCase {
    /**
    * Default config for tests.
    *
    * The config is bound to the ldif test file
    * tests/ldif_data/unsorted_w50.ldif
    * so don't change or tests will fail
    *
    * @var array
    */
    var $defaultConfig = array(
        'onerror' => 'undef',
        'encode'  => 'base64',
        'wrap'    => 50,
        'change'  => 0,
        'sort'    => 0,
    );

    /**
    * Test entries data
    *
    * Please do not just modify these values, they
    * are closely related to the LDIF test data.
    *
    * @var string
    */
    var $testentries_data = array(
        'cn=test1,ou=example,dc=cno' => array(
            'cn'    => 'test1',
            'attr3' => array('foo', 'bar'),
            'attr1' => 12345,
            'attr4' => 'brrrzztt',
            'objectclass' => 'oc1',
            'attr2' => array('1234', 'baz')),

        'cn=test blabla,ou=example,dc=cno' => array(
            'cn'    => 'test blabla',
            'attr3' => array('foo', 'bar'),
            'attr1' => 12345,
            'attr4' => 'blabla���',
            'objectclass' => 'oc2',
            'attr2' => array('1234', 'baz'),
            'verylong' => 'fhu08rhvt7b478vt5hv78h45nfgt45h78t34hhhhhhhhhv5bg8h6ttttttttt3489t57nhvgh4788trhg8999vnhtgthgui65hgb5789thvngwr789cghm738'),

        'cn=test ���,ou=example,dc=cno' => array(
            'cn'    => 'test ���',
            'attr3' => array('foo', 'bar'),
            'attr1' => 12345,
            'attr4' => 'blabla���',
            'objectclass' => 'oc3',
            'attr2' => array('1234', 'baz'),
            'attr5' => 'endspace ',
            'attr6' => ':badinitchar'),

        ':cn=endspace,dc=cno ' => array(
            'cn'    => 'endspace')
    );

    /**
    * Test file written to
    *
    * @var string
    */
    var $outfile = 'test.out.ldif';

    /**
    * Test entries
    *
    * They will be created in main()
    *
    * @var array
    */
    var $testentries = array();

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main() {
        require_once "PHPUnit/TextUI/TestRunner.php";

        $suite  = new PHPUnit_Framework_TestSuite("Net_LDAP_LDIFTest");
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Open some outfile and ensure correct rights
     *
     * @access protected
     */
    protected function setUp() {
        // initialize test entries
        $this->testentries = array();
        foreach ($this->testentries_data as $dn => $attrs) {
            $entry = Net_LDAP_Entry::createfresh($dn, $attrs);
            $this->assertType('Net_LDAP_Entry', $entry, 'ERROR inittializing test entries');
            array_push($this->testentries, $entry);
        }

        // create outfile if not exists and enforce proper access rights
        if (!file_exists($this->outfile)) {
            if (!touch($this->outfile)) {
                $this->markTestSkipped('Unable to create '.$this->outfile.', skipping test');
            }
        }
        if (!chmod($this->outfile, 0644)) {
            $this->markTestSkipped('Unable to chmod(0644) '.$this->outfile.', skipping test');
        }
    }

    /**
     * Remove the outfile
     *
     * @access protected
     */
    protected function tearDown() {
       @unlink($this->outfile);
    }

    /**
     * Construction tests
     *
     * Construct LDIF object and see if we can get a handle
     */
    public function testConstruction() {
        $supported_modes = array('r', 'w', 'a');
        $plus            = array('', '+');

        // Test all open modes,
        // all of them should return a correct handle
        foreach ($supported_modes as $mode) {
            foreach ($plus as $p) {
                $ldif = new Net_LDAP_LDIF($this->outfile, $mode, $this->defaultConfig);
                $this->assertTrue(is_resource($ldif->handle()));
            }
        }

        // Test illegal option passing
        $ldif = new Net_LDAP_LDIF($this->outfile, $mode, array('somebad' => 'option'));
        $this->assertType('Net_LDAP_Error', $ldif->error());

        // Test passing custom handle
        $handle = fopen($this->outfile, 'r');
        $ldif = new Net_LDAP_LDIF($handle, $mode, $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        // Reading test with invalid file mode
        $ldif = new Net_LDAP_LDIF($this->outfile, 'y', $this->defaultConfig);
        $this->assertNull($ldif->handle());
        $this->assertType('Net_LDAP_Error', $ldif->error());

        // Reading test with nonexistent file
        $ldif = new Net_LDAP_LDIF('some/nonexistent/file_for_net_ldap_ldif', 'r', $this->defaultConfig);
        $this->assertNull($ldif->handle());
        $this->assertType('Net_LDAP_Error', $ldif->error());

        // writing to nonexistent file
        $ldif = new Net_LDAP_LDIF('testfile_for_net_ldap_ldif', 'w', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));
        @unlink('testfile_for_net_ldap_ldif');

        // writing to nonexistent path
        $ldif = new Net_LDAP_LDIF('some/nonexistent/file_for_net_ldap_ldif', 'w', $this->defaultConfig);
        $this->assertNull($ldif->handle());
        $this->assertType('Net_LDAP_Error', $ldif->error());

        // writing to existing file but without permission
        // note: chmod should succeed since we do that in setUp()
        if (chmod($this->outfile, 0444)) {
            $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $this->defaultConfig);
            $this->assertNull($ldif->handle());
            $this->assertType('Net_LDAP_Error', $ldif->error());
        } else {
            $this->markTestSkipped("Could not chmod ".$this->outfile.", write test without permission skipped");
        }
    }

    /**
     * Tests if entries from an LDIF file are correctly constructed
     */
    public function testRead_entry() {
        /*
        * UNIX line endings
        */
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        $entries = array();
        do {
            $entry = $ldif->read_entry();
            $this->assertFalse((boolean)$ldif->error(), 'failed building entry from LDIF: '.$ldif->error(true));
            $this->assertType('Net_LDAP_Entry', $entry);
            array_push($entries, $entry);
        } while (!$ldif->eof());

        $this->compareEntries($this->testentries, $entries);

        /*
        * Windows line endings
        */
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50_WIN.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        $entries = array();
        do {
            $entry = $ldif->read_entry();
            $this->assertFalse((boolean)$ldif->error(), 'failed building entry from LDIF: '.$ldif->error(true));
            $this->assertType('Net_LDAP_Entry', $entry);
            array_push($entries, $entry);
        } while (!$ldif->eof());

        $this->compareEntries($this->testentries, $entries);
    }

    /**
     * Tests if entries are correctly written
     *
     * This tests converting entries to LDIF lines, wrapping, encoding, etc
     */
    public function testWrite_entry() {
        $testconf = $this->defaultConfig;

        /*
        * test wrapped operation
        */
        $testconf['wrap'] = 50;
        $testconf['sort'] = 0;
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);

        // Write LDIF
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($this->testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        // Compare files
        $this->assertEquals($expected, file($this->outfile));


        $testconf['wrap'] = 30;
        $testconf['sort'] = 0;
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/unsorted_w30.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);

        // Write LDIF
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($this->testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        // Compare files
        $this->assertEquals($expected, file($this->outfile));



        /*
        * Test unwrapped operation
        */
        $testconf['wrap'] = 40;
        $testconf['sort'] = 1;
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/sorted_w40.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);

        // Write LDIF
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($this->testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        // Compare files
        $this->assertEquals($expected, file($this->outfile));


        $testconf['wrap'] = 50;
        $testconf['sort'] = 1;
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/sorted_w50.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);

        // Write LDIF
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($this->testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        // Compare files
        $this->assertEquals($expected, file($this->outfile));


        /*
        * Test raw option
        */
        $testconf['wrap'] = 50;
        $testconf['sort'] = 1;
        $testconf['raw']  = '/attr6/';
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/sorted_w50.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);

        // Write LDIF
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($this->testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        // Compare files, with expected attr adjusted
        $expected[33] = preg_replace('/attr6:: OmJhZGluaXRjaGFy/', 'attr6: :badinitchar', $expected[33]);
        $this->assertEquals($expected, file($this->outfile));


        /*
        * Test writing with non entry as parameter
        */
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w');
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry('malformed_parameter');
        $this->assertTrue((boolean)$ldif->error());
    }

    /**
     * Round trip test: Read LDIF, parse to entries, write that to LDIF and compare both files
     */
    public function testReadWriteRead() {
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        // Read LDIF
        $entries = array();
        do {
            $entry = $ldif->read_entry();
            $this->assertFalse((boolean)$ldif->error(), 'failed building entry from LDIF: '.$ldif->error(true));
            $this->assertType('Net_LDAP_Entry', $entry);
            array_push($entries, $entry);
        } while (!$ldif->eof());
        $ldif->done();

         // Write LDIF
         $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $this->defaultConfig);
         $this->assertTrue(is_resource($ldif->handle()));
         $ldif->write_entry($entries);
         $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
         $ldif->done();

         // Compare files
         $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif'));
         // strip 4 starting lines because of comments in the file header:
         array_shift($expected);array_shift($expected);
         array_shift($expected);array_shift($expected);
         $this->assertEquals($expected, file($this->outfile));
    }

    /**
     * Tests if entriy changes are correctly written
     */
    public function testWrite_entryChanges() {
        $testentries = $this->testentries;
        $testentries[] = Net_LDAP_Entry::createFresh('cn=foo,ou=example,dc=cno', array('cn' => 'foo'));
        $testentries[] = Net_LDAP_Entry::createFresh('cn=footest,ou=example,dc=cno', array('cn' => 'foo'));

        $testconf = $this->defaultConfig;
        $testconf['change'] = 1;

        /*
        * no changes should produce empty file
        */
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();
        $this->assertEquals(array(), file($this->outfile));

        /*
        * changes test
        */
        //prepare some changes
        $testentries[0]->delete('attr1'); // del whole attr
        $testentries[0]->delete(array('attr2' => 'baz')); // del spec. value
        $testentries[0]->delete(array('attr4', 'attr3' => 'bar')); // del mixed

        // prepare some replaces and adds
        $testentries[2]->replace(array('attr1' => 'newvaluefor1'));
        $testentries[2]->replace(array('attr2' => array('newvalue1for2', 'newvalue2for2')));
        $testentries[2]->replace(array('attr3' => ''));      // will result in delete
        $testentries[2]->replace(array('newattr' => 'foo')); // will result in add

        // delete whole entry
        $testentries[3]->delete();

        // rename and move
        $testentries[4]->dn('cn=Bar,ou=example,dc=cno');
        $testentries[5]->dn('cn=foobartest,ou=newexample,dc=cno');

        // carry out write
        $ldif = new Net_LDAP_LDIF($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->write_entry($testentries);
        $this->assertFalse((boolean)$ldif->error(), 'Failed writing entry to '.$this->outfile.': '.$ldif->error(true));
        $ldif->done();

        //compare results
        $expected = array_map('conv_lineend', file(dirname(__FILE__).'/ldif_data/changes.ldif'));
        // strip 4 starting lines because of comments in the file header:
        array_shift($expected);array_shift($expected);
        array_shift($expected);array_shift($expected);
        $this->assertEquals($expected, file($this->outfile));
    }

    /**
    * Tests if syntax errors are detected
    *
    * The used LDIF files have several damaged entries but always one
    * correct too, to test if Net_LDAP_LDIF is continue reading as it should
    * Each Entry must have 2 correct attributes.
    */
    public function testSyntaxerrors() {
        // Test malformed encoding
        // I think we can ignore this test, because if the LDIF is not encoded properly, we
        // might be able to successfully fetch the entries data. However, it is possible
        // that it will be corrupted, but thats not our fault then.
        // If we should catch that error, we must adjust Net_LDAP_LDIF::next_lines().
        /*
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/malformed_encoding.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());
        $entries = array();
        do {
            $entry = $ldif->read_entry();
            if ($entry) {
                // the correct attributes need to be parsed
                $this->assertThat(count(array_keys($entry->getValues())), $this->equalTo(2));
                $entries[] = $entry;
            }
        } while (!$ldif->eof());
        $this->assertTrue((boolean)$ldif->error());
        $this->assertThat($ldif->error_lines(), $this->greaterThan(1));
        $this->assertThat(count($entries), $this->equalTo(1));
        */

        // Test malformed syntax
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/malformed_syntax.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());
        $entries = array();
        do {
            $entry = $ldif->read_entry();
            if ($entry) {
                // the correct attributes need to be parsed
                $this->assertThat(count(array_keys($entry->getValues())), $this->equalTo(2));
                $entries[] = $entry;
            }
        } while (!$ldif->eof());
        $this->assertTrue((boolean)$ldif->error());
        $this->assertThat($ldif->error_lines(), $this->greaterThan(1));
        $this->assertThat(count($entries), $this->equalTo(2));

        // test bad wrapping
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/malformed_wrapping.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());
        $entries = array();
        do {
           $entry = $ldif->read_entry();
            if ($entry) {
                // the correct attributes need to be parsed
                $this->assertThat(count(array_keys($entry->getValues())), $this->equalTo(2));
                $entries[] = $entry;
            }
        } while (!$ldif->eof());
        $this->assertTrue((boolean)$ldif->error());
        $this->assertThat($ldif->error_lines(), $this->greaterThan(1));
        $this->assertThat(count($entries), $this->equalTo(2));
    }

    /**
     * Test error dropping functionality
     */
    public function testError() {
        // NO error:
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());

        // Error giving error msg and line number:
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/some_not_existing/path/for/net_ldap_ldif', 'r', $this->defaultConfig);
        $this->assertTrue((boolean)$ldif->error());
        $this->assertType('Net_LDAP_Error', $ldif->error());
        $this->assertType('string', $ldif->error(true));
        $this->assertType('int', $ldif->error_lines());
        $this->assertThat(strlen($ldif->error(true)), $this->greaterThan(0));

        // Test for line number reporting
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/malformed_syntax.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());
        do { $entry = $ldif->read_entry(); } while (!$ldif->eof());
        $this->assertTrue((boolean)$ldif->error());
        $this->assertThat($ldif->error_lines(), $this->greaterThan(1));
    }

    /**
     * Tests current_lines() and next_lines().
     *
     * This should always return the same lines unless forced
     */
    public function testLineMethods() {
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());
        $this->assertEquals(array(), $ldif->current_lines(), 'Net_LDAP_LDIF initialization error!');

        // read first lines
        $lines = $ldif->next_lines();
        $this->assertFalse((boolean)$ldif->error(), 'unable to read first lines');

        // read the first lines several times and test
        for ($i = 0; $i <= 10; $i++) {
            $r_lines = $ldif->next_lines();
            $this->assertFalse((boolean)$ldif->error());
            $this->assertEquals($lines, $r_lines);
        }

        // now force to iterate and see if the content changes
        $r_lines = $ldif->next_lines(true);
        $this->assertFalse((boolean)$ldif->error());
        $this->assertNotEquals($lines, $r_lines);

        // it could be confusing to some people, but calling
        // current_entry would not work now, like the description
        // of the method says.
        $no_entry = $ldif->current_lines();
        $this->assertEquals(array(), $no_entry);
    }

    /**
     * Tests current_entry(). This should always return the same object
     */
    public function testCurrent_entry() {
        $ldif = new Net_LDAP_LDIF(dirname(__FILE__).'/ldif_data/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertFalse((boolean)$ldif->error());

        // read first entry
        $entry = $ldif->read_entry();
        $this->assertFalse((boolean)$ldif->error(), 'First entry failed: '.$ldif->error(true));

        // test if current_Entry remains the first one
        for ($i = 0; $i <= 10; $i++) {
            $e = $ldif->current_entry();
            $this->assertFalse((boolean)$ldif->error(), $ldif->error(true));
            $this->assertEquals($entry, $e);
        }
    }





    /**
    * Compare Net_LDAP_Entries
    *
    * This helper function compares two entries (or array of entries)
    * and tells if they are equal. They are equal if all DNs from
    * the first crowd exist in the second AND each attribute is present
    * and equal at the respicitve entry.
    * The search is case sensitive.
    *
    * @param array|Net_LDAP_Entry $entry1
    * @param array|Net_LDAP_Entry $entry2
    * @return true|false
    */
    function compareEntries($entry1, $entry2) {
        if (!is_array($entry1)) $entry1 = array($entry1);
        if (!is_array($entry2)) $entry2 = array($entry2);

        $entries_data1  = array();
        $entries_data2  = array();

        // step 1: extract and sort data
        foreach ($entry1 as $e) {
            $values = $e->getValues();
            foreach ($values as $attr_name => $attr_values) {
                if (!is_array($attr_values)) $attr_values = array($attr_values);
                $values[$attr_name] = $attr_values;
            }
            $entries_data1[$e->dn()] = $values;
        }
        foreach ($entry2 as $e) {
            $values = $e->getValues();
            foreach ($values as $attr_name => $attr_values) {
                if (!is_array($attr_values)) $attr_values = array($attr_values);
                $values[$attr_name] = $attr_values;
            }
            $entries_data2[$e->dn()] = $values;
        }

        // step 2: compare DNs (entries)
        $this->assertEquals(array_keys($entries_data1), array_keys($entries_data2), 'Entries DNs not equal! (missing entry or wrong DN)');

        // step 3: look for attribute existence and compare values
        foreach ($entries_data1 as $dn => $attributes) {
            $this->assertEquals($entries_data1[$dn], $entries_data2[$dn], 'Entries '.$dn.' attributes are not equal');
            foreach ($attributes as $attr_name => $attr_values) {
                $this->assertEquals(0, count(array_diff($entries_data1[$dn][$attr_name], $entries_data2[$dn][$attr_name])), 'Entries '.$dn.' attribute '.$attr_name.' values are not equal');
            }
        }

        return true;
    }

}

// Call Net_LDAP_LDIFTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "Net_LDAP_LDIFTest::main") {
    Net_LDAP_LDIFTest::main();
}

/**
* Function transfers line endings to current OS
*
* This is neccessary to make write tests platform indendent.
*
* @param string $line Line
* @return string
*/
function conv_lineend($line) {
    return rtrim($line).PHP_EOL;
}
?>