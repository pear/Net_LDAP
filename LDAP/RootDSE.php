<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Jan Wagner <wagner@netsols.de>                              |
// +----------------------------------------------------------------------+
//
// $Id$

/**
 * Getting the rootDSE entry of a LDAP server
 *
 * @package Net_LDAP
 * @author Jan Wagner <wagner@netsols.de>
 * @version $Revision$
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
    function Net_LDAP_RootDSE(&$entry)
    {
        $this->_entry = $entry;      
    }

    /**
     * Gets the requested attribute value
     *
     * Same usuage as Net_LDAP_Entry::get_value()
     *
     * @access public
     * @param string Attribute name
     * @param array Array of options
     * @return mixed Net_LDAP_Error object or attribute values
     * @see Net_LDAP_Entry::get_value()
     */    
    function getValue($attr = '', $options = '')
    {
        return $this->_entry->get_value($attr, $options);
    }

    /**
     * alias function of getValue() for perl-ldap interface
     * 
     * @see getValue()
     */
     function get_value() 
     {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'getValue' ), $args);
     }
    
    /**
     * Determines if the extension is supported
     * 
     * @access public
     * @param array Array of oids to check
     * @return boolean
     */
    function supportedExtension($oids) 
    {        
        return $this->_checkAttr($oids, 'supportedExtension');
    }
    
    /**
     * alias function of supportedExtension() for perl-ldap interface
     * 
     * @see supportedExtension()
     */
     function supported_extension() 
     {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'supportedExtension'), $args);
     }
    
    /**
     * Determines if the version is supported
     *
     * @access public
     * @param array Versions to check
     * @return boolean
     */
    function supportedVersion($versions) 
    {
        return $this->_checkAttr($versions, 'supportedLDAPVersion');
    }

    /**
     * alias function of supportedVersion() for perl-ldap interface
     * 
     * @see supportedVersion()
     */
     function supported_version() 
     {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedVersion'), $args);
     }    

     /**
     * Determines if the control is supported
     *
     * @access public
     * @param array Control oids to check
     * @return boolean
     */
    function supportedControl($oids)
    {
        return $this->_checkAttr($oids, 'supportedControl');
    }
    
    /**
     * alias function of supportedControl() for perl-ldap interface
     * 
     * @see supportedControl()
     */
     function supported_control() 
     {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedControl' ), $args);
     }        
    
    /**
     * Determines if the sasl mechanism is supported
     *
     * @access public
     * @param array SASL mechanisms to check
     * @return boolean
     */
    function supportedSASLMechanism($mechlist)
    {
        return $this->_checkAttr($mechlist, 'supportedSASLMechanisms');
    }

    /**
     * alias function of supportedSASLMechanism() for perl-ldap interface
     * 
     * @see supportedSASLMechanism()
     */
     function supported_sasl_mechanism() 
     {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedSASLMechanism'), $args);
     }    
    
     /**
     * Checks for existance of value in attribute
     *
     * @access private
     * @param array $values values to check
     * @param attr $attr attribute name
     * @return boolean
     */
    function _checkAttr($values, $attr)
    {
        if (!is_array($values)) $values = array($values);
     
        foreach ($values as $value) {
            if (!@in_array($value, $this->get_value($attr))) {
                return false;
            }
        }        
        return true;   
    }
}

?>