<?PHP

require_once( 'PEAR.php' );
require_once( 'Net/LDAP.php' );

/**
 * Net_LDAP_RootDSE: getting the rootDSE entry of a LDAP server
 *
 * @package Net_LDAP
 * @author Jan Wagner <wagner@netsols.de>
 * @version $Id$
 */
class Net_LDAP_RootDSE extends PEAR
{
    /**
     * @access private
     * @var object Net_LDAP_Entry
     **/
    var $_entry;
    
    /**
     * class constructor
     *
     * @param object Net_LDAP_Entry
     */
    function Net_LDAP_RootDSE( &$entry )
    {
        $this->_entry = $entry;      
    }

    /**
     * Gets the requested attribute value
     *
     * Same usuage as Net_LDAP_Entry::get_value()
     *
     * @access public
     * @param string $attr Attribute name
     * @param array $options Array of options
     * @return mixed Ldap_Error object or attribute values
     * @see Net_LDAP_Entry::get_value()
     */    
    function getValue( $attr = '', $options = '' ) 
    {
        return $this->_entry->get_value( $attr, $options );
    }

    /**
     * alias function of getValue() for perl-ldap interface
     * 
     * @see getValue()
     */
     function get_value() 
     {
        $args = func_get_args();
        return call_user_func_array( array( &$this, 'getValue' ), $args );         
     }    
    
    /**
     * Determines if the extension is supported
     * 
     * @access public
     * @param array $oids array of oids to check
     * @return boolean
     */
    function supportedExtension ( $oids ) 
    {        
        return $this->_check_attr( $oids, 'supportedExtension' );
    }
    
    /**
     * alias function of supportedExtension() for perl-ldap interface
     * 
     * @see supportedExtension()
     */
     function supported_extension() 
     {
        $args = func_get_args();
        return call_user_func_array( array( &$this, 'supportedExtension' ), $args );         
     }
    
    /**
     * Determines if the version is supported
     *
     * @access public
     * @param array $versions versions to check
     * @return boolean
     */
    function supportedVersion ( $versions ) 
    {
        return $this->_check_attr( $versions, 'supportedLDAPVersion' );
    }

    /**
     * alias function of supportedVersion() for perl-ldap interface
     * 
     * @see supportedVersion()
     */
     function supported_version() 
     {
        $args = func_get_args();
        return call_user_func_array( array( &$this, 'supportedVersion' ), $args );         
     }    

     /**
     * Determines if the control is supported
     *
     * @access public
     * @param array $oids control oids to check
     * @return boolean
     */
    function supportedControl ( $oids ) 
    {
        return $this->_check_attr( $oids, 'supportedControl' );
    }
    
    /**
     * alias function of supportedControl() for perl-ldap interface
     * 
     * @see supportedControl()
     */
     function supported_control() 
     {
        $args = func_get_args();
        return call_user_func_array( array( &$this, 'supportedControl' ), $args );
     }        
    
    /**
     * Determines if the sasl mechanism is supported
     *
     * @access public
     * @param array $mechlist sasl mechanisms to check
     * @return boolean
     */
    function supportedSASLMechanism ( $mechlist )
    {
        return $this->_check_attr( $mechlist, 'supportedSASLMechanisms' );
    }

    /**
     * alias function of supportedSASLMechanism() for perl-ldap interface
     * 
     * @see supportedSASLMechanism()
     */
     function supported_sasl_mechanism() 
     {
        $args = func_get_args();
        return call_user_func_array( array( &$this, 'supportedSASLMechanism' ), $args );
     }    
    
     /**
     * Checks for existance of value in attribute
     *
     * @access private
     * @param array $values values to check
     * @param attr $attr attribute name
     * @return boolean
     */
    function _check_attr( $values, $attr ) 
    {
        if( !is_array( $values ) ) $values = array( $values );
     
        foreach( $values as $value ) {
            if( !@in_array( $value, $this->get_value( $attr ) ) ) return false;
        }
        
        return true;   
    }
}

?>