<?php


/*
 */

class Net_Ldap_Search extends PEAR
{


    var $_search; // search result identifier
    var $_link; // ldap_resource_link

    var $_entries = array(); // array of the entries.
    var $_elink = null; // internal pointer to the result_entry_identifier

    /* _errorCode - the errorcode the search got. Some errorcodes might be of interest, but might not be best handled as errors. 
     * examples:4 - LDAP_SIZELIMIT_EXCEEDED - indecates a huge search. Incomplete results are returned. If you just want to check if there's anything in the search.
     *              than this is a point to handle.
     *          32 - no such object - search here returns a count of 0.
     *          
     *            - */
    var $_errorCode = 0; // if not set - sucess!
    
   /** Net_Ldap_Search() - constructor
    *
    * @params &$search - pointer to ldap search result, $this->_link pointer to the ldaplink.
    *
    * */

    function Net_Ldap_Search (&$search,$link) {
    // set important variables.
        $this->_set_search(&$search,$link);
        $this->_errorCode = ldap_errno($link);
    }

   /** Entries() -  returns an assosiative array of entry objects
    *
    * Retrieves an array of entryobjects.
    * @params none
    * @return array of entry objects.
   */
    function entries()
    {
        if ($this->count() == 0) return array();

        $this->_elink = ldap_first_entry( $this->_link,$this->_search);
        $entry = new Net_Ldap_Entry(&$this->_link,ldap_get_dn($this->_link,$this->_elink),ldap_get_attributes($this->_link,$this->_elink));
        array_push ( $entry);

        while ($this->_elink = ldap_next_entry($this->_link,$this->_elink)) {
            $entry = new Net_ldap_entry(&$this->_link,
                                    ldap_get_dn($this->_link,$this->_elink),
                                    ldap_get_attributes($this->_link,$this->_elink));
            array_push ($entry);
        }
    }
   
   /*shift_entry - get the next enty in the searchresult.
    *
    * @params none
    * @return Net_Ldap_Entry object.
    *
    */
    function shift_entry ()
    {
        if ($this->count() == 0 ) return false;

        if (is_null($this->_elink)) {
            $this->_elink = ldap_first_entry($this -> _link, $this -> _search);
            $entry = new Net_ldap_entry(&$this -> _link,
        	                            ldap_get_dn($this->_link, $this -> _elink),
                	                    ldap_get_attributes($this -> _link, $this -> _elink));
        } else {
            if (!$this->_elink = ldap_next_entry($this -> _link, $this -> _elink)) {
                return false;
            }
    	    $entry = new Net_ldap_entry(&$this->_link,
    		                            ldap_get_dn($this->_link,$this->_elink),
            	                        ldap_get_attributes($this->_link,$this->_elink));
        }
        return $entry;
    }
   
   /*pop_entry - retrieve the last entry of the searchset. NOT IMPLEMENTED
    * 
    * */
    function pop_entry () 
    {
        $this->raiseError("Not implemented");
    }
    
    /** sorted - NOT IMPLEMENTED
    *
    * @param - array of sort attributes
    * */
    function sorted ($attrs = array()) 
    {
        $this->raiseError("Not impelented");
    }
    
   /** as_atruct() NOT IMPLEMENTED
    *
    * */
    function as_struct ()
    {
        $this->raiseError("Not implemented");
    }

   /** _set_search () - set the searchobjects resourcelinks
    *
    * @param $search -  resource result_identifier, $link - resource link_identifier
    * */
    function _set_search (&$search,&$link)
    {
      
        $this->_search = $search;
        $this->_link = $link;

    
    }
   
   /** count() -  returns the number of entries in the searchresult
    *
    * @params none 
    * @return integer - number of entries in search.
   */
    function count ()
    {
        /* this catches the situation where OL returned errno 32 = no such object! */
        if (!$this->_search) {
            return 0;
        }
        return ldap_count_entries ($this->_link,$this->_search);
    }

    /* getErrorCode () - get the errorcode the object got in its search.
     * @public
     * @params none
     * @returns integrer - the ldap_error_no.
     * 
     * */
    function getErrorCode () {
        return $this->_errorCode;
    } 
   /** _Net_Ldap_Search - destructor - closes searchresult.
    * @param - none
    * @return - none
    * */
    function _Net_Ldap_search() 
    {
        ldap_free_result($this->_search);
    }
   /** done - destructor
    *
    * @param - none
    * @return - none
    * */
    function done()
    {
        $this->_Net_Ldap_Search();
    }


}
?>
