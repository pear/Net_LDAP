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
     * examples: 4 - LDAP_SIZELIMIT_EXCEEDED - indicates a huge search.
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
    * @param resource $search         Search result identifier
    * @param Net_LDAP|resource $ldap  Net_LDAP object or just a LDAP-Link resource
    */
    function Net_LDAP_Search(&$search, &$ldap)
    {
        $this->PEAR('Net_LDAP_Error');

        $this->setSearch($search);

        if (is_a($ldap, 'Net_LDAP')) {
            $this->_ldap =& $ldap;
            $this->setLink($this->_ldap->getLink());
        } else {
            $this->setLink($ldap);
        }

        $this->_errorCode = @ldap_errno($this->_link);
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
     * @return Net_LDAP_Entry|false  Reference to Net_LDAP_Entry object or false
     */
    function &shiftEntry()
    {
        if ($this->count() == 0 ) {
            return false;
        }

        if (is_null($this->_entry)) {
            $this->_entry = @ldap_first_entry($this->_link, $this->_search);
            $entry = new Net_LDAP_Entry($this->_ldap, $this->_entry);
        } else {
            if (!$this->_entry = @ldap_next_entry($this->_link, $this->_entry)) {
                return false;
            }
            $entry = new Net_LDAP_Entry($this->_ldap, $this->_entry);
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
     * @return Net_LDAP_Error
     * @todo implement me!
     */
    function pop_entry()
    {
        PEAR::raiseError("Not implemented");
    }

    /**
     * Return entries sorted
     *
     * [BUG] there is a problem with multivalued attributes. If sorting by such an attribute, only the first
     *       attribute is used for the compare, not the highest/lowest one in the list. This results in uncorrect sorting.
     *       The solution to this is to fetch the attributes here and sort them by hand or to wait for the
     *       php-developers to fix this inside ldap_sort().
     *
     * @param array $attrs Array of sort attributes, order from left to right
     * @param bool  $order if set to true, the sort will be decreasing
     * @return mixed Array of sorted entries
     * @todo Add multivalue sort support (FR #8346)
     */
    function sorted($attrs = array(), $order = false)
    {
        $attrs = array_reverse($attrs);
        foreach ($attrs as $attribute) {
            if (!ldap_sort($this->_link, $this->_search, $attribute)){
                $this->raiseError("Sorting failed for Attribute " . $attribute);
            }
        }

        $results = ldap_get_entries($this->_link, $this->_search);

        unset($results['count']); //for tidier output
        if ($order) {
            return array_reverse($results);
        } else {
            return $results;
        }
    }

   /**
    * Return entries as array
    *
    * This method returns the entries and the selected attributes values as
    * array.
    * The first array level contains all found entries where the keys are the
    * DNs of the entries. The second level arrays contian the entries attributes
    * such that the keys is the lowercased name of the attribute and the values
    * are stored in another indexed array. Note that the attribute values are stored
    * in an array even if there is no or just one value.
    *
    * The array has the following structure:
    * <code>
    * $return = array(
    *           'cn=foo,dc=example,dc=com' => array(
    *                                                'sn'       => array('foo'),
    *                                                'multival' => array('val1', 'val2', 'valN')
    *                                             )
    *           'cn=bar,dc=example,dc=com' => array(
    *                                                'sn'       => array('bar'),
    *                                                'multival' => array('val1', 'valN')
    *                                             )
    *           )
    * </code>
    *
    * @return array      associative result array as described above
    */
    function as_struct()
    {
        $return = array();
        $entries = $this->entries();
        foreach ($entries as $entry) {
        	$attrs = array();
        	$entry_attributes = $entry->attributes();
        	foreach ($entry_attributes as $attr_name) {
        		$attr_values = $entry->getValue($attr_name, 'all');
        		if (!is_array($attr_values)) {
        			$attr_values = array($attr_values);
        		}
        		$attrs[$attr_name] = $attr_values;
        	}
            $return[$entry->dn()] = $attrs;
        }
        return $return;
    }

   /**
    * Set the search objects resource link
    *
    * @access public
    * @param resource $search Search result identifier
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
    * @param resource $link Link identifier
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

   /**
    * Destructor
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
