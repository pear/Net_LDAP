<?php
/**
 * Net::LDAP - manipulate LDAP servers the right way!
 *
 * (the perl Net::LDAP way)
 *
 * @author  Tarjei Huse
 * @version $Id$
 * @package Net_LDAP
 */

/**
 * Include required files
 */
require_once 'PEAR.php';
require_once 'LDAP/Entry.php';
require_once 'LDAP/Search.php';

/**
 * Main class
 *
 * @package Net_LDAP
 */
class Net_LDAP extends PEAR
{
    /**
     * Class configuration array
     * dn = the DN to bind as.
     * host = the ldap host to connect to
     * password = no explanation needed
     * base = ldap base
     * port = the server port
     * tls - is set - the ldap_start_tls() is run after connecting.
     * version = ldap version (defaults to v 3)
     * filter = default search filter
     * scope = default search scope
     *
     * @var array
     */
     var $_config = array ('dn',
         'host' => 'localhost',
         'password',
         'tls' => false,
         'base' => '',
         'port' => 389,
         'version' => 3,
         'filter' => '(uid=*)',
         'scope' => 'sub'
        );
  
    /**
     * The ldap resourcelink.
     * You should not need to touch this.
     *
     * @var object
     */
     var $_link;

    /**
     * Net ldap, Sets the configarray and binds if the config array contains the server prameter
     *
     * @access protected
     * @param array $config - se description in class vars
     * @return void
     */
    function Net_Ldap($_config = array(), $bind = false)
    {
        foreach ($_config as $k => $v) {
            $_{$k} = $v;
        }
    }

    /**
     * connect - create the initial ldap-object
     *
     *
     * function &connect($_config = array())
     *
     * Static function that returns either an error object or the new Net_ldap object.
     *
     * @access public
     * @param  $_config array containgin the needed ldap configuration parameters. Defaults:
     *                  'dn',
     *                  'host' => 'localhost',
     *                  'password',
     *                  'tls' => false,
     *                  'base' => '',
     *                  'port'=>389,
     *                  'version'=> 3,
     *                  'filter' => '(uid=*)',
     *                  'scope' => 'sub'
     * @return reference object , either a PEAR error or a Net_ldap object.
     */
    function &connect($_config = array())
    {
        @$obj = & new Net_Ldap;
        $err  = $obj -> bind($_config);

        if (Net_Ldap :: isError($err)) {
            return $err;
        }

        return $obj;
    }

    /**
     * Bind to the ldap-server
     *
     * The function may be used if you do not create the object using Net_ldap::connect.
     *
     * @access public
     * @param  array $ config see connect for the structure and defaults.
     * @return mixed true if the bind went ok, Net_ldap_error if not.
     */
    function bind($config = array())
    {
        foreach ($config as $k => $v) {
            $this -> _config[$k] = $v;
        }

        if ($this -> _config['host']) {
             $conn = ldap_connect($this -> _config['host'], $this -> _config['port']);
        } else {
             return $this -> raiseError("Host not defined in config. {$this->_config['host']}");
        }

        if (!$conn) {
             return $this -> raiseError("Could not connect to server. ldap_connect failed.",52 );// there isn't a good errcode for thisone! I chose 52.
        }
        // You must set the version and start tls BEFORE binding!
        // (quite logical when you think of it...
        if ($this -> _config['version'] == 3 && !@ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            return $this -> raiseError("Could not set ldap v3: " . ldap_error($conn),ldap_errno($conn));
        }

        if ($this -> _config['tls'] ) {
            if (!@ldap_start_tls($conn)) {
                return $this -> raiseError("TLS not started. Error:" . ldap_error($conn),ldap_errno($conn));
            }
        }
        
        if (isset($this -> _config['dn']) && isset($this -> _config['password'])) {
             $bind = @ldap_bind($conn, $this -> _config['dn'], $this -> _config['password']);
        } else {
             $bind = @ldap_bind($conn);
        }

        if (!$bind) {
             return $this -> raiseError("Bind failed " . @ldap_error($conn), ldap_errno($conn)  );
        }

        $this -> _link = $conn;

        return true;
    }

    /**
     * start_tls() - start an encrypted session
     *
     * Starts an encrypted session
     *
     * @access public
     * @return - ldap_error if error else true
     */
    function start_tls()
    {
        if (!@ldap_set_option($this -> _link, LDAP_OPT_PROTOCOL_VERSION, 3)) {
             return $this -> raiseError("Could not set ldap v3" . ldap_error($this -> _link), ldap_errno($this -> _link));
        }
        return true;
    }

    /**
     * done - close ldap-connection.
     *
     * Close the ldapconnection. Use this when the session is over.
     *
     * @access private
     * @return void
     */
    function done()
    {
        $this -> _Net_Ldap();
    }

    /**
     * Destructor
     *
     * @access private
     */
    function _Net_Ldap()
    {
        @ldap_close($this -> _link);
    }

    /**
     * add - add a new entryobject to a directory.
     *
     * Use add to add a new Net_ldap_entry object to a directory.
     *
     * @param  $ - $entry a Net_ldap_entry object.
     * @return mixed Net_ldap_error if feilure true if sucess.
     */
    function add($entry)
    {
        if (@ldap_add($this -> _link, $entry -> dn(), $entry -> attributes())) {
             return true;
        } else {
             return $this -> raiseError("Could not add entry " . $entry -> dn() . " " . ldap_error(), ldap_errno($this -> _link));
        }
    }

    /**
     * delete - Delete an object,
     *
     * Delete an object, the object may either be a string representing the dn or a ldap_entry object.
     *
     * @access public
     * @param mixed $dn - string representing the dn or a Net_ldap_entry object
     *         array $params - an array possible values:
     *                      bool 'recursive' (default false) - delete all subentries of an entry as well as the entry itself
     *                      
     */
    function delete($dn,$param = array())
    {
        if (is_object($dn) && get_class($dn) == 'net_ldap_entry') {
             $dn = $dn -> dn();
        } else {
            if (!is_string($dn)) {
                 return $this -> raiseError("$dn not a string nor an entryobject!",34); // this is what the server would say: invalid_dn_syntax.
            }
        }
        
        if ($param['recursive'] ) {
            $searchresult = @ldap_list($this -> _link, $dn, "(objectClass=*)",array(""));
            
            if ($searchresult) {
                $entries = ldap_get_entries($this -> _link, $searchresult);
             
                for($i=0;$i<$entries['count'];$i++){
                    $result= $this -> delete ($entries[$i]['dn'],array('recursive' => true));
                    if (!$result) {
                        $errno = ldap_errno($this -> _link);
                        return $this -> raiseMessage ("Net_LDAP::delete: " . $this -> errorMessage($errno), $errno);
                    }
                
                    if(PEAR::isError($result)){
                        return $result;
                    }
                
                }
            }
                if (!@ldap_delete($this -> _link, $dn)) {
                    $error = ldap_errno($this -> _link );
                
                    if ($error == 0) {
                        return true;
                    
                    /* entry has subentries this should not happen!*/
                    } elseif ($error == 66) {
                        return $this -> raiseError("Cound not delete entry " . $dn . " because of subentries.Use the recursiv param to delete them. ",$error);
                    } else {
                        return $this -> raiseError("Net_LDAP::delete: " .$this -> errorMessage($error) , $error);
                    }        
                }        
        } else {
            if (!@ldap_delete($this -> _link, $dn)) {
                $error = ldap_errno($this -> _link );
                /* entry has subentries */
                if ($error == 66) {
                    return $this -> raiseError("Net_LDAP::delete: Cound not delete entry " . $dn . " because of subentries.Use the recursiv param to delete them. "); 
                } else {
                    return $this -> raiseError("Net_LDAP::delete: Could not delete entry " . $dn ." because: ". $this -> errorMessage($error),  $error);
                }
            }
        }
        return true;
    }

    /**
     * modify - modify an ldapentry
     *
     *
     * This is taken from the perlpod of net::ldap, and explains things quite nicely.
     * modify ( DN, OPTIONS )
     * Modify the contents of DN on the server. DN May be a
     * string or a Net::LDAP::Entry object.
     *
     * dn  This option is here for compatibility only, and
     * may be removed in future.  Previous releases did
     * not take the DN argument which replaces this
     * option.
     *
     * add The add option should be a reference to a HASH.
     * The values of the HASH are the attributes to add,
     * and the values may be a string or a reference to a
     * list of values.
     *
     * delete
     * A reference to an ARRAY of attributes to delete.
     * TODO: This does not support deleting one or two values yet - use
     * replace.
     *
     * replace
     * The <replace> option takes a argument in the same
     * form as add, but will cause any existing
     * attributes with the same name to be replaced. If
     * the value for any attribute in the årray is a ref­
     * erence to an empty string the all instances of the
     * attribute will be deleted.
     *
     * changes
     * This is an alternative to add, delete and replace
     * where the whole operation can be given in a single
     * argument. The argument should be a array
     *
     * Values in the ARRAY are used in pairs, the first
     * is the operation add, delete or replace and the
     * second is a reference to an ARRAY of attribute
     * values.
     *
     * The attribute value list is also used in pairs.
     * The first value in each pair is the attribute name
     * and the second is a reference to a list of values.
     *
     * Example
     * $ldap->modify ( $dn, array (changes=>array(
     * 'delete' => array('faxNumber'=>''),
     * 'add'=>array('sn'=>'Barr'),
     * 'replace' => array(email=>'tarjei@nu.noæ)
     * )
     * )
     * );
     *
     * @access public
     * @param string $dn representing a DN, array $params containing the changes.
     * @return mixed : Net_ldap_error if failure and true if success.
     */
    function modify($dn , $params = array())
    {
        if (is_object($dn)) {
             $dn = $dn -> dn();
        }

         // since $params['dn'] is not used in net::ldap now:
        if (isset($params['dn'])) {
             return $this -> raiseError("This feature will not be implemented!");
        }

        if (isset($params['changes'])) {

             if (isset($params['changes']['add']) &&
                     !@ldap_modify($this -> _link, $dn, $params['changes']['add'])) {

                 return $this -> raiseError("Net_LDAP::modify: $dn not modified because:" . ldap_error($this -> _link), ldap_errno($this -> _link));
             }

             if (isset($params['changes']['replace']) &&
                     !@ldap_modify($this -> _link, $dn, $params['changes']['replace'])) {

                 return $this -> raiseError("Net_LDAP::modify: replace change didn't work: " . ldap_error($this -> _link), ldap_errno($this -> _link));
             }

             if (isset($params['changes']['delete']) &&
                     !@ldap_mod_del($this -> _link, $dn, $params['changes']['delete'])) {

                 return $this -> raiseError("Net_LDAP::modify:delete did not work" . ldap_error($this -> _link), ldap_errno($this -> _link));
             }
        }

        if (isset($params['add']) && !@ldap_add($this -> _link, $dn, $params['add'])) {
            return $this -> raiseError(ldap_error($this -> _link), ldap_errno($this -> _link));
        }

        if (isset($params['replace']) && !@ldap_modify($this -> _link, $dn, $params['replace'])) {
            return $this -> raiseError(ldap_error($this -> _link), ldap_errno($this -> _link));
        }

        if (isset($params['delete'])) {
             // since you delete an attribute by making it empty:
            foreach ($params['delete'] as $k) {
                $params['delete'][$k] = '';
            }

            if (!@ldap_modify($this -> _link, $dn, $params['delete'])) {
                 return $this -> raiseError(ldap_error($this -> _link), ldap_errno($this -> _link));
            }
        }
        // everything went fine :)
        return true;
    }

    /**
     * search() - run a ldap query
     *
     *   Search is used to query the ldap-database. Seardh will either return an Net_ldap_search object
     *   or an Net_ldap_error object.
     *
     * @access public
     * @param  $base - ldap searchbase, $filter - ldap filter, both may be omitted returning a search after uid=* for the whole server.o
     *          $params - array of options: 
     *                  scope - By default the search is performed on the whole tree below the specified base object. 
     *                          This may be chaned by specifying a "scope" parameter with one of the following values.
     *                          possible values:
     *                          
     *                          one  -  Search the entries immediately below $base. This is a more efficient search in many situations.
     *                          base -  Read just one entry ($base)
     *                          sub  -  Search the whole tree below $base. This is the default.
     *                  
     *                  sizelimit - (default: 0 = no limit) limit the nr. of entries returned by the search. This i also limmited by the ldapserver.
     *
     *                  timelimit - (default: 0 = no limit) limit in nr. of seconds the time the search takes. 
     *                  
     *                  attrsonly - (default: 0 = attributes and values). If set to 1 this will only return the attribtues of an entry - NOT the values. 
     *                  attributes - (default: none). an array of the attributes that an returned entry should contain.
     *                  
     *                  
     *                  
     * [NOT IMPLEMENTED]deref - By default aliases are dereferenced to locate the base object for the search, but not when
     *                          searching subordinates of the base object.
     *                          This may be changed by specifying a "deref" parameter with one of the following values:
     *                          
     *                          never  - Do not dereference aliases in searching or in locating the base object of the search.
     *                          search - Dereference aliases in subordinates of the base object in searching, but not in 
     *                                   locating the base object of the search. 
     *                          find - 
     *                          always - 
     * @return object Net_ldap_search or Net_ldap_error
     */
    function search($base = null, $filter = null, $params = array())
    {		
    	if (is_null($base)) {
            $base = $this -> _config['base'];
        }
        if (is_null($filter)) {
            $filter = $this -> _config['filter'];
        }        
        
        /* setting searchparameters  */
        (isset ($params['sizelimit'] ) ) ? $sizelimit  = $params['sizelimit']  : $sizelimit = 0;
        (isset ($params['timelimit'] ) ) ? $timelimit  = $params['timelimit']  : $timelimit = 0;
        (isset ($params['attrsonly'] ) ) ? $attrsonly  = $params['attrsonly']  : $attrsonly = 0;        
        (isset ($params['attributes']) ) ? $attributes = $params['attributes'] : $attributes = array();        
       
        if (!is_array ( $attributes ) ) {
            $this->raiseError("The param attributes must be an array!");
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

        $search = @call_user_func( $search_function, 
                                   $this->_link,
                                   $base,
                                   $filter,
                                   $attributes,
                                   $attrsonly,
                                   $sizelimit,
                                   $timelimit );

        if ($err = ldap_errno($this->_link)) { 

            if ($err == 32) {
                return $obj = & new Net_LDAP_Search (& $search, $this -> _link); // Errorcode 32 = no such object, i.e. a nullresult.
                
            // Errorcode 4 = sizelimit exeeded. this will be handled better in time...
            //} elseif ($err == 4) {
            //    return $obj = & new Net_LDAP_Search (& $search, $this -> _link); 
            
            } elseif ($err == 87) {
                return $this -> raiseError("function ldap_search got error \"bad searchfilter\". Filter: $filter",$err);
            } else {
                
                return $this -> raiseError("Net_LDAP::search: Got error \""
                                        . $this->errorMessage($err) 
                                        . "\" \nParameters: \nbase: $base \nfilter: $filter \nscope: $scope \nldaperrornumber: "
                                        . $err .  "\nattributes: $attributes",$err); 
                // This should get the user something to work with!
            }
        } else {
            @$obj = & new Net_Ldap_Search(& $search, $this -> _link);
           return $obj;
        }

    }

    /* getVersion () - get the LDAP_VERSION that is used on the connection.
     * A lot of ldap functionality is defined by what version the ldap-server is, either v2 or v3.
     * @params none
     * @return int version - the version used.
     *
     * */

    function getVersion () 
    {
        return $this->_config['version'];
    }

    /* UTF8Encode ($array) - utfencode an array
     * Utf8 encodes the values in the supplied array.
     * @params array
     * @return array the encoded array
    */

    function UTF8Encode($array)
    {

      if (is_array($array) ) {
          $return = array();
          foreach ($array as $k => $v){
            $return[$k] = Net_LDAP::UTF8Encode($array[$k]);
          }
          return $return;


      } else {
        return utf8_encode($array);
      }



    }
    /* UTF8Decode - decode an array of utf8encoded values.
     * @returns array utf8decoded values
     * @params array the array to be decoded.
    */

    function UTF8Decode($array)
    {

        if (is_array($array) ) {
            $return = array();
            foreach ($array as $k => $v){
                $return[$k] = Net_LDAP::UTF8Decode($array[$k]);
            }
            return $return;
        } else {
            return utf8_decode($array);
        }
    }

    /* errorMessage - returns the string for an ldap errorcode.
     * Made to be able to make better errorhandling
     * Function based on DB::errorMessage()
     * Tip: The best description of the errorcodes is found here: http://www.directory-info.com/LDAP/LDAPErrorCodes.html
     * @params errorcode - the ldap errorcode
     * @return string - the errorstring for the error.
    */
    function errorMessage ($errorcode)
    {


      $errorMessages = array (
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
                              0x61 => "LDAP_REFERRAL_LIMIT_EXCEEDED"
                              );


         return isset($errorMessages[$errorcode]) ? $errorMessages[$errorcode] : $errorMessages[LDAP_ERROR];
    }
       
    /**
     * gets a root dse object
     *
     * @access public
     * @author Jan Wagner <wagner@netsols.de>
     * @param array $attrs Array of attributes to search for
     * @return object LDAP_Error or Net_Ldap_RootDSE
     */
    function &rootDse( $attrs = null ) 
    {
        require_once( 'Net/LDAP/RootDSE.php' );
        
        if( is_array( $attrs ) && count( $attrs ) > 0 ) {
            $attributes = $attrs;
        } else {
            $attributes = array( 'namingContexts',
                                 'altServer',
                                 'supportedExtension',
                                 'supportedControl',
                                 'supportedSASLMechanisms',
                                 'supportedLDAPVersion',
                                 'subschemaSubentry' );
        }
        $result = $this->search( '', '(objectClass=*)',
                                 array( 'attributes' => $attributes, 'scope' => 'base' ) );
        if( Net_LDAP::isError( $result ) ) return $result;
        
        $entry = $result->shift_entry();
        if( false === $entry ) return $this->raiseError( 'Could not fetch RootDSE entry' );

        return new Net_LDAP_RootDSE( $entry );
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
        return call_user_func_array( array( &$this, 'rootDse' ), $args );
    }
    
    /**
     * get a schema object
     *
     * @access public
     * @author Jan Wagner <wagner@netsols.de>
     * @return object Net_LDAP_Schema or Net_LDAP_Error
     */
     function &schema( $dn = null )
     {
        require_once( 'Net/LDAP/Schema.php' );
        
        $schema = & new Net_LDAP_Schema();

        if( is_null( $dn ) ) {
            // get the subschema entry via root dse
            $dse = $this->rootDSE( array( 'subschemaSubentry' ) );
            if( false == Net_Ldap::isError( $dse ) )
            {
                $base = $dse->getValue( 'subschemaSubentry', 'single' );
                if( !Net_Ldap::isError( $base ) ) $dn = $base;
            }
        }
        if( is_null( $dn ) ) $dn = 'cn=Subschema';
        
        // fetch the subschema entry
        $result = $this->search( $dn, '(objectClass=*)',
                                 array( 'attributes' => array_values( $schema->types ), 'scope' => 'base' ) 
                               );
        if( Net_Ldap::isError( $result ) ) return $result;

        $entry = $result->shift_entry();
        if( false === $entry ) return $this->raiseError( 'Could not fetch Subschema entry' );

        $schema->parse( $entry );

        return $schema;
     }
}



// Class ldap_search implements my own search_class

/**
 * LDAP_Error implements a class for reporting portable ldap error
 * messages.
 *
 * @package Net_LDAP
 * @author  Stig Bakken <ssb@fast.no>
 */
class Net_Ldap_Error extends PEAR_Error
{
    /**
     * LDAP_Error constructor.
     *
     * @param mixed $code DB error code, or string with error message.
     * @param integer $mode what "error mode" to operate in
     * @param integer $level what error level to use for $mode & PEAR_ERROR_TRIGGER
     * @param mixed $debuginfo additional debug info, such as the last query
     * @access public
     * @see PEAR_Error
     */
    function LDAP_Error($code = DB_ERROR, $mode = PEAR_ERROR_RETURN,
         $level = E_USER_NOTICE, $debuginfo = null)
    {
        if (is_int($code)) {
            $this -> PEAR_Error('LDAP Error: ' . LDAP :: errorMessage($code), $code, $mode, $level, $debuginfo);
        } else {
            $this -> PEAR_Error("LDAP Error: $code", LDAP_ERROR, $mode, $level, $debuginfo);
        }
    }
}
?>
