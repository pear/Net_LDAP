This is my own implementation of some classes for manipulation LDAP-entries in
a directory. 

The classes methods and structure are based on Perls Net::LDAP
(see perl-ldap.sf.net). The test.php file shuld provide you with enough
examples to do the most basic things. 

The largest difference between the perl implementation and this one (apart
from the fact that all array/list structures are different due to differences
in the two languages) is that instead of the method new you'll have to use the
method connect() instead.

Patches and comments are most welcome! 

Please send them to tarjei - at - nu.no.

Tarjei
