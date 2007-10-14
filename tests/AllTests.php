<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Net_LDAP_AllTests::main');
}

// PHPUnit inlcudes
require_once 'PHPUnit/Framework/TestSuite.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

// Net_LDAP test suites includes
chdir(dirname(__FILE__) . '/../');
require_once 'Net_LDAP_FilterTest.php';
require_once 'Net_LDAP_UtilTest.php';
require_once 'Net_LDAPTest.php';
require_once 'Net_LDAP_EntryTest.php';
require_once 'Net_LDAP_RootDSETest.php';
require_once 'Net_LDAP_SearchTest.php';

class Net_LDAP_AllTests
{
    public static function main()
    {

        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Net_LDAP Tests');

       // LDAP independent tests
       $suite->addTestSuite('Net_LDAP_FilterTest');
       $suite->addTestSuite('Net_LDAP_UtilTest');

       // LDAP dependent tests (require a LDAP server)
       $suite->addTestSuite('Net_LDAPTest');
       $suite->addTestSuite('Net_LDAP_SearchTest');
       $suite->addTestSuite('Net_LDAP_EntryTest');
       $suite->addTestSuite('Net_LDAP_RootDSETest');

        return $suite;
    }
}


// exec test suite
if (PHPUnit_MAIN_METHOD == 'Net_LDAP_AllTests::main') {
    Net_LDAP_AllTests::main();
}
?>
