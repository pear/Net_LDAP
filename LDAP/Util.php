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
 * Utility Class for Net_LDAP
 *
 * @package Net_LDAP
 * @author Jan Wagner <wagner@netsols.de>
 * @version $Revision$
 */
class Net_LDAP_Util extends PEAR 
{
    /**
     * Reference to LDAP object
     *
     * @access private
     * @var object Net_LDAP
     */
    var $_ldap = null;
    
    /**
     * Net_LDAP_Schema object
     *
     * @access private
     * @var object Net_LDAP_Schema
     */
    var $_schema = null;
    
    /**
     * Constructur
     *
     * Takes an LDAP object by reference and saves it. Then the schema will be fetched.
     *
     * @access public
     * @param object Net_LDAP
     */
    function Net_LDAP_Util(&$ldap)
    {
        if (is_object($ldap) && (get_class($ldap) == 'net_ldap')) {
            $this->_ldap = $ldap;
            $this->_schema = $this->_ldap->schema();
            if (Net_LDAP::isError($this->_schema)) $this->_schema = null;
        }
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
        if (!$this->_ldap || !$this->_schema || !function_exists($function)) {
            return $attributes;
        }
        if (is_array($attributes) && count($attributes) > 0) {
            foreach( $attributes as $k => $v ) {
                $attr = $this->_schema->get('attribute', $k);
                if (Net_LDAP::isError($attr)) {
                    continue;
                }                
                if (false !== strpos($attr['syntax'], '1.3.6.1.4.1.1466.115.121.1.15')) {                    
                    if (is_array($v)) {
                        foreach ($v as $ak => $av ) {
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
}

?>
