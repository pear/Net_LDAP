<?php
// Call Net_LDAP_FilterTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "Net_LDAP_FilterTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'Net/LDAP/Filter.php';

/**
 * Test class for Net_LDAP_Filter.
 * Generated by PHPUnit_Util_Skeleton on 2007-10-09 at 10:34:23.
 */
class Net_LDAP_FilterTest extends PHPUnit_Framework_TestCase {
    /**
    * @var string   default filter string to test with
    */
    var $filter_str = '(&(cn=foo)(ou=bar))';

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main() {
        require_once "PHPUnit/TextUI/TestRunner.php";

        $suite  = new PHPUnit_Framework_TestSuite("Net_LDAP_FilterTest");
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp() {
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown() {
    }

    /**
     * This tests the perl compatible creation of filters through parsing of an filter string
     */
    public function testCreatePerlCompatible() {
        $filter_o = new Net_LDAP_Filter($this->filter_str);

        $this->assertType('Net_LDAP_Filter', $filter_o);
        $this->assertEquals($this->filter_str, $filter_o->asString());
    }

    /**
     * Test correct parsing of filter strings through parse()
     *
     * @todo Currently, parsing is not fully implemented, so we just test that the filter string is inserted correctly
     */
    public function testParse() {
       $parsed = Net_LDAP_Filter::parse($this->filter_str);

       $this->assertType('Net_LDAP_Filter', $parsed);
       $this->assertEquals($this->filter_str, $parsed->asString());

       $this->markTestIncomplete("Not fully implemented, because the parse() method isn't itself. Only the current behavior (\$filter_str == \$parsed->asString()) was tested.");
    }


    /**
     * This tests the basic create() method of creating filters
     */
    public function testCreate() {
        // Test values and an array containing the filter
        // creating methods and an regex to test the resulting filter
        $testattr = 'testattr';
        $testval  = 'testval';
        $combinations = array(
            'equals'         => "/\($testattr=$testval\)/",
            'begins'         => "/\($testattr=$testval\*\)/",
            'ends'           => "/\($testattr=\*$testval\)/",
            'contains'       => "/\($testattr=\*$testval\*\)/",
            'greater'        => "/\($testattr>$testval\)/",
            'less'           => "/\($testattr<$testval\)/",
            'greaterorequal' => "/\($testattr>=$testval\)/",
            'lessorequal'    => "/\($testattr<=$testval\)/",
            'approx'         => "/\($testattr=~$testval\)/",
            'any'            => "/\($testattr=\*\)/"
        );

        foreach ($combinations as $match => $regex) {
            // escaping is tested in util class
            $filter = Net_LDAP_Filter::create($testattr, $match, $testval, false);

            $this->assertType('Net_LDAP_Filter', $filter);
            $this->assertRegExp($regex, $filter->asString(), "Filter generation failed for MatchType: $match");
        }
    }

    /**
     * Tests, if _isLeaf() works
     */
    public function test_isLeaf() {
        $leaf   = Net_LDAP_Filter::create('foo', 'equals', 'bar');
        $noleaf = Net_LDAP_Filter::combine('not', $leaf);
        $this->assertType('Net_LDAP_Filter', $leaf);
        $this->assertType('Net_LDAP_Filter', $noleaf);
        $this->assertTrue($leaf->_isLeaf());
        $this->assertFalse($noleaf->_isLeaf());
    }

    /**
     * Tests, if asString() works
     */
    public function testAsString() {
        $filter = Net_LDAP_Filter::create('foo', 'equals', 'bar');
        $this->assertType('Net_LDAP_Filter', $filter);
        $this->assertEquals('(foo=bar)', $filter->asString());
    }

    /**
     * This tests the basic cobination of filters
     */
    public function testCombine() {
        $filter0 = Net_LDAP_Filter::create('foo', 'equals', 'bar');
        $filter1 = Net_LDAP_Filter::create('bar', 'equals', 'foo');
        $filter2 = Net_LDAP_Filter::create('you', 'equals', 'me');
        $filter3 = new Net_LDAP_Filter('(perlinterface=used)');

        $this->assertType('Net_LDAP_Filter', $filter0);
        $this->assertType('Net_LDAP_Filter', $filter1);
        $this->assertType('Net_LDAP_Filter', $filter2);

        // Negation test
        $filter_not1 = Net_LDAP_Filter::combine('not', $filter0);
        $filter_not2 = Net_LDAP_Filter::combine('!', $filter0);
        $this->assertType('Net_LDAP_Filter', $filter_not1, 'Negation failed for literal NOT');
        $this->assertType('Net_LDAP_Filter', $filter_not2, 'Negation failed for logical NOT');
        $this->assertEquals('(!(foo=bar))', $filter_not1->asString());
        $this->assertEquals('(!(foo=bar))', $filter_not2->asString());

        // Combination test: OR
        $filter_comb_or1 = Net_LDAP_Filter::combine('or', array($filter1, $filter2));
        $filter_comb_or2 = Net_LDAP_Filter::combine('|', array($filter1, $filter2));
        $this->assertType('Net_LDAP_Filter', $filter_comb_or1, 'Combination failed for literal OR');
        $this->assertType('Net_LDAP_Filter', $filter_comb_or2, 'combination failed for logical OR');
        $this->assertEquals('(|(bar=foo)(you=me))', $filter_comb_or1->asString());
        $this->assertEquals('(|(bar=foo)(you=me))', $filter_comb_or2->asString());

        // Combination test: AND
        $filter_comb_and1 = Net_LDAP_Filter::combine('and', array($filter1, $filter2));
        $filter_comb_and2 = Net_LDAP_Filter::combine('&', array($filter1, $filter2));
        $this->assertType('Net_LDAP_Filter', $filter_comb_and1, 'Combination failed for literal AND');
        $this->assertType('Net_LDAP_Filter', $filter_comb_and2, 'combination failed for logical AND');
        $this->assertEquals('(&(bar=foo)(you=me))', $filter_comb_and1->asString());
        $this->assertEquals('(&(bar=foo)(you=me))', $filter_comb_and2->asString());

        // Combination test: using filter created with perl interface
        $filter_comb_perl1 = Net_LDAP_Filter::combine('and', array($filter1, $filter3));
        $filter_comb_perl2 = Net_LDAP_Filter::combine('&', array($filter1, $filter3));
        $this->assertType('Net_LDAP_Filter', $filter_comb_perl1, 'Combination failed for literal AND');
        $this->assertType('Net_LDAP_Filter', $filter_comb_perl2, 'combination failed for logical AND');
        $this->assertEquals('(&(bar=foo)(perlinterface=used))', $filter_comb_perl1->asString());
        $this->assertEquals('(&(bar=foo)(perlinterface=used))', $filter_comb_perl2->asString());

        // Combination test: deep combination
        $filter_comp_deep = Net_LDAP_Filter::combine('and',array($filter2, $filter_not1, $filter_comb_or1, $filter_comb_perl1));
        $this->assertType('Net_LDAP_Filter', $filter_comp_deep, 'Deep combination failed!');
        $this->assertEquals('(&(you=me)(!(foo=bar))(|(bar=foo)(you=me))(&(bar=foo)(perlinterface=used)))', $filter_comp_deep->AsString());
    }
}

// Call Net_LDAP_FilterTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "Net_LDAP_FilterTest::main") {
    Net_LDAP_FilterTest::main();
}
?>