<?PHP

require_once( 'PEAR.php' );
require_once( 'Net/LDAP.php' );

/**
 * Load an LDAP Schema and provide information
 *
 * @package Net_LDAP
 * @author Jan Wagner <wagner@netsols.de>
 * @version $Id$
 */
 class Net_LDAP_Schema extends PEAR
 {
    /**
     * Net_LDAP_Entry containing the Subschema entry
     *
     * @access private
     * @var object $_entry Net_LDAP_Entry
     */
    var $_entry;
    
    /**
     * Array which holds errors
     *
     * @access private
     * @var array $_errors Array of errors
    */
    var $_errors = array();
     
    /**
     * Map of entry types to ldap attributes of subschema entry
     *
     * @access private
     * @var array
     */
    var $_types = array( 'attribute'        => 'attributeTypes',
                         'ditcontentrule'   => 'dITContentRules',
                         'ditstructurerule' => 'dITStructureRules',
                         'matchingrule'     => 'matchingRules',
                         'matchingruleuse'  => 'matchingRuleUse',
                         'nameform'         => 'nameForms',
                         'objectclass'      => 'objectClasses',
                         'syntax'           => 'ldapSyntaxes' );
    /**#@+
     * Array of entries belonging to this type
     *
     * @access private
     * @var array
     */
    var $_attributeTypes   = array();
    var $_matchingRules    = array();
    var $_matchingRuleUse  = array();
    var $_ldapSyntaxes     = array();
    var $_objectClasses    = array();
    var $_dITContentRules  = array();
    var $_dITStructureRules = array();
    var $_nameForms        = array();
    /**#@-*/

    /**
     * hash of all fetched oids
     *
     * @access private
     */
    var $_oids = array();
        
    /**
     * constructor of the class
     *
     * Fetches $dn via rootDSE if none was given. Then parses the subschema entry.
     *
     * @access public
     * @param object Net_LDAP Net_LDAP instance for searching
     * @param string $dn Subschema entry     
     */       
    function Net_LDAP_Schema( &$ldap, $dn )
    {
        $this->PEAR( 'Net_LDAP_Error' ); // default error class
        $this->setErrorHandling( PEAR_ERROR_CALLBACK, array( &$this, '_pushError' ) );

        if( false == is_null( $dn ) ) {
            $base = $dn;
        } else {
            // get the subschema entry via root dse
            $dse = $ldap->rootDSE( array( 'subschemaSubentry' ) );
            if( Net_Ldap::isError( $dse ) ) {
                $this->raiseError( $dse );
                return false;
            }
            $base = $dse->getValue( 'subschemaSubentry', 'single' );
            if( Net_Ldap::isError( $base ) ) {
                $this->raiseError( $base );
                return false;
            }
        }
        
        // fetch attributes from the subschema entry
        $result = $ldap->search( $base,
                                '(objectClass=*)',
                                 array( 'attributes' => array_values( $this->_types ),
                                        'scope' => 'base' ) 
                               );
        if( Net_Ldap::isError( $result ) ) { 
            $this->_raiseError( $result );
            return false; 
        }

        $entry = $result->shift_entry();
        if( false === $entry ) {
            $this->raiseError( 'Could not fetch Subschema entry' );
            return false;
        }

        $this->_entry = &$entry; // save the entry
        
        $this->_parse(); // parse the schema
    }

    /**
     * Return a hash of entries for the given type
     *
     * Returns a hash of entry for th givene type. Types may be:
     * objectclasses, attributes, ditcontentrules, ditstructurerules, matchingrules,
     * matchingruleuses, nameforms, syntaxes
     *
     * @access public
     * @var string $type
     * @return array
     */
    function &getAll( $type )
    {
        $map = array( 'objectclasses'     => &$this->_objectClasses,
                      'attributes'        => &$this->_attributeTypes,
                      'ditcontentrules'   => &$this->_dITContentRules,
                      'ditstructurerules' => &$this->_dITStructureRules,
                      'matchingrules'     => &$this->_matchingRules,
                      'matchingruleuses'  => &$this->_matchingRuleUse,
                      'nameforms'         => &$this->_nameForms,
                      'syntaxes'          => &$this->_ldapSyntaxes );

        $key = strtolower( $type );
        return ( ( key_exists( $key, $map ) ) ? $map[ $key ] : false );
    }
    
    /**
     * Return a specific entry
     *
     * @access public
     * @param string $type Type of name
     * @param string $name Name or OID to fetch
     * @return mixed Entry or false
     */
     function &get( $type, $name )
     {
        $type = strtolower( $type );
        if( false == key_exists( $type, $this->_types ) ) return false;

        $name = strtolower( $name );
        $type_var = &$this->{ '_' . $this->_types[ $type ] };
        
        if( key_exists( $name, $type_var ) ) {
            return $type_var[ $name ];
        } elseif( key_exists( $name, $this->_oids ) && $this->_oids[ $name ][ 'type' ] == $type ) {
            return $this->_oids[ $name ];
        } else {
            $this->raiseError( "Could not find $type $name" );
            return false;
        }
     }

     
    /**#@+
     * Fetches attributes that MAY be present in the given objectclass
     *
     * @access public
     * @param string $oc Name or OID of objectclass
     * @return mixed Array with attributes or false
     */
    function may( $oc )
    {
        return $this->_must_may( $oc, 'may' );
    }
    
    /**
     * Fetches attributes that MUST be present in the given objectclass
     */
    function must( $oc )
    {
        return $this->_must_may( $oc, 'must' );
    }
    /**#@-*/
     
    /**
     * Fetches the given attribute from the given objectclass
     *
     * @access private
     * @param string $oc Name or OID of objectclass
     * @param string attr Name of attribute to fetch
     * @return mixed The attribute or false on error
     */
    function _must_may( $oc, $attr )
    {
        $oc = strtolower( $oc );
        if( key_exists( $oc, $this->_objectClasses ) &&
            key_exists( $attr, $this->_objectClasses[ $oc ] ) )
        {
            return $this->_objectClasses[ $oc ][ $attr ];
        }
        elseif ( key_exists( $oc, $this->_oids) && 
                 $this->_oids[ $oc ][ 'type' ] == 'objectclass' &&
                 key_exists( $attr, $this->_oids[ $oc ] ) )
        {
            return $this->_oids[ $oc ][ $attr ];
        } else {
            $this->raiseError( "Could not find $attr attributes for $oc " );
            return false;
        }
    }

    /**
     * parses the schema
     *
     * @access private
     */
    function _parse() 
    {
        foreach ( $this->_types as $type => $attr )
        {
            // initialize map type to entry
            $type_var = '_' . $attr ;
            $this->{ $type_var } = array();
            
            // get values for this type
            $values = $this->_entry->get_value( $attr );
                        
            if( is_array( $values ) )
            {
                foreach ( $values as $value )
                {
                    unset( $schema_entry ); // this was a real mess without it
                                        
                    // get the schema entry
                    $schema_entry = $this->_parse_entry( $value );

                    // set the type
                    $schema_entry[ 'type' ] = $type;
                    
                    // save a ref in $_oids
                    $this->_oids[ $schema_entry[ 'oid' ] ] = &$schema_entry;                    
                    
                    // save refs for all names in type map
                    $names = $schema_entry[ 'aliases' ];
                    array_push( $names, $schema_entry[ 'name' ] );
                    foreach( $names as $name ) $this->{ $type_var }[ strtolower( $name ) ] = &$schema_entry;
                }
            }
        }
    }
    
    /**
     * parses an attribute value into a schema entry
     *
     * @access private
     * @param string $value Attribute values
     * @return mixed Schema entry array or false
     */
    function &_parse_entry( $value )
    {
        // tokens that have no value associated
        $noValue = array( 'single-value',
                          'obsolete',
                          'collective',
                          'no-user-modification',
                          'abstract',
                          'structural',
                          'auxiliary' );
        
        // tokens that can have multiple values
        $multiValue = array( 'must', 'may', 'sup' );
        
        $schema_entry = array( 'aliases' => array() ); // initilization
        
        $tokens = $this->_tokenize( $value ); // get an array of tokens
       
        // remove surrounding brackets
        if( $tokens[0] == '(' ) array_shift( $tokens );
        if( $tokens[ count( $tokens ) -1 ] == ')' ) array_pop( $tokens ); // -1 doesnt work on arrays :-(

        $schema_entry[ 'oid' ] = array_shift( $tokens ); // first token is the oid
        
        while( count( $tokens ) > 0 ) // cycle over the tokens until none are left
        {
            $token = strtolower( array_shift( $tokens ) );
            if(in_array( $token, $noValue ) ) $schema_entry[ $token ] = 1; // single value token
            else {
                // this one follows a string or a list if it is multivalued
                if( ( $schema_entry[ $token ] = array_shift( $tokens ) ) == '(' ) 
                {
                    // this creates the list of values and cycles through the tokens
                    // until the end of the list is reached ')'
                    $schema_entry[ $token ] = array();
                    while( $tmp = array_shift( $tokens ) ) {
                        if( $tmp == ')' ) break;
                        if( $tmp != '$' ) array_push( $schema_entry[ $token ], $tmp );
                    }
                }
                // create a array if the value should be multivalued but was not
                if( in_array( $token, $multiValue ) && !is_array( $schema_entry[ $token ] ) ) {
                    $schema_entry[ $token ] = array( $schema_entry[ $token ] );
                }
            }
        }
        
        // get max length from syntax        
        if( key_exists( 'syntax', $schema_entry ) ) {
            if( preg_match( '/{(\d+)}/', $schema_entry[ 'syntax'], $matches ) ) {
                $schema_entry[ 'max_length' ] = $matches[1];
            }
        }        
        // force a name
        if( empty( $schema_entry[ 'name' ] ) ) $schema_entry[ 'name' ] = $schema_entry[ 'oid' ];
        
        // make one name the default and put the other ones into aliases
        if( is_array( $schema_entry[ 'name' ] ) ) {
            $aliases = $schema_entry[ 'name' ];
            $schema_entry[ 'name' ] = array_shift( $aliases );
            $schema_entry[ 'aliases' ] = $aliases;
        }
        
        return $schema_entry;
    }
    
    /**
     * tokenizes the given value into an array of tokens
     *
     * @access private
     * @param string $value String to parse
     * @return array $tokens Array of tokens
     */
    function _tokenize( $value )
    {
        $tokens = array();        // array of tokens
        $matches = array();       // matches[0] full pattern match, [1,2,3] subpatterns
        
        // this one is taken from perl-ldap, modified for php
        $pattern = "/\s* (?:([()]) | ([^'\s()]+) | '((?:[^']+|'[^\s)])*)') \s*/x";
        
        preg_match_all( $pattern, $value, $matches );

        for( $i = 0; $i < count( $matches[ 0 ] ); $i++ ) {        // number of tokens (full pattern match)
            for( $j = 1; $j < 4; $j++ ) {                         // each subpattern
                if( null != trim( $matches[ $j ][ $i ]) ) {       // pattern match in this subpattern
                    $tokens[ $i ] = trim( $matches[ $j ][ $i ] ); // this is the token
                }
            }
        }
        return $tokens;
    }
    
    /**
     * pushes an error to the error stack
     *
     * @access private
     * @param object Net_LDAP_Error $err
     * @return boolean 
     */
    function _pushError( $err ) 
    {
        return array_push( $this->_errors, $err );
    }

    /**
     * has an error occured?
     *
     * @access public
     * @return boolean 
     */
    function hasError() 
    {
        return ( count( $this->_errors ) > 0 );
    }
    
    /**
     * return the last error
     *
     * @access public
     * @return mixed Net_LDAP_Error or false
     */
    function &getError()
    {
        return array_pop( $this->_errors );
    }
 }
 
?>