<?php

require_once('Net/LDAP.php');
require_once('PHPUnit.php');
require_once('PHPUnit/GUI/HTML.php');

set_time_limit(0);

require_once('Net_LDAP.php');
require_once('config.php');

$suite = new PHPUnit_TestSuite('Net_LDAP_Test');
$gui = new PHPUnit_GUI_HTML($suite);
$gui->show();

?>