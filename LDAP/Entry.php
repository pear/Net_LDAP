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
// | Authors: Jan Wagner                                                     |
// +--------------------------------------------------------------------------+
//
// $Id$

require_once("PEAR.php");

/**
 * Object representation of a directory entry
 *
 * This class represents a directory entry. You can add, delete, replace
 * attributes and their values, rename the entry, delete the entry.
 *
 * @package Net_LDAP
 * @author Jan Wagner <wagner@netsols.de>
 * @author Tarjej Huse
 * @version $Revision$
 */

class Net_LDAP_Entry extends PEAR
{
    /**
     * Entry ressource identifier
     *
     * @access private
     * @var ressourcee
     */
    var $_entry = null;

    /**
     * LDAP ressource identifier
     *
     * @access private
     * @var ressource
     */
    var $_link = null;

    /**
     * Net_LDAP object
     *
     * This object will be used for updating and schema checking
     *
     * @access private
     * @var object Net_LDAP
     */
    var $_ldap = null;

    /**
     * Distinguished name of the entry
     *
     * @access private
     * @var string
     */
    var $_dn = null;

    /**
     * Attributes
     *
     * @access private
     * @var array
     */
    var $_attributes = array();

    /**
     * Original attributes before any modification
     *
     * @access private
     * @var array
     */
    var $_original = array();


    /**
     * Map of attribute names
     *
     * @access private
     * @var array
     */
    var $_map = array();


    /**
     * Is this a new entry?
     *
     * @access private
     * @var boolean
     */
    var $_new = true;

    /**
     * New distinguished name
     *
     * @access private
     * @var string
     */
    var $_newdn = null;

    /**
     * Shall the entry be deleted?
     *
     * @access private
     * @var boolean
     */
    var $_delete = false;

    /**
     * Map with changes to the entry
     *
     * @access private
     * @var array
     */
    var $_changes = array("add"     => array(),
                          "delete"  => array(),
                          "replace" => array()
                          );
    /**
     * Constructor
     *
     * Constructor of the entry. Sets up the distinguished name and the entries
     * attributes. If no attributes are given, it is assumed to be a new entry.
     *
     * @access protected
     * @param $dn string
     * @param $attributes array
     * @return none
     */
    function Net_LDAP_Entry($entry = null, &$ldap)
    {
        $this->PEAR('Net_LDAP_Error');

        if (is_resource($entry)) {
            $this->_entry = &$entry;
        } else {
            $this->_dn = $entry;
        }

        if (is_a($ldap, 'Net_LDAP')) {
            $this->_ldap = &$ldap;
            $this->_link = $ldap->getLink();
        } elseif (is_resource($ldap)) {
            $this->_link = $ldap;
        } elseif (is_array($ldap)) {
            $this->setAttributes($ldap);
        }

        if (is_resource($this->_entry) && is_resource($this->_link)) {
            $this->_new = false;
            $this->_dn  = @ldap_get_dn($this->_link, $this->_entry);
            $this->setAttributes();
        }
    }

    /**
     * Get or set the distinguished name of the entry
     *
     * If called without an argument the current dn gets returned, else the
     * current value gets returned
     *
     * @access public
     * @param $dn string New distinguished name
     * @return string Disinguished name
     */
    function dn($dn = null)
    {
        if (false == is_null($dn)) {
            if (is_null($this->_dn)) {
                $this->_dn = $dn;
            } else {
                $this->_newdn = $dn;
            }
            return true;
        }
        return (isset($this->_newdn) ? $this->_newdn : $this->_dn);
    }

    /**
     * Sets the internal attributes array
     *
     * @access private
     * @param $attributes array
     */
    function setAttributes($attributes = null)
    {
        // fetch attributes from the server
        if (is_null($attributes) && is_resource($this->_entry) && is_resource($this->_link))
        {
            // fetch schema
            if (is_a($this->_ldap, 'Net_LDAP')) {
                $schema = $this->_ldap->schema();
            }
            // fetch attributes
            $attributes = array();
            do {
                if (empty($attr)) {
                    $ber = null;
                    $attr = @ldap_first_attribute($this->_link, $this->_entry, $ber);
                } else {
                    $attr = @ldap_next_attribute($this->_link, $this->_entry, $ber);
                }
                if ($attr) {
                    $func = 'ldap_get_values'; // function to fetch value
                    if (is_a($schema, 'Net_LDAP_Schema')) {
                        // try to get binary values as binary data
                        $attr_s = $schema->get('attribute', $attr);
                        if (false === Net_LDAP::isError($attr_s)) {
                            if (false !== strpos($attr_s['syntax'], NET_LDAP_SYNTAX_OCTET_STRING)) {
                                $func = 'ldap_get_values_len';
                            }
                        }
                    }
                    // fetch attribute value (needs error checking?)
                    $attributes[$attr] = $func($this->_link, $this->_entry, $attr);
                }
            } while ($attr);
        }

        if (is_array($attributes) && count($attributes) > 0) {
            if (isset($attributes["count"]) && is_numeric($attributes["count"])) {
                unset($attributes["count"]);
            }
            foreach ($attributes as $k => $v) {
                // attribute names should not be numeric
                if (is_numeric($k)) {
                    continue;
                }
                // map generic attribute name to real one
                $this->_map[strtolower($k)] = $k;
                // attribute values should be in an array
                if (false == is_array($v)) {
                    $v = array($v);
                }
                // remove the value count (comes from ldap server)
                if (isset($v["count"])) {
                    unset($v["count"]);
                }
                $this->_attributes[$k] = $v;
            }
        }
        // save a copy for later use
        $this->_original = $this->_attributes;
    }
    /**
     * Get the values of all attributes in a hash
     *
     * The returned hash has the form
     * array('attributename' => 'single value',
     *       'attributename' => array('value1', value2', value3'))
     *
     * @access public
     * @return array Hash of all attributes with their values
     */
    function getValues()
    {
        $attrs = array();
        foreach ($this->_attributes as $attr => $value) {
            $attrs[$attr] = $this->getValue($attr);
        }
        return $attrs;
    }

    /**
     * Get the value of a specific attribute
     *
     * The first parameter is the name of the attribute
     * The second parameter influences the way the value is returned:
     * 'single': only the first value is returned as string
     * 'all': all values including the value count are returned in an
     *               array
     * 'default': in all other cases an attribute value with a single value is
     *            returned a string, if it has multiple values it is returned
     *            as an array (without value count)
     *
     * @access public
     * @param string $attr Attribute name
     * @param string $option Option
     * @return mixed string, array or PEAR_Error
     */
    function getValue($attr, $option = null)
    {
        $attr = $this->_getAttrName($attr);

        if (false == array_key_exists($attr, $this->_attributes)) {
            return PEAR::raiseError("Unknown attribute requested");
        }

        $value = $this->_attributes[$attr];

        if ($option == "single" || (count($value) == 1 && $option != 'all')) {
            $value = array_shift($value);
        }

        return $value;
    }

    /**
     * Alias function of getValue for perl-ldap interface
     *
     * @see getValue()
     */
    function get_value()
    {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'getValue' ), $args);
    }

    /**
     * Returns an array of attributes names
     *
     * @access public
     * @return array Array of attribute names
     */
    function attributes()
    {
        return array_keys($this->_attributes);
    }

    /**
     * Returns whether an attribute exists or not
     *
     * @access public
     * @param string Attribute name
     * @return boolean
     */
    function exists($attr)
    {
        $attr = $this->_getAttrName($attr);
        return array_key_exists($attr, $this->_attributes);
    }

    /**
     * Adds a new attribute or a new value to an existing attribute
     *
     * The paramter has to be an array of the form:
     * array('attributename' => 'single value',
     *       'attributename' => array('value1', 'value2))
     * When the attribute already exists the values will be added, else the
     * attribute will be created. These changes are local to the entry and do
     * not affect the entry on the server until update() is called.
     *
     * @access public
     * @param $attr array
     */
    function add($attr = array())
    {
        if (false == is_array($attr)) {
            return PEAR::raiseError("Parameter must be an array");
        }
        foreach ($attr as $k => $v) {
            $k = $this->_getAttrName($k);
            if (false == is_array($v)) {
                // Do not add empty values
                if ($v == null) {
                    continue;
                } else {
                    $v = array($v);
                }
            }
            // add new values to existing attribute
            if ($this->exists($k)) {
                $this->_attributes[$k] = array_merge($this->_attributes[$k], $v);
            } else {
                $this->_map[strtolower($k)] = $k;
                $this->_attributes[$k] = $v;
            }
            // save changes for update()
            if (empty($this->_changes["add"][$k])) {
                $this->_changes["add"][$k] = array();
            }
            $this->_changes["add"][$k] = array_merge($this->_changes["add"][$k], $v);
        }
    }

    /**
     * Deletes an attribute or a value
     *
     * The parameter can be one of the following:
     *
     * "attributename" - The attribute as a whole will be deleted
     * array("attributename1", "attributename2) - All given attributes will be
     *                                            deleted
     * array("attributename" => "value") - The value will be deleted
     * array("attributename" => array("value1", "value2") - The given values
     *                                                      will be deleted
     *
     * @access public
     * @param mixed
     */
    function delete($attr = null)
    {
        if (is_null($attr)) {
            $this->_delete = true;
        }
        if (is_string($attr)) {
            $attr = array($attr);
        }
        // Make the assumption that attribute names cannot be numeric,
        // therefore this has to be a simple list of attribute names to delete
        if (is_numeric(key($attr))) {
            foreach ($attr as $name) {
                $name = $this->_getAttrName($name);
                if ($this->exists($name)) {
                    $this->_changes["delete"][$name] = null;
                    unset($this->_attributes[$name]);
                }
            }
        } else {
            // Here we have a hash with "attributename"" => "value to delete"
            foreach ($attr as $name => $values) {
                // get the correct attribute name
                $name = $this->_getAttrName($name);
                if ($this->exists($name)) {
                    if (false == is_array($values)) {
                        $values = array($values);
                    }
                    // save values to be deleted
                    if (empty($this->_changes["delete"][$name])) {
                        $this->_changes["delete"][$name] = array();
                    }
                    $this->_changes["delete"][$name] =
                        array_merge($this->_changes["delete"][$name], $values);
                    foreach ($values as $value) {
                        // find the key for the value that should be deleted
                        $key = array_search($value, $this->_attributes[$name]);
                        if (false !== $key) {
                            // delete the value
                            unset($this->_attributes[$name][$key]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Replaces attributes or its values
     *
     * The parameter has to an array of the following form:
     * array("attributename" => "single value",
     *       "attribute2name" => array("value1", "value2"))
     * If the attribute does not yet exist it will be added instead.
     * If the attribue value is null, the attribute will de deleted
     *
     * @access public
     * @param array
     */
    function replace($attr = array())
    {
        if (false == is_array($attr)) {
            return PEAR::raiseError("Parameter must be an array");
        }
        foreach ($attr as $k => $v) {
            $k = $this->_getAttrName($k);
            if (false == is_array($v)) {
                // delete attributes with empty values
                if ($v == null) {
                    $this->delete($k);
                    continue;
                } else {
                    $v = array($v);
                }
            }
            // existing attributes will get replaced
            if ($this->exists($k)) {
                $this->_changes["replace"][$k] = $v;
                $this->_attributes[$k] = $v;
            } else {
                // new ones just get added
                $this->add(array($k => $v));
            }
        }
    }

    /**
     * Update the entry on the directory server
     *
     * @access public
     * @param object Net_LDAP, optional. If you provide a object, be sure to PASS IT VIA REFERENCE!
     * @return mixed
     */
    function update($ldap=false)
    {
        if (!$ldap) {  // If object is not provided, then use this entrys ldap object
            $ldap =& $this->_ldap;
        } else {
            if (!is_a($ldap, 'Net_LDAP')) {
                return PEAR::raiseError("Need a Net_LDAP object as parameter");
            }
        }

        $link = $ldap->getLink();

        // Delete the entry
        if ($this->_delete === true) {
            return $ldap->delete($this);
        }
        // New entry
        if ($this->_new === true) {
            $msg = $ldap->add($this);
            if (Net_LDAP::isError($msg)) {
                return $msg;
            }
            $this->_new = false;
            $this->_changes['add'] = array();
            $this->_changes['delete'] = array();
            $this->_changes['replace'] = array();
            $this->_original = $this->_attributes;
            return;
        }
        // Rename/move entry
        if (false == is_null($this->_newdn)) {
            if ($ldap->getLDAPVersion() !== 3) {
                return PEAR::raiseError("Renaming/Moving an entry is only supported in LDAPv3");
            }
            // make dn relative to parent (needed for ldap rename)
            $parent = ldap_explode_dn($this->_newdn);
            if (isset($parent["count"])) {
                unset($parent["count"]);
            }
            $child = array_shift($parent);
            $parent = join(",", $parent);
            // rename
            if (false == @ldap_rename($link, $this->_dn, $child, $parent, true)) {
                return PEAR::raiseError("Entry not renamed: " .
                                        @ldap_error($link), @ldap_errno($link));
            }
            // reflect changes to local copy
            $this->_dn = $this->_newdn;
            $this->_newdn = null;
        }
        // Modified entry
        foreach ($this->_changes["add"] as $attr => $value) {
            // if attribute exists, add new values
            if ($this->exists($attr)) {
                if (false === @ldap_mod_add($link, $this->dn(), array($attr => $value))) {
                    return PEAR::raiseError("Could not add new values to attribute $attr: " .
                                            @ldap_error($link), @ldap_errno($link));
                }
            } else {
                // new attribute
                if (false === @ldap_modify($link, $this->dn(), array($attr => $value))) {
                    return PEAR::raiseError("Could not add new attribute $attr: " .
                                            @ldap_error($link), @ldap_errno($link));
                }
            }
            // all went well here, I guess
            unset($this->_changes["add"][$attr]);
        } // add

        foreach ($this->_changes["delete"] as $attr => $value) {
            // In LDAPv3 you need to specify the old values for deleting
            if (is_null($value) && $ldap->getLDAPVersion() === 3) {
                $value = $this->_original[$attr];
            }
            if (false === @ldap_mod_del($link, $this->dn(), array($attr => $value))) {
                return PEAR::raiseError("Could not delete attribute $attr: " .
                                        @ldap_error($link), @ldap_errno($link));
            }
            unset($this->_changes["delete"][$attr]);
        } // delete

        foreach ($this->_changes["replace"] as $attr => $value) {
            if (false === @ldap_modify($link, $this->dn(), array($attr => $value))) {
                return PEAR::raiseError("Could not replace attribute $attr values: " .
                                        @ldap_error($link), @ldap_errno($link));
            }
            unset($this->_changes["replace"][$attr]);
        } // replace

        // all went well, so _original (server) becomes _attributes (local copy)
        $this->_original = $this->_attributes;
    }

    /**
     * Returns the right attribute name
     *
     * @access private
     * @param string Name of attribute
     * @return string The right name of the attribute
     */
    function _getAttrName($attr)
    {
        $name = strtolower($attr);
        if (array_key_exists($name, $this->_map)) {
            $attr = $this->_map[$name];
        }
        return $attr;
    }

    /**
     * Copy the current entry to another place in the directory
     *
     * @access public
     * @param object Net_LDAP
     * @param string New distinguished name
     * @param boolean Is the new name relative to current parent
     * @return mixed Net_LDAP_Entry or Net_LDAP_Error
     */
    function &copy(&$ldap, $dn, $relative = false)
    {
        if ($relative == true) {
            $dn = "$dn," . $this->dn();
        }
        // get the attribute which makes up the rdn
        $parts = @ldap_explode_dn($this->dn(), 0);
        list($attr, $value) = explode('=', $parts[0]);

        // remove it from the entry (not valid in copy)
        $old_e = $this; // backup
        $old_e->delete(array($attr => $value));

        // get the attribute which makes up the new rdn
        $parts = @ldap_explode_dn($dn, 0);
        list($attr, $value) = explode('=', $parts[0]);

        $old_e->add(array($attr => $value));

        $entry = new Net_LDAP_Entry($dn);

        $entry->add($old_e->getValues());
        $msg = $entry->update($ldap);
        if (Net_LDAP::isError($msg)) {
            return $msg;
        }
        return $entry;
    }
    
    /**
     * Returns a reference to the LDAP-Object of this entry
     *
     * @access public
     * @return Net_LDAP|Net_LDAP_Error   Reference to the Net_LDAP Object (the connection) or Net_LDAP_Error
     */
    function &getLDAP()
    {
        if (!is_a($this->_ldap, 'Net_LDAP')) {
            return PEAR::raiseError("LDAP is not a valid Net_LDAP object");
        } else {
            return $this->_ldap;
        }
    }
}

?>