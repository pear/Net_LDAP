<?php

/*
 * LDAP test suite. Run this to se if everything is fine!
 *
 *
 * */
require 'LDAP.php';

$param['base'] = 'o=nu,c=no';
$param['dn'] = 'cn=manager,o=nu,c=no';
$param['password'] = 'secret';
$param['tls'] = false;
$param['host'] = '127.0.0.1';
// the test searchfilter
$param['filter'] = '(uid=*)';

// the uid of the new entry to create:
$param['uid'] = 'tarjei';

// what test to do. Change the different values to try different tests.
// 
$tests = array ('connect' => true,
                'search' => true,
                'modify' => true,
                'new' => false,
                'delete' => false,
                'changetype' => false);

// the new entry to be added. Note: if this works also depends on what schemas you use. I tried to keep this generic!
//
$param['newentry'] = array ('objectclass'=>array('top','person'),
                            'cn' => 'kaja',
                            'sn' => 'nordby',
                            'userPassword' => '{plain}kaja',
                            'description' => 'a very cute girl'
                            );
$param['newentrydn'] = 'cn=' . $param['newentry']['cn'] . ',' . $param['base'];

print "<br><pre><code>";
if ($tests['connect']) {
    print "<b>Testing if we connect to the server</b>";
    $ldap = Net_Ldap::connect($param);

    if (Net_Ldap::isError($ldap)) { 
   
        print "<br>Test did not succed, reason: <br>" . $ldap->getMessage() . "<br>";
   
    } else {
        print "<br>Successfully connected to server <b>[connection ok]</b>";
    }
}

if ($tests['search'] ) { 
        print "<br><br><b>Trying to do a search with filter " . $param['filter'];
        // MSG will either be an ldap_error object or a ldap_search object!
        $msg = $ldap->search(null,$param['filter']);

        if (Net_Ldap::isError($msg)) {
            print "<br>Search did not succsed: <br>". $msg->getMessage();
        } else {

            print "<br>Search seems to have sucseded nr. of objects found: " . $msg->count();
        }

}

if ($tests['modify']) {
   print "<br><br>Testing if we can get one of the entries:";
   print "<br><b>This test needs the search test to work!!!</b><br>";
   // shift_entry gets the first entry from the search result
   $entry = $msg->shift_entry();

   if (Net_Ldap::isError($msg)) {
    print "<br>Shift entry did not work: " . $msg->getError();
   } else {
    print "<br>Testing entry!";
    print "<br> Uid: " . $entry->get_value('uid','single');
    print "<br> mail: "  . $entry->get_value('ispmanSysadminMail', 'single');

    print "<br>trying to change entry:<br>";

    $entry->replace(array( 'ispmanSysadminMail' => array( 'tarjei@nu.no')));

    
    $msg = $entry->update();

    if (Net_Ldap::isError($msg)) { print $msg->getMessage();} else { 
       print "<br>Entry successfully modified" ; }
    
    }
}

if ($tests['new']) {
   
    print "<br> Trying to create a new entry:";
    $entry = $msg->shift_entry(); 
    // the atributes function returns another entries attributes in the same array format as is neded by the new function. 
    // I then modify the entrys uid and dn before adding it.
    $at = $entry->attributes();
    $at['uid'] = 'tarjei';
    // however, I'm goint to use the array defined at the top instead:
    unset ($at);

    $at = $param['newentry'];
    // note:: There is no link to the ldap-server in this object
    $newentry = Net_LDAP_Entry::createFresh($param['newentrydn'], $at);
    
    print "<br> New entry dn: " . $newentry->dn();
    
    // by adding the entry, it gets the LDAP object internally set
    $msg = $ldap->add($newentry);
    
    if (Net_Ldap::isError($msg)) {
       print $msg->getMessage();
    } else {
     print "<br>Entry added!";
    }

}

// now let's test deleting
if ($tests['delete']) {
   print "<br>Trying to delete the object with cn=" .  $param['newentrydn']['cn'];
    // finding an entry is not always easy!
   $msg = $ldap->search(null,'(cn=' .  $param['newentrydn']['cn'] . ')');

   if (Net_Ldap::isError($msg)) {
    print "<br>Entry to be deleted not found";
   }
   print "<br>Found " .$msg->count() . " entries";
   
   if ($msg->count() > 0 ) {
    print " Deleting the first one";
   

    $entry = $msg->shift_entry();
    if (Net_Ldap::isError($entry)) {
         print "<br>Entry not retrieved: ". $entry->getMessage();
    } else {
       // you may also use the dn instead of a complete entry object!

     $msg = $ldap->delete($entry);
    if (Net_Ldap::isError($msg) ) {
        print "<br>Entry not deleted " . $msg->getMessage();
    }
   }
   }
}

if ($tests['changetype']) {
    // the changes in the $ldap->modify method gives quite som options :)
    $msg = $ldap->modify($param['newentrydn'],array('changes'=>array('add'=>array('description'=>'23232323'), 'delete'=>array('telephonenumber')))); 
   if (Net_Ldap::isError($msg)) {
    print "<br> Changes doesn't work: " . $msg->getMessage();
   }
}

// close ldap connecton:
//
$ldap->done();

?></code></pre>
