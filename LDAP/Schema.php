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
    var $_entry;
    var $_errors = array();
    
    function Net_LDAP_Schema( &$entry )
    {
        $this->_entry = $entry;
    }

    function &get( &$ldap, $dn = null )
    {
        if( false == is_null( $dn ) ) 
        {
            $base = $dn;
        } 
        else 
        {
            // get the subschema entry via root dse
            $dse = $ldap->rootDSE( array( 'subschemaSubentry' ) );
            if( Net_Ldap::isError( $dse ) ) return $dse;

            $base = $dse->getValue( 'subschemaSubentry', 'single' );
            if( Net_Ldap::isError( $base ) ) return $base;
        }

        // attributes to fetch from subschema entry
        $attrs = array( 'objectClasses',
                        'matchingRules', 
                        'matchingRuleUse', 
                        'ldapSyntaxes', 
                        'attributeTypes',
                        'dITContentRules',
                        'dITStructureRules',
                        'nameForms' );
        
        $result = $ldap->search( $base, '(objectClass=*)', array( 'attributes' => $attrs, 'scope' => 'base' ));
        if( Net_Ldap::isError( $result ) ) return $result;
        
        $entry = $result->shift_entry();
        if ( Net_Ldap::isError( $entry ) ) return $entry;

        return new Net_LDAP_Schema( $entry );
    }
    
    function allAttributes()
    {
        return $this->_entry->get_value( 'attributeTypes' );
    }
 }
 
?>