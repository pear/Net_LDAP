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
// | Authors: Tarjej Huse                                                 |
// +----------------------------------------------------------------------+
//
// $Id$

/**
 * Result set of an LDAP search
 *
 * @author  Tarjei Huse
 * @version $Revision$
 * @package Net_LDAP
 */
class Net_LDAP_Search extends PEAR
{
    /**
     * Search result identifier
     *
     * @access private
     * @var resource
     */
    var $_search;
    
    /**
     * LDAP resource link
     *
     * @access private
     * @var resource
     */
    var $_link;

    /**
     * Array of entries
     *
     * @access private
     * @var array
     */
    var $_entries = array();
    
    /**
     * Result entry identifier
     *
     * @access private
     * @var resource
     */
    var $_elink = null;

    /**
     * The errorcode the search got
     * 
     * Some errorcodes might be of interest, but might not be best handled as errors. 
     * examples: 4 - LDAP_SIZELIMIT_EXCEEDED - indecates a huge search.
     *               Incomplete results are returned. If you just want to check if there's anything in the search.
     *               than this is a point to handle.
     *           32 - no such object - search here returns a count of 0.
     *          
     * @access private
     * @var int
     */
    var $_errorCode = 0; // if not set - sucess!
    
   /** 
    * Constructor
    *
    * @access protected
    * @param resource Search result identifier
    * @param resource Link identifier
    */
    function Net_LDAP_Search (&$search, &$link)
    {    
        $this->_setSearch($search, $link);
        $this->_errorCode = ldap_errno($link);
    }

    /**
     * Returns an assosiative array of entry objects
     *
     * @return array Array of entry objects.
     */
    function entries()
    {
        if ($this->count() == 0) {
            return array();
        }
        
        $this->_elink = @ldap_first_entry( $this->_link,$this->_search);
        $entry = new Net_LDAP_Entry(&$this->_link,    
                                  @ldap_get_dn($this->_link, $this->_elink),
                                  @ldap_get_attributes($this->_link, $this->_elink));
        array_push ( $entry);

        while ($this->_elink = @ldap_next_entry($this->_link,$this->_elink)) {
            $entry = new Net_ldap_entry(&$this->_link,
                                        ldap_get_dn($this->_link, $this->_elink),
                                        ldap_get_attributes($this->_link, $this->_elink));
            array_push ($entry);
        }
    }
   
    /**
     * Get the next entry in the searchresult.
     *
     * @return mixed Net_LDAP_Entry object or false
     */
    function shiftEntry()
    {
        if ($this->count() == 0 ) {
            return false;
        }

        if (is_null($this->_elink)) {
            $this->_elink = @ldap_first_entry($this->_link, $this->_search);
            $entry = new Net_ldap_entry(&$this->_link,
        	                            ldap_get_dn($this->_link, $this->_elink),
                	                    ldap_get_attributes($this->_link, $this->_elink));
        } else {
            if (!$this->_elink = ldap_next_entry($this->_link, $this->_elink)) {
                return false;
            }
    	    $entry = new Net_ldap_entry(&$this->_link,
    		                            ldap_get_dn($this->_link,$this->_elink),
            	                        ldap_get_attributes($this->_link,$this->_elink));
        }
        return $entry;
    }
    
    /**
     * alias function of shiftEntry() for perl-ldap interface
     * 
     * @see shiftEntry()
     */
    function shift_entry() 
    {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'shiftEntry' ), $args);
    }
   
    /**
     * Retrieve the last entry of the searchset. NOT IMPLEMENTED
     *
     * @return object Net_LDAP_Error
     */
    function pop_entry () 
    {
        $this->raiseError("Not implemented");
    }
    
    /**
     * Return entries sorted NOT IMPLEMENTED
     *
     * @param array Array of sort attributes
     * @return object Net_LDAP_Error
     */
    function sorted ($attrs = array()) 
    {
        $this->raiseError("Not impelented");
    }
    
   /** 
    * Return entries as object NOT IMPLEMENTED
    *
    * @return object Net_LDAP_Error
    */
    function as_struct ()
    {
        $this->raiseError("Not implemented");
    }

   /**
    * Set the searchobjects resourcelinks
    *
    * @access private
    * @param resource Search result identifier
    * @param resource Resource link identifier
    */
    function _setSearch(&$search,&$link)
    {      
        $this->_search = $search;
        $this->_link   = $link;
    }
   
   /**
    * Returns the number of entries in the searchresult
    *
    * @return int Number of entries in search.
    */
    function count()
    {
        /* this catches the situation where OL returned errno 32 = no such object! */
        if (!$this->_search) {
            return 0;
        }
        return @ldap_count_entries($this->_link, $this->_search);
    }

    /**
     * Get the errorcode the object got in its search.
     *
     * @return int The ldap error number.
     */
    function getErrorCode()
    {
        return $this->_errorCode;
    }
    
   /** Destructor 
    *
    * @access protected
    */
    function _Net_LDAP_Search() 
    {
        @ldap_free_result($this->_search);
    }
    
   /** 
    * Closes search result
    */
    function done()
    {
        $this->_Net_LDAP_Search();
    }
}

?>
