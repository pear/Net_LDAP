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
// +--------------------------------------------------------------------------+
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
     * Net_LDAP object
     *
     * A reference of the Net_LDAP object for passing to Net_LDAP_Entry
     *
     * @access private
     * @var object Net_LDAP
     */
    var $_ldap;
    
    /**
     * Result entry identifier
     *
     * @access private
     * @var resource
     */
    var $_entry = null;

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
    function Net_LDAP_Search (&$search, &$ldap)
    {
        $this->PEAR('Net_LDAP_Error');
        
        $this->setSearch($search);
        
        if (is_a($ldap, 'Net_LDAP')) {
            $this->_ldap = $ldap;
            $this->setLink($this->_ldap->getLink());
        } else {
            $this->setLink($ldap);        
        }
        
        $this->_errorCode = @ldap_errno($link);
    }

    /**
     * Returns an assosiative array of entry objects
     *
     * @return array Array of entry objects.
     */
    function entries()
    {
        $entries = array();
        
        while ($entry = $this->shiftEntry()) {
            $entries[] = $entry;
        }
        
        return $entries;
    }
   
    /**
     * Get the next entry in the searchresult.
     *
     * @return mixed Net_LDAP_Entry object or false
     */
    function &shiftEntry()
    {
        if ($this->count() == 0 ) {
            return false;
        }

        if (is_null($this->_entry)) {
            $this->_entry = @ldap_first_entry($this->_link, $this->_search);
            $entry = new Net_LDAP_Entry(&$this->_entry, &$this->_ldap);
        } else {
            if (!$this->_entry = @ldap_next_entry($this->_link, $this->_entry)) {
                return false;
            }
    	    $entry = new Net_LDAP_Entry(&$this->_entry, &$this->_ldap);
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
        PEAR::raiseError("Not implemented");
    }
    
    /**
     * Return entries sorted NOT IMPLEMENTED
     *
     * @param array Array of sort attributes
     * @return object Net_LDAP_Error
     */
    function sorted ($attrs = array()) 
    {
        PEAR::raiseError("Not implemented");
    }
    
   /** 
    * Return entries as object NOT IMPLEMENTED
    *
    * @return object Net_LDAP_Error
    */
    function as_struct ()
    {
        PEAR::raiseError("Not implemented");
    }

   /**
    * Set the search objects resource link
    *
    * @access public
    * @param resource Search result identifier
    * @return void
    */
    function setSearch(&$search)
    {      
        $this->_search = $search;
    }

   /**
    * Set the ldap ressource link
    *
    * @access public
    * @param resource Link identifier
    * @return void
    */
    function setLink(&$link)
    {      
        $this->_link = $link;
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
