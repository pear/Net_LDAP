<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +--------------------------------------------------------------------------+
// | Net_LDAP                                                                 |
// +--------------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                    |
// +--------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU Lesser General Public               |
// | License as published by the Free Software Foundation; either             |
// | version 2.1 of the License, or (at your option) any later version.       |
// |                                                                          |
// | This library is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU        |
// | Lesser General Public License for more details.                          |
// |                                                                          |
// | You should have received a copy of the GNU Lesser General Public         |
// | License along with this library; if not, write to the Free Software      |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA |
// +--------------------------------------------------------------------------+
// | Authors: Tarjej Huse                                                     |
// |          Jan Wagner                                                      |
// +--------------------------------------------------------------------------+
//
// $Id$

require_once('PEAR.php');
require_once('LDAP/Entry.php');
require_once('LDAP/Search.php');

/**
 *  Error constants for errors that are not LDAP errors 
 */
define ('NET_LDAP_ERROR', 1000);

/**
 * Net_LDAP - manipulate LDAP servers the right way!
 *
 * @author Tarjei Huse
 * @author Jan Wagner
 * @version $Revision$
 * @package Net_LDAP
 */
 class Net_LDAP extends PEAR
{
    /**
     * Net_LDAP Release Version
     *
     * @access private
     * @var string
     */
    var $_version = "0.6.99";
    
    /**
     * Class configuration array
     *
     * host     = the ldap host to connect to
     * port     = the server port
     * version  = ldap version (defaults to v 3)
     * starttls = when set, ldap_start_tls() is run after connecting.
     * bindpw   = no explanation needed
     * binddn   = the DN to bind as.
     * basedn   = ldap base 
     * options  = hash of ldap options to set (opt => val) 
     * filter   = default search filter
     * scope    = default search scope
     *
     * @access private
     * @var array
     */
     var $_config = array('host'     => 'localhost',
                          'port'     => 389,
                          'version'  => 3,
                          'starttls' => false,                          
                          'binddn'   => '',                          
                          'bindpw'   => '',                          
                          'basedn'   => '',
                          'options'  => array(),
                          'filter'   => '(objectClass=*)',
                          'scope'    => 'sub');

    /**
     * LDAP resource link.
     *
     * @access private
     * @var resource
     */
    var $_link = false;

    /**
     * Net_LDAP_Schema object
     *
     * @access private
     * @var object Net_LDAP_Schema
     */
    var $_schema = null;
    
    /**
     * Cache for attribute encoding checks
     *
     * @access private
     * @var array Hash with attribute names as key and boolean value
     *            to determine whether they should be utf8 encoded or not.
     */
    var $_schemaAttrs = array();

    /**
     * Creates the initial ldap-object
     *
     * Static function that returns either an error object or the new Net_LDAP
     * object. Something like a factory. Takes a config array with the needed
     * parameters. 
     *
     * @access public
     * @param array Configuration array
     * @return mixed object Net_LDAP_Error or Net_LDAP
     */
    function &connect($config = array())
    {
        @$obj = & new Net_LDAP($config);
        
        $err  = $obj->bind();
        if (Net_LDAP::isError($err)) {
            return $err;
        }
        
        return $obj;
    }
    
    /**
     * Net_LDAP constructor
     *
     * Sets the config array
     *
     * @access protected
     * @param array Configuration array
     * @return void
     * @see $_config
     */
    function Net_LDAP($config = array())
    {
        $this->PEAR('Net_LDAP_Error');
        
        if (!extension_loaded('ldap') && !dl('ldap')){
            return PEAR::raiseError("It seems that you do not have the ldap-extension installed. Please install it before using this package.");
        }

        $this->_setConfig($config);
    }

    /**
     * Sets the internal configuration array
     *
     * @access private
     * @param array Configuration array
     * @return void
     */
    function _setConfig($config)
    {
        if (is_array($config)) {
            foreach ($config as $k => $v) {
                if (isset($this->_config[$k])) {
                    $this->_config[$k] = $v;
                } else {
                    // map old (Net_LDAP) parms to new ones
                    switch($k) {
                        case "dn":
                            $this->_config["binddn"] = $v;
                            break;
                        case "password":
                            $this->_config["bindpw"] = $v;
                            break;
                        case "tls":
                            $this->_config["starttls"] = $v;
                            break;
                        case "base":
                            $this->_config["basedn"] = $v;
                            break;
                    }
                }
            }
        }    
    }

    /**
     * Bind to the ldap-server
     *
     * This function binds with the given dn and password to the server. In case
     * no connection has been made yet, it will be startet and startTLS issued
     * if appropiate.
     *
     * @access public
     * @param string Distinguished name for binding
     * @param string Password for binding
     * @return mixed Net_LDAP_Error or true
     */
    function bind($dn = null, $password = null)
    {
        if (Net_LDAP::isError($msg = $this->_connect())) {
            return $msg;
        } 
        if (is_null($dn)) {
            $dn = $this->_config["binddn"];
        }
        if (is_null($password)) {
            $password = $this->_config["bindpw"];
        }
        if (is_null($dn)) {
            $msg = @ldap_bind($this->_link);
        } else {
            $msg = @ldap_bind($this->_link, $dn, $password);
        }
        if (false === $msg) {
            return PEAR::raiseError("Bind failed: " .
                                    @ldap_error($this->_link),
                                    @ldap_errno($this->_link));
        }
        return true;
    }

    /**
     * Connect to the ldap-server
     *
     * This function connects to the given LDAP server.
     *
     * @access private
     * @return mixed Net_LDAP_Error or true
     */
    function _connect()
    {
        if ($this->_link === false) {            
            $this->_link = @ldap_connect($this->_config['host'],
                                         $this->_config['port']);
            if (false === $this->_link) {
                return PEAR::raiseError("Could not connect to $host:$port");
            }            
            if (Net_LDAP::isError($msg = $this->setLDAPVersion())) {
                return $msg;
            }            
            if ($this->_config["starttls"] === true) {
                if (Net_LDAP::isError($msg = $this->startTLS())) {
                    return $msg;
                }
            }
            if (isset($this->_config['options']) &&
                is_array($this->_config['options']) &&
                count($this->_config['options']))
            {
                foreach ($this->_config['options'] as $opt => $val) {
                    $err = $this->setOption($opt, $val);
                    if (Net_LDAP::isError($err)) {
                        return $err;
                    }
                }
            }            
        }
        return true; 
    }    
    
    /**
     * Starts an encrypted session
     *
     * @access public
     * @return mixed True or Net_LDAP_Error
     */
    function startTLS()
    {
        if (false === @ldap_start_tls($this->_link)) {
            return $this->raiseError("TLS not started: " .
                                     @ldap_error($this->_link),
                                     @ldap_errno($this->_link));
        }
        return true;
    }
    
    /**
     * alias function of startTLS() for perl-ldap interface
     * 
     * @see startTLS()
     */
    function start_tls() 
    {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'startTLS' ), $args);
    }

    /**
     * Close LDAP connection.
     *
     * Closes the connection. Use this when the session is over.
     *
     * @return void
     */
    function done()
    {
        $this->_Net_LDAP();
    }

    /**
     * Destructor
     *
     * @access private
     */
    function _Net_LDAP()
    {
        @ldap_close($this->_link);
    }

    /**
     * Add a new entryobject to a directory.
     *
     * Use add to add a new Net_LDAP_Entry object to the directory.
     *
     * @param object Net_LDAP_Entry
     * @return mixed Net_LDAP_Error or true
     */
    function add($entry)
    {
        if (false === is_a($entry, 'Net_LDAP_Entry')) {
            return PEAR::raiseError('Parameter to Net_LDAP::add() must be a Net_LDAP_Entry object.');
        }
        if (@ldap_add($this->_link, $entry->dn(), $entry->getValues())) {
             return true;
        } else {
             return PEAR::raiseError("Could not add entry " . $entry->dn() . " " .
                                     @ldap_error($this->_link),
                                     @ldap_errno($this->_link));
        }
    }

    /**
     * Delete an entry from the directory
     *
     * The object may either be a string representing the dn or a Net_LDAP_Entry
     * object. When the boolean paramter recursive is set, all subentries of the
     * entry will be deleted as well
     *
     * @access public
     * @param mixed string or Net_LDAP_Entry
     * @param boolean recursive
     * @return mixed Net_LDAP_Error or true  
     */
    function delete($dn, $recursive = false)
    {
        if (is_a($dn, 'Net_LDAP_Entry')) {
             $dn = $dn->dn();
        }
        if (false === is_string($dn)) {
            return PEAR::raiseError("Parameter is not a string nor an entry object!"); 
        }
        // Recursive delete searches for children and calls delete for them
        if ($recursive) {
            $result = @ldap_list($this->_link, $dn, '(objectClass=*)', array(null));
            if (@ldap_count_entries($this->_link, $result)) {
                $subentry = @ldap_first_entry($this->_link, $result);
                $this->delete(@ldap_get_dn($this->_link, $subentry));
                while ($subentry = @ldap_next_entry($this->_link, $subentry)) {
                    $this->delete(@ldap_get_dn($this->_link, $subentry));
                }
            }
        } 
        // Delete the DN
        if (false == @ldap_delete($this->_link, $dn)) {
            $error = @ldap_errno($this->_link);                
            if ($error == 66) {
                return PEAR::raiseError("Could not delete entry $dn because of subentries. Use the recursive param to delete them."); 
            } else {
                return PEAR::raiseError("Could not delete entry $dn: " .
                                         $this->errorMessage($error), $error);
            }
        }
        return true;
    }
    
    /**
     * Modify an ldapentry
     *
     * This one takes the dn or a Net_LDAP_Entry object and an array of actions.
     * This array should be something like this:
     *
     * array('add' => array('attribute1' => array('val1', 'val2'),
     *                      'attribute2' => array('val1')),
     *       'delete' => array('attribute1'),
     *       'replace' => array('attribute1' => array('val1')),
     *       'changes' => array('add' => ...,
     *                          'delete' => array('attribute1', 'attribute2'),
     *                          'delete' => array('attribute2' => array('val1')),
     *                          'replace' => ...))
     *
     * The changes array is there so the order of operations can be influenced
     * (the operations are done in order of appearance).
     * The function calls the corresponding functions of an Net_LDAP_Entry
     * object. A detailed description of array structures can be found there.
     *
     * @access public
     * @param mixed Net_LDAP_Entry object or dn (string)
     * @param array Array of changes
     * @return mixed Net_LDAP_Error or true
     */
    function modify($entry , $parms = array())
    {
        if (is_string($entry)) {
            $entry = $this->getEntry($entry);
            if (Net_LDAP::isError($entry)) {
                return $entry;
            }
        }
        if (!is_a($entry, 'Net_LDAP_Entry')) {
            return PEAR::raiseError("Parameter is not a string nor an entry object!");
        }
        
        foreach (array('add', 'delete', 'replace') as $action) {
            if (isset($parms[$action])) {
                $msg = $entry->$action($parms[$action]);
                if (Net_LDAP::isError($msg)) {
                    return $msg;
                }
                $msg = $entry->update($this);
                if (Net_LDAP::isError($msg)) {
                    return $msg;
                }
            }
        }
        
        if (isset($parms['changes'])) {
            foreach ($parms['changes'] as $action => $value) {
                $msg = $this->modify($entry->dn(), array($action => $value));
                if (Net_LDAP::isError($msg)) {
                    return $msg;
                }
            }
        }
        
        return true;
    }

    /**
     * Run a ldap query
     *
     * Search is used to query the ldap-database.
     * $base and $filter may be ommitted.The one from config will then be used.
     * Params may contain:
     *
     * scope: The scope which will be used for searching 
     *        base - Just one entry
     *        sub  - The whole tree
     *        one  - Immediately below $base
     * sizelimit: Limit the number of entries returned (default: 0),
     * timelimit: Limit the time spent for searching (default: 0),
     * attrsonly: If true, the search will only return the attribute names,
     * attributes: Array of attribute names, which the entry should contain.
     *             It is good practice to limit this to just the ones you need
     * [NOT IMPLEMENTED]
     * deref: By default aliases are dereferenced to locate the base object for the search, but not when
     *        searching subordinates of the base object. This may be changed by specifying one of the
     *        following values:
     *       
     *        never  - Do not dereference aliases in searching or in locating the base object of the search.
     *        search - Dereference aliases in subordinates of the base object in searching, but not in 
     *                locating the base object of the search. 
     *        find
     *        always
     *
     * @access public
     * @param string LDAP searchbase 
     * @param string LDAP search filter
     * @param array Array of options
     * @return object mixed Net_LDAP_Search or Net_LDAP_Error
     */
    function search($base = null, $filter = null, $params = array())
    {		
    	if (is_null($base)) {
            $base = $this->_config['basedn'];
        }
        if (is_null($filter)) {
            $filter = $this->_config['filter'];
        }        
        
        /* setting searchparameters  */
        (isset($params['sizelimit']))  ? $sizelimit  = $params['sizelimit']  : $sizelimit = 0;
        (isset($params['timelimit']))  ? $timelimit  = $params['timelimit']  : $timelimit = 0;
        (isset($params['attrsonly']))  ? $attrsonly  = $params['attrsonly']  : $attrsonly = 0;        
        (isset($params['attributes'])) ? $attributes = $params['attributes'] : $attributes = array();        
       
        if (!is_array($attributes)) {
            PEAR::raiseError("The param attributes must be an array!");
        }
       
        /* scoping makes searches faster!  */                 		
        $scope = (isset($params['scope']) ? $params['scope'] : $this->_config['scope']);
        
        switch ($scope) {
        	case 'one':
        		$search_function = 'ldap_list';
        		break;
        	case 'base':
        		$search_function = 'ldap_read';
        		break;
        	default:
        		$search_function = 'ldap_search';
        }               
                
        $search = @call_user_func($search_function, 
                                  $this->_link,
                                  $base,
                                  $filter,
                                  $attributes,
                                  $attrsonly,
                                  $sizelimit,
                                  $timelimit);
        
        if ($err = @ldap_errno($this->_link)) {             
            if ($err == 32) {
                // Errorcode 32 = no such object, i.e. a nullresult.
                return $obj = & new Net_LDAP_Search ($search, $this); 
            } elseif ($err == 4) {
                // Errorcode 4 = sizelimit exeeded. TODO
                return $obj = & new Net_LDAP_Search ($search, $this);             
            } elseif ($err == 87) {
                // bad search filter
                return $this->raiseError($this->errorMessage($err) . "($filter)", $err);
            } else {                
                $msg = "\nParameters:\nBase: $base\nFilter: $filter\nScope: $scope";
                return $this->raiseError($this->errorMessage($err) . $msg, $err);                 
            }
        } else {
            return $obj = & new Net_LDAP_Search($search, $this);
        }
    }

    /**
     * Set an LDAP option
     *
     * @access public
     * @param string Option to set
     * @param mixed Value to set Option to
     * @return mixed Net_LDAP_Error or true
     */
    function setOption($option, $value)
    {
        if ($this->_link) {
            if (defined($option)) {
                if (@ldap_set_option($this->_link, constant($option), $value)) {
                    return true;
                } else {
                    $err = @ldap_errno($this->_link);
                    if ($err) {
                        $msg = @ldap_err2str($err);                        
                    } else {
                        $err = NET_LDAP_ERROR;
                        $msg = $this->errorMessage($err);
                    } 
                    return $this->raiseError($msg, $err);
                }
            } else {
                return $this->raiseError("Unkown Option requested");
            }    
        } else {
            return $this->raiseError("No LDAP connection");
        }
    }

    /**
     * Get an LDAP option value
     *
     * @access public
     * @param string Option to get
     * @return mixed Net_LDAP_Error or option value
     */
    function getOption($option)
    {
        if ($this->_link) {
            if (defined($option)) {
                if (@ldap_get_option($this->_link, constant($option), $value)) {
                    return $value;
                } else {
                    $err = @ldap_errno($this->_link);
                    if ($err) {
                        $msg = @ldap_err2str($err);                        
                    } else {
                        $err = NET_LDAP_ERROR;
                        $msg = $this->errorMessage($err);
                    } 
                    return $this->raiseError($msg, $err);
                }
            } else {
                $this->raiseError("Unkown Option requested");
            }    
        } else {
            $this->raiseError("No LDAP connection");
        }
    }

    /**
     * Get the LDAP_PROTOCOL_VERSION that is used on the connection.
     *
     * A lot of ldap functionality is defined by what protocol version the ldap server speaks.
     * This might be 2 or 3.
     *
     * @return int
     */
    function getLDAPVersion()
    {
        if($this->_link) {
            $version = $this->getOption("LDAP_OPT_PROTOCOL_VERSION");
        } else {
            $version = $this->_config['version'];
        }
        return $version;
    }

    /**
     * Set the LDAP_PROTOCOL_VERSION that is used on the connection.
     *
     * @param int Version to set
     * @return mixed Net_LDAP_Error or TRUE
     */
    function setLDAPVersion($version = 0)
    {
        if (!$version) {
            $version = $this->_config['version'];
        }
        return $this->setOption("LDAP_OPT_PROTOCOL_VERSION", $version);
    }

    /**
     * Get the Net_LDAP version. 
     *
     * @return string Net_LDAP version
     */
    function getVersion ()
    {
        return $this->_version;
    }

    /**
     * Tell if a dn already exists 
     *
     * @param string
     * @return boolean
     */
    function dnExists($dn)
    {
        $dns = explode(",",$dn);
        $filter = array_shift($dns);
        $base= implode($dns,',');
        //$base = $dn;        
        //$filter = '(objectclass=*)';
        
        $result = @ldap_list($this->_link, $base, $filter, array(), 1, 1);
        if (ldap_errno($this->_link) == 32) {
            return false;
        }
        if (ldap_errno($this->_link) != 0) {
            PEAR::raiseError(ldap_error($this->_link), ldap_errno($this->_link));
        }
        if (@ldap_count_entries($this->_link, $result)) {
            return true;
        }
        return false;
    }
    

   /**
    * Get a specific entry based on the dn
    *
    * @param string dn
    * @param array Array of Attributes to select
    * @return mixed Net_LDAP_Entry or false
    */
   function &getEntry($dn, $attr = array())
   {
        $result = $this->search($dn, '(objectClass=*)',
                                array('scope' => 'base', 'attributes' => $attr));
        if (Net_LDAP::isError($result)) {
            return $result;
        }
        $entry = $result->shiftEntry();
        if (false == $entry) {
            return PEAR::raiseError('Could not fetch entry');
        }
        return $entry;
   }
   

    /**
     * Returns the string for an ldap errorcode.
     *
     * Made to be able to make better errorhandling
     * Function based on DB::errorMessage()
     * Tip: The best description of the errorcodes is found here:
     * http://www.directory-info.com/LDAP/LDAPErrorCodes.html
     *
     * @param int Error code
     * @return string The errorstring for the error.
     */
    function errorMessage($errorcode)
    {
        $errorMessages = array(
                              0x00 => "LDAP_SUCCESS",
                              0x01 => "LDAP_OPERATIONS_ERROR",
                              0x02 => "LDAP_PROTOCOL_ERROR",
                              0x03 => "LDAP_TIMELIMIT_EXCEEDED",
                              0x04 => "LDAP_SIZELIMIT_EXCEEDED",
                              0x05 => "LDAP_COMPARE_FALSE",
                              0x06 => "LDAP_COMPARE_TRUE",
                              0x07 => "LDAP_AUTH_METHOD_NOT_SUPPORTED",
                              0x08 => "LDAP_STRONG_AUTH_REQUIRED",
                              0x09 => "LDAP_PARTIAL_RESULTS",
                              0x0a => "LDAP_REFERRAL",
                              0x0b => "LDAP_ADMINLIMIT_EXCEEDED",
                              0x0c => "LDAP_UNAVAILABLE_CRITICAL_EXTENSION",
                              0x0d => "LDAP_CONFIDENTIALITY_REQUIRED",
                              0x0e => "LDAP_SASL_BIND_INPROGRESS",
                              0x10 => "LDAP_NO_SUCH_ATTRIBUTE",
                              0x11 => "LDAP_UNDEFINED_TYPE",
                              0x12 => "LDAP_INAPPROPRIATE_MATCHING",
                              0x13 => "LDAP_CONSTRAINT_VIOLATION",
                              0x14 => "LDAP_TYPE_OR_VALUE_EXISTS",
                              0x15 => "LDAP_INVALID_SYNTAX",
                              0x20 => "LDAP_NO_SUCH_OBJECT",
                              0x21 => "LDAP_ALIAS_PROBLEM",
                              0x22 => "LDAP_INVALID_DN_SYNTAX",
                              0x23 => "LDAP_IS_LEAF",
                              0x24 => "LDAP_ALIAS_DEREF_PROBLEM",
                              0x30 => "LDAP_INAPPROPRIATE_AUTH",
                              0x31 => "LDAP_INVALID_CREDENTIALS",
                              0x32 => "LDAP_INSUFFICIENT_ACCESS",
                              0x33 => "LDAP_BUSY",
                              0x34 => "LDAP_UNAVAILABLE",
                              0x35 => "LDAP_UNWILLING_TO_PERFORM",
                              0x36 => "LDAP_LOOP_DETECT",
                              0x3C => "LDAP_SORT_CONTROL_MISSING",
                              0x3D => "LDAP_INDEX_RANGE_ERROR",
                              0x40 => "LDAP_NAMING_VIOLATION",
                              0x41 => "LDAP_OBJECT_CLASS_VIOLATION",
                              0x42 => "LDAP_NOT_ALLOWED_ON_NONLEAF",
                              0x43 => "LDAP_NOT_ALLOWED_ON_RDN",
                              0x44 => "LDAP_ALREADY_EXISTS",
                              0x45 => "LDAP_NO_OBJECT_CLASS_MODS",
                              0x46 => "LDAP_RESULTS_TOO_LARGE",
                              0x47 => "LDAP_AFFECTS_MULTIPLE_DSAS",
                              0x50 => "LDAP_OTHER",
                              0x51 => "LDAP_SERVER_DOWN",
                              0x52 => "LDAP_LOCAL_ERROR",
                              0x53 => "LDAP_ENCODING_ERROR",
                              0x54 => "LDAP_DECODING_ERROR",
                              0x55 => "LDAP_TIMEOUT",
                              0x56 => "LDAP_AUTH_UNKNOWN",
                              0x57 => "LDAP_FILTER_ERROR",
                              0x58 => "LDAP_USER_CANCELLED",
                              0x59 => "LDAP_PARAM_ERROR",
                              0x5a => "LDAP_NO_MEMORY",
                              0x5b => "LDAP_CONNECT_ERROR",
                              0x5c => "LDAP_NOT_SUPPORTED",
                              0x5d => "LDAP_CONTROL_NOT_FOUND",
                              0x5e => "LDAP_NO_RESULTS_RETURNED",
                              0x5f => "LDAP_MORE_RESULTS_TO_RETURN",
                              0x60 => "LDAP_CLIENT_LOOP",
                              0x61 => "LDAP_REFERRAL_LIMIT_EXCEEDED",
                              1000 => "Unknown Net_LDAP Error"
                              );

         return isset($errorMessages[$errorcode]) ? $errorMessages[$errorcode] : $errorMessages[LDAP_ERROR];
    }
    
    /**
     * Tell whether value is a Net_LDAP_Error or not
     *
     * @access public
     * @param mixed 
     * @return boolean
     */
    function isError($value)
    {
        return (is_a($value, "Net_LDAP_Error") || parent::isError($value));
    }
        
    /**
     * gets a root dse object
     *
     * @access public
     * @author Jan Wagner <wagner@netsols.de>
     * @param array Array of attributes to search for
     * @return object mixed Net_LDAP_Error or Net_LDAP_RootDSE
     */
    function &rootDse($attrs = null) 
    {
        require_once('Net/LDAP/RootDSE.php');
        
        if (is_array($attrs) && count($attrs) > 0 ) {
            $attributes = $attrs;
        } else {
            $attributes = array('namingContexts',
                                'altServer',
                                'supportedExtension',
                                'supportedControl',
                                'supportedSASLMechanisms',
                                'supportedLDAPVersion',
                                'subschemaSubentry' );
        }
        $result = $this->search('', '(objectClass=*)', array('attributes' => $attributes, 'scope' => 'base'));
        if (Net_LDAP::isError($result)) {
            return $result;
        }
        $entry = $result->shiftEntry();
        if (false === $entry) {
            return PEAR::raiseError('Could not fetch RootDSE entry');
        }
        return new Net_LDAP_RootDSE($entry);
    }
    
    /**
     * alias function of rootDse() for perl-ldap interface
     *
     * @access public
     * @see rootDse()
     */
    function &root_dse() 
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'rootDse'), $args);
    }
    
    /**
     * get a schema object
     *
     * @access public
     * @author Jan Wagner <wagner@netsols.de>
     * @param string Subschema entry dn
     * @return object mixed Net_LDAP_Schema or Net_LDAP_Error
     */
     function &schema($dn = null)
     {
        if (false == is_a($this->_schema, 'Net_LDAP_Schema'))
        {
            require_once('Net/LDAP/Schema.php');
            
            $this->_schema = & new Net_LDAP_Schema();
    
            if (is_null($dn)) {
                // get the subschema entry via root dse
                $dse = $this->rootDSE(array('subschemaSubentry'));
                if (false == Net_LDAP::isError($dse)) {
                    $base = $dse->getValue('subschemaSubentry', 'single');
                    if (!Net_LDAP::isError($base)) {
                        $dn = $base;
                    }
                }
            }
            if (is_null($dn)) {
                $dn = 'cn=Subschema';
            }        
            
            // fetch the subschema entry
            $result = $this->search($dn, '(objectClass=*)',
                                    array('attributes' => array_values($this->_schema->types),
                                          'scope' => 'base'));
            if (Net_LDAP::isError($result)) {
                return $result;
            }
    
            $entry = $result->shiftEntry();
            if (false === $entry) {
                return PEAR::raiseError('Could not fetch Subschema entry');
            }
            
            $this->_schema->parse($entry);
        }
        return $this->_schema;
    }

    /**
     * Encodes given attributes to UTF8 if needed
     *
     * This function takes attributes in an array and then checks against the schema if they need
     * UTF8 encoding. If that is so, they will be encoded. An encoded array will be returned and
     * can be used for adding or modifying.
     *
     * @access public
     * @param array Array of attributes
     * @return array Array of UTF8 encoded attributes
     */
    function utf8Encode($attributes)
    {
        return $this->_utf8($attributes, 'utf8_encode');
    }

    /**
     * Decodes the given attribute values
     *
     * @access public
     * @param array Array of attributes
     * @return array Array with decoded attribute values
     */
    function utf8Decode($attributes)
    {
        return $this->_utf8($attributes, 'utf8_decode');
    }

    /**
     * Encodes or decodes attribute values if needed
     *
     * @access private
     * @param array Array of attributes
     * @param array Function to apply to attribute values
     * @return array Array of attributes with function applied to values
     */
    function _utf8($attributes, $function)
    {
        if (!$this->_schema) {
            $this->_schema = $this->schema();
        }

        if (!$this->_link || Net_LDAP::isError($this->_schema) || !function_exists($function)) {
           return $attributes;
        }

        if (is_array($attributes) && count($attributes) > 0) {
            
            foreach( $attributes as $k => $v ) {
                
                if (!isset($this->_schemaAttrs[$k])) {

                    $attr = $this->_schema->get('attribute', $k);
                    if (Net_LDAP::isError($attr)) {
                        continue;
                    }

                    if (false !== strpos($attr['syntax'], '1.3.6.1.4.1.1466.115.121.1.15')) {
                        $encode = true;
                    } else {
                        $encode = false;
                    }                  
                    $this->_schemaAttrs[$k] = $encode;
                  
                } else {
                    $encode = $this->_schemaAttrs[$k];
                }

                if ($encode) {
                    if (is_array($v)) {
                        foreach ($v as $ak => $av) {
                            $v[$ak] = call_user_func($function, $av );
                        }
                    } else {
                        $v = call_user_func($function, $v);
                    }
                }
                $attributes[$k] = $v;
            }
        }
        return $attributes;
    }
    
    /**
     * Get the LDAP link
     *
     * @access public
     * @return resource LDAP link
     */
    function &getLink()
    {
        return $this->_link;
    }
}

/**
 * Net_LDAP_Error implements a class for reporting portable LDAP error messages.
 *
 * @package Net_LDAP
 */
class Net_LDAP_Error extends PEAR_Error
{
    /**
     * Net_LDAP_Error constructor.
     *
     * @param mixed Net_LDAP error code, or string with error message.
     * @param integer what "error mode" to operate in
     * @param integer what error level to use for $mode & PEAR_ERROR_TRIGGER
     * @param mixed additional debug info, such as the last query
     * @access public
     * @see PEAR_Error
     */
    function Net_LDAP_Error($code = NET_LDAP_ERROR, $mode = PEAR_ERROR_RETURN,
                            $level = E_USER_NOTICE, $debuginfo = null)
    {
        if (is_int($code)) {
            $this->PEAR_Error('Net_LDAP_Error: ' . Net_LDAP::errorMessage($code), $code, $mode, $level, $debuginfo);
        } else {
            $this->PEAR_Error("Net_LDAP_Error: $code", LDAP_ERROR, $mode, $level, $debuginfo);
        }
    }
}

?>
