<?php
class Net_LDAP_Entry extends PEAR
{

    /**
     * Array of the attributes the user has
     * */
    var $_attrs = array(); // the attribute array
    var $_delAttrs = array(); // Attributes to be deleted upon ->update()
    var $_modAttrs = array(); // attributes to be modified upon ->update()
    var $_addAttrs = array(); // Attributes to be added upon ->update()
    var $_dn = ''; // The dn
    var $_link = null; // ldap resourcelink.
    
    var $_olddn = ''; // The users old DN if the dn has been changed

    /* used for debugging class */
    var $_error = array();

    // updatechecks
    var $updateCheck = array('newdn'    => false,
                             'modify'   => false,
                             'newEntry' => true
                             ); // since the entry is not changed before the update();
    /* _schema: Net::LDAP::Schema object. May be removed.  */
    var $_schema;
    /* _utfAttr
     * array to save ldapsearches. Will contain the most normal utized attributes.
     *
     * */
    var $_utfAttr = array (); 

    /* _nonUtfAttr
     * array to save ldapsearches. Will contain the most normal attributes that should not be utf8.
     * */
    var $_nonUtfAttr = array ('homedirectory');
    
    /** Constructor
     * @param - link - ldap_resource_link, dn = string entry dn, attributes - array entry attributes array.
     * @return - none
     **/
    function Net_Ldap_Entry ($link = null, $dn = null, $attributes = array())
    {

        if (!is_null($link)) {
            $this->_link = $link;
        }
        if (!is_null($dn)) {
            $this->_set_dn($dn);
        }
        if (count ($attributes)>0 ) {
            $this->_set_attributes($attributes);
        } else {
            $this->updateCheck['newEntry'] = true;
        }
        


    }
    /** Set the reasourcelink to the ldapserver.
     *
     * */
    function _set_link(&$link) 
    {
        $this->_link = $link;
    }
    /** _set_dn - set the entrys DN 
     * */
    function _set_dn ($dn)
    {
        $this->_dn = $dn;
    }

    /** _set_attributes - sets the internal array of the entrys attributes.
     * 
     * */
    function _set_attributes ($attributes= array())
    {
        $this->_attrs = $attributes;
        // this is the sign that the entry exists in the first place: 
        $this->updateCheck['newEntry'] = false;
    }

   /** clean_entry - removes [count] entries from the array.
    * 
   * remove all the count elements in the array:
   * Used before ldap_modify, ldap_add
   * 
   * @params - none
   * */
    function _clean_entry()
    {
        $attributes = array();
        
        for ($i=0; $i < $this->_attrs['count'] ; $i++) {
        
            $attr = $this->_attrs[$i];
        
            if ($this->_attrs[$attr]['count'] == 1) {
                $attributes[$this->_attrs[$i]] = $this->_attrs[$attr][0];
            } else {
                $attributes[$attr] = $this->_attrs[$attr];
                unset ($attributes[ $attr ]['count']);
            }
        }
         
        return $attributes;

    }

   /** attributes -  returns an assosiative array of all the attributes in the array
    *
    * attributes -  returns an assosiative array of all the attributes in the array
    * on the form array ('attributename'=>'singelvalue' , 'attribute'=>array('multiple','values'))
    * @param none
    * @return $array of attributes and values.
   */

    function attributes ()
    {
        return Net_LDAP::UTF8Decode($this->_clean_entry());
    }

   /** add -  Add one or more attribute to the entry
    *
    * The values given will be added to the values which already exist for the given attributes.
    * usage:
    * $entry->add ( array('sn'=>'huse',objectclass=>array(top,posixAccount)))
    * @param $array of attributes example: array('sn'=>'huse',objectclass=>array(top,posixAccount))
    * @return Net_Ldap_Error if error, else true.
    */

    function add ($attr = array())
    {

        if (!isset($this->_attrs['count']) ) {
            $this->_attrs['count'] = 0;
        }
        if (!is_array($attr)) {
            return $this->raiseError("Net_LDAP::add : the parameter supplied is not an array, $attr", 1000);   
        }
        /* if you passed an empty array, that is your problem! */
        if (count ($attr)==0) {
            return true;
        
        }
        foreach ($attr as $k => $v ) {
            // empty entrys should not be added to the entry.
            if ($v == '') continue;
            if ($this->exists($k)) {
                if (!is_array($this->_attrs[$k])) {
                    $this->raiseError("Possible malformed array as parameter to Net_LDAP::add().");
                }
                array_push($this->_attrs[$k],$v);
                $this->_attrs[$k]['count']++;

            } else {
                $this->_attrs[$k][0] = $v;
                $this->_attrs[$k]['count'] = 1;
                $this->_attrs[$this->_attrs['count']] = $k;
                $this->_attrs['count']++;
            }
        }
        return true;
    }

   /** dn - Set or get the DN for the object
    *
    * If a new dn is supplied, this will move the object when running $obj->update();
    * @param - string DN
    * @return none
    */
    function dn ($newdn="")
    {
        if ($newdn == "") {
            return $this->_dn;
        }
      
        $this->_olddn = $this->_dn;
        $this->_dn = $newdn;
        $this->updateCheck['newdn'] = true;
    }
   
   /** exists - check if a certain attribute exists in the directory
    *
    * Checks if the entry contains a certain attribute.
    * @params string attribute name.
    * @return boolean
   */
    function exists ($attr)
    {
        if (array_key_exists($attr,$this->_attrs)) {
            return true;
        }
    
        return false;
    }

   /** get_value get the values for a attribute
    * returns either an array or a string
    * $attr is a string with the attribute name.
    *
    *
    * @param $attr string attribute name
    *        $options assoiative array, possible values:
    *           alloptions - returns an array with the values + a countfield.
    *                       i.e.: array (count=>1, 'sn'=>'huse');
    *           single - returns the, first value in the array as a string.
    */
    function get_value($attr = '',$options = '')
    {

        if (array_key_exists($attr,$this->_attrs)) {

            if ($options == 'single') {
                if (is_array($this->_attrs[$attr])) {
                    return Net_LDAP::UTF8Decode($this->_attrs[$attr][0]);
                } else {
                    return Net_LDAP::UTF8Decode($this->_attrs[$attr]);
                }
            }

            $value = $this->_attrs[$attr];
            
            if (!$options == 'alloptions' ) {
                unset ( $value['count'] );
            }

            return  Net_LDAP::UTF8Decode($value);
            
        } else {
            return '';
        }
    }

    /* modify - add/delete/modify attributes
     *
     * this function tries to do all the things that replace(),delete() and add() does on an object.
     * Syntax:
     * array ( 'attribute' => newval, 'delattribute' => '', newattrivute => newval);
     * Note: You cannot use this function to modify parts of an attribute. You must modify the whole attribute.
     * You may call the function many times before running $entry->update();
     * @param array attributes to be modified
     * @return mixed errorObject if failure, true if success.
     * */
    function modify ( $attrs = array()) {
    
        if (!is_array($attrs) || count ($attrs) < 1 ) {
            return $this -> raiseError( "You did not supply an array as expected",1000); // 
        }

        foreach ($attrs as $k => $v) {
            // empty values are deleted (ldap v3 handling is in update() )            
            if ($v == '' && $this->exists($k)) {
                $this->_delAttrs[$k] = '';
                continue;
            }
            /* existing attributes are modified*/
            if ($this->exists($k) ) {
                if (is_array($v)) {
                     $this -> _modAttrs[$k] = $v;
                } else {
                    $this -> _modAttrs[$k][0] = $v;
                } 
            } else {
                /* new ones are created  */
                if (is_array($v) ) {
                    // an empty array is deleted...
                    if (count($v) == 0 ) {
                        $this->_delAttrs[$k] = '';
                    } else {
                        $this -> _addAttrs[$k] = $v;
                    }
                } else {
                    // dont't add empty attributes
                    if ($v != null) $this -> _addAttrs[$k][0] = $v;
                }
            }        
        }
        return true;
    }

    
   /** replace - replace a certain attributes value
    *
    * replace - replace a certain attributes value
    * example:
    * $entry->replace(array('uid'=>array('tarjei')));
    * @param array attributes to be replaced
    * @return error if failure, true if sucess.
    */
    function replace ($attrs = array() )
    {

        foreach ($attrs as $k => $v) {
           
            if ($this->exists($k)) {
                
                if (is_array($v)) {
                    $this -> _attrs[$k] = $v;
                    $this -> _attrs[$k]['count'] = count($v);
                    $this -> _modAttrs[$k] = $v;
                } else {
                    $this -> _attrs[$k]['count'] = 1;
                    $this -> _attrs[$k][0] = $v;
                    $this -> _modAttrs[$k][0] = $v;
                }
            } else {
                return $this->raiseError("Attribute $k does not exist",16); // 16 = no such attribute exists.
            }
        }
        return true;
    }

   /** delete -  delete attributes
    *
    * Use this function to delete certian attributes from an object.
    *
    * @param - array of attributes to be deleted
    * @return mixed Net_Ldap_Error if failure, true if success.
    * */
    function delete($attrs = array())
    {

        foreach ($attrs as $k => $v) {
            
            if ($this->exists ($k)) {
                // if v is a null, then remove the whole attribute, else only the value.
                if ($v = '') {
                    unset($this->_attrs[$k]);
                    $this -> _delAttrs[$k] = "";
                    
                // else we remove only the correct value.
                } else {
                
                    for ($i = 0;$i< $this->_attrs[$k]['count'];$i++) {
                        if ($this->_attrs[$k][$i] == $v ) {
                            unset ($this->_attrs[$k][$i]);
                            $this -> _delAttrs[$k] = $v;
                            continue;
                        }
                    }
                    
                }
                
            } else {
                $this->raiseError("You tried to delete a nonexisting attribute!",16);
            }


        }
        
        return true;
    }

   /** update -  update the Entry in LDAP
    *
    * After modifying an object, you must run update() to
    * make the updates on the ldap server. Before that, they only exists in the object.
    *
    * @param object Net_LDAP
    * @return mixed Net_LDAP_Error object on failure or true on success
    *
    * */
    function update ($ldapObject = null)
    {
        if ($ldapObject == null && $this->_link == null ) {
            $this->raiseError("No link to database");
        }

        if ($ldapObject != null) {
            $this->_link =& $ldapObject->_link;
        }

        //if it's a new 
        if ($this->updateCheck['newdn'] && !$this->updateCheck['newEntry']) {
            if (@ldap_get_option( $this->_link, LDAP_OPT_PROTOCOL_VERSION, $version) && $version != 3) {
                return $this->raiseError("Moving or renaming an dn is only supported in LDAP V3!", 80);
            }
            // ldap_rename ( resource link_identifier, string dn, string newrdn, string newparent, bool deleteoldrdn)
            $newparent = ldap_explode_dn($this->_dn,0);
            // remove the first part
            array_pop($newparent);
            if (!@ldap_rename( $this->_link,$this->_olddn,$this->_dn,$newparent,true) ){
                 return $this->raiseError("DN not renamed: " . ldap_error($this->_link),ldap_errno($this->_link));
            }
        }

        if ($this->updateCheck['newEntry']) {
           //print "<br>"; print_r($this->_clean_entry());

            if (!@ldap_add($this->_link, $this->dn(), Net_LDAP::UTF8Encode($this->_clean_entry()))) {
                  return $this->raiseError("Entry" . $this->dn() . " not added!" . ldap_error($this->_link), ldap_errno($this->_link));
            } else {
                return true;
            }
        // update existing entry
        } else {
            $this->_error['first'] = $this->_modAttrs;
            $this->_error['count'] = count($this -> _modAttrs); 
            
            // modified attributes
            if (( count($this->_modAttrs)>0) &&
                  !ldap_modify($this->_link, $this->dn(), Net_LDAP::UTF8Encode($this->_modAttrs)))
            {
                return $this->raiseError("Entry " . $this->dn() . " not modified(attribs not modified): " .
                                         ldap_error($this->_link),ldap_errno($this->_link));
            }
            
            // attributes to be deleted
            if (( count($this->_delAttrs) > 0 ))
            {
                // in ldap v3 we need to supply the old attribute values for deleting
                if (@ldap_get_option( $this->_link, LDAP_OPT_PROTOCOL_VERSION, $version) && $version == 3) {
                    foreach ( $this->_delAttrs as $k => $v ) {
                        if ( $v == '' && $this->exists($k) ) {
                            $this->_delAttrs[$k] = $this->get_value( $k );
                        }
                    }
                }
                if ( !ldap_mod_del($this->_link, $this->dn(), Net_LDAP::UTF8Encode($this->_delAttrs))) {
                    return $this->raiseError("Entry " . $this->dn() . " not modified (attributes not deleted): " .
                                             ldap_error($this->_link),ldap_errno($this->_link));
                }
            }
            
            // new attributes
            if (( count($this -> _addAttrs)) > 0 && !ldap_modify($this -> _link, $this -> dn(),Net_LDAP::UTF8Encode( $this -> _addAttrs))) {
                return $this -> raiseError( "Entry " . $this->dn() . " not modified (attributes not added): " . ldap_error($this->_link),ldap_errno($this->_link));
            }
                        
            return true;
        }
    }
}

?>
