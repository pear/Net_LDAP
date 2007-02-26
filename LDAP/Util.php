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
// | Authors: Benedikt Hallinger                                              |
// +--------------------------------------------------------------------------+
//
// $Id$
require "PEAR.php";


/**
 * Utility Class for Net_LDAP
 *
 * This class servers some functionality to the other classes of Net_LDAP but most of
 * the methods can be used separately as well.
 *
 * @package Net_LDAP
 * @author Benedikt Hallinger <beni@php.net>
 * @version $Revision$
 */
class Net_LDAP_Util extends PEAR
{
    /**
     * Private empty Constructur
     *
     * @access private
     */
    function Net_LDAP_Util()
    {
         // We do nothing here, since all methods can be called statically.
         // In Net_LDAP <= 0.7, we needed a instance of Util, because
         // it was possible to do utf8 encoding and decoding, but this
         // has been moved to the LDAP class.
    }

    /**
     * Wrapper function for PHPs ldap_explode_dn()
     *
     * PHPs ldap_explode_dn() does not escape DNs so it will fail
     * if the parameter $dn is something like <kbd>"<foobar>"</kbd> or contains
     * Umlauts.
     * This method ensures, that the DN is properly escaped and encoded.
     *
     * It is taken from http://php.net/ldap_explode_dn and slightly modified.
     *
     * @author DavidSmith@byu.net
     * @param string $dn           The DN that should be split
     * @param string $only_values  Return just values, no attribute names ('foo' instead of 'cn=foo')
     * @static
     * @deprecated This method gets superseeded by ldap_explode_dn(), if it is done
     */
    function ldap_explode_dn_escaped($dn, $only_values = 0)
    {
        $dn = addcslashes( $dn, "<>" );
        $result = ldap_explode_dn( $dn, $only_values );
        if (!$result) {
            return PEAR::raiseError("Error exploding DN: invalid DN!");
        }
        if (isset($result["count"])) {
            unset($result["count"]);
        }
        //translate hex code into ascii again
        foreach( $result as $key => $value )
            $result[$key] = preg_replace("/\\\([0-9A-Fa-f]{2})/e", "''.chr(hexdec('\\1')).''", $value);
        return $result;
     }

    /**
    * Explodes the given DN into its elements
    *
    * {@link http://www.ietf.org/rfc/rfc2253.txt RFC 2253} says, a Distinguished Name is a sequence
    * of Relative Distinguished Names (RDNs), which themselves
    * are sets of Attributes. For each RDN a array is constructed where the RDN part is stored.
    *
    * For example, the DN 'OU=Sales+CN=J. Smith,DC=example,DC=net' is exploded to:
    * <kbd>array( [0] => 'OU=Sales', [1] => 'CN=J. Smith', [2] => 'DC=example', [3] => 'DC=net' )</kbd>
    *
    * [NOT IMPLEMENTED] DNs might also contain values, which are the bytes of the BER encoding of
    * the X.500 AttributeValue rather than some LDAP string syntax. These values are hex-encoded
    * and prefixed with a #. To distinguish such BER values, ldap_explode_dn uses references to
    * the actual values, e.g. '1.3.6.1.4.1.1466.0=#04024869,DC=example,DC=com' is exploded to:
    * [ { '1.3.6.1.4.1.1466.0' => "\004\002Hi" }, { 'DC' => 'example' }, { 'DC' => 'com' } ];
    *
    * [NOT IMPLEMENTED] It also performs the following operations on the given DN:
    *   - Unescape "\" followed by ",", "+", """, "\", "<", ">", ";", "#", "=", " ", or a hexpair
    *     and and strings beginning with "#".
    *   - Removes the leading 'OID.' characters if the type is an OID instead of a name.
    *
    * OPTIONS is a list of name/value pairs, valid options are:
    *   casefold    Controls case folding of attribute types names.
    *               Attribute values are not affected by this option.
    *               The default is to uppercase. Valid values are:
    *               lower        Lowercase attribute types names.
    *               upper        Uppercase attribute type names. This is the default.
    *               none         Do not change attribute type names.
    *   reverse     If TRUE, the RDN sequence is reversed.
    *   onlyvalues  If TRUE, then only attributes values are returned ('foo' instead of 'cn=foo')
    *
    * @todo Escaping stuff needs to be implemented completely: http://www.ietf.org/rfc/rfc2253.txt
    * @todo OID stuff needs to be implemented
    * @todo The return value is currently php-like. Maybe we should return exactly the structure perl would return
    * @todo ldap_explode_dn does not (un)escape anything thus we should maybe code our own exploding mechanism. We could use unescape_dn_value() to do that
    * @static
    * @author beni@php.net, based on work from DavidSmith@byu.net
    * @param string $dn      The escaped DN that should be exploded
    * @param array  $options  Options to use
    * @return array    Parts of the exploded DN
    */
    function ldap_explode_dn($dn, $options = array('casefold' => 'upper'))
    {
        $options['onlyvalues'] == true ? $options['onlyvalues'] = 1 : $options['onlyvalues'] = 0;
        !isset($options['reverse']) ? $options['reverse'] = false : $options['reverse'] = true;
        if (!isset($options['casefold'])) $options['casefold'] = 'upper';

        // Escaping of DN
        $dn = addcslashes( $dn, "<>" );
        $dn_array = ldap_explode_dn( $dn, $options['onlyvalues'] );
        if (!$dn_array) {
            return PEAR::raiseError("Error exploding DN: invalid DN!");
        }
        if (isset($dn_array["count"])) {
            unset($dn_array["count"]);
        }

        //translate hex code into ascii again and apply case folding
        foreach ( $dn_array as $key => $value ) {
            $value = preg_replace("/\\\([0-9A-Fa-f]{2})/e", "''.chr(hexdec('\\1')).''", $value);
            if ($options['casefold'] == 'upper') $value = preg_replace("/^(\w+)=/e", "''.strtoupper('\\1').''", $value);
            if ($options['casefold'] == 'lower') $value = preg_replace("/^(\w+)=/e", "''.strtolower('\\1').''", $value);
        }

        if ($options['reverse']) {
            return array_reverse($dn_array);
        } else {
            return $dn_array;
        }
    }

    /**
    * escape_dn_value ( VALUES )
    *
    * Comment taken from CPAN:
    * Escapes the given VALUES according to RFC 2253 so that they can be safely used in LDAP DNs.
    * The characters ",", "+", """, "\", "<", ">", ";", "#", "=" with a special meaning in RFC 2252
    * are preceeded by ba backslash. Control characters with an ASCII code < 32 are represented as \hexpair.
    * Finally all leading and trailing spaces are converted to sequences of \20.
    *
    * Returns the converted list in list mode and the first element in scalar mode.
    *
    * @todo implement me!
    * @static
    * @param array $values    A array containing the DN values that should be escaped
    * @return array           The array $values, but escaped
    */
    function escape_dn_value($values = array())
    {
        PEAR::raiseError("Not implemented!");
    }

    /**
    * Undoes the conversion done by escape_dn_value().
    *
    * Any escape sequence starting with a baskslash - hexpair or special character -
    * will be transformed back to the corresponding character.
    *
    * Returns the converted list in list mode and the first element in scalar mode.
    *
    * @todo implement me!
    * @param array $values    Array of DN Values
    * @return array           Same as $values, but unescaped
    * @static
    */
    function unescape_dn_value($values = array())
    {
        PEAR::raiseError("Not implemented!");
    }

    /**
    * Returns the given DN in a canonical form
    *
    * Returns undef if DN is not a valid Distinguished Name.
    * Note: The empty string "" is a valid DN.) DN can either be a string or reference to an array of
    * hashes as returned by ldap_explode_dn, which is useful when constructing a DN.
    *
    * It performs the following operations on the given DN:
    *     - Removes the leading 'OID.' characters if the type is an OID instead of a name.
    *     - Escapes all RFC 2253 special characters (",", "+", """, "\", "<", ">", ";", "#", "=", " "), slashes ("/"), and any other character where the ASCII code is < 32 as \hexpair.
    *     - Converts all leading and trailing spaces in values to be \20.
    *     - If an RDN contains multiple parts, the parts are re-ordered so that the attribute type names are in alphabetical order.
    *
    * OPTIONS is a list of name/value pairs, valid options are:
    *     casefold    Controls case folding of attribute type names.
    *                 Attribute values are not affected by this option. The default is to uppercase.
    *                 Valid values are:
    *                 lower        Lowercase attribute type names.
    *                 upper        Uppercase attribute type names. This is the default.
    *                 none         Do not change attribute type names.
    *     mbcescape   If TRUE, characters that are encoded as a multi-octet UTF-8 sequence will be escaped as \(hexpair){2,*}.
    *     reverse     If TRUE, the RDN sequence is reversed.
    *     separator   Separator to use between RDNs. Defaults to comma (',').
    *
    * @todo implement me!
    * @static
    * @param string $dn      The DN
    * @param array  $option  Options to use
    * @return string    The canonical DN
    */
    function canonical_dn($dn, $options = array('casefold' => 'upper'))
    {
        PEAR::raiseError("Not implemented!");
    }

    /**
    * Escapes the given VALUES according to RFC 2254 so that they can be safely used in LDAP filters.
    *
    * Any control characters with an ACII code < 32 as well as the characters with special meaning in
    * LDAP filters "*", "(", ")", and "\" (the backslash) are converted into the representation of a
    * backslash followed by two hex digits representing the hexadecimal value of the character.
    *
    * @todo NULL escaping seems to never apply
    * @todo The "ASCII escaping" Part needs some work
    * @static
    * @param array $values    Array of values to escape
    * @return array           Array $values, but escaped
    */
    function escape_filter_value($values = array())
    {
        $escaped = array();
        foreach ($values as $val) {
            // ASCII < 32 escaping
            // [TODO]

            // Escaping of meta characters
            $val = str_replace('*', '\0x2a', $val);
            $val = str_replace('(', '\0x28', $val);
            $val = str_replace(')', '\0x29', $val);
            $val = str_replace('\\', '\0x5c', $val);
            $val = str_replace(null, '\0x2a', $val); // null escaping seems to never apply. This probably needs some work!

            if ($val === null) $val = '\0x2a';  // apply escaped "null" if string is empty

            array_push($escaped, $val);
        }

        return $escaped;
    }

    /**
    * Undoes the conversion done by {@link escape_filter_value()}.
    *
    * Converts any sequences of a backslash followed by two hex digits into the corresponding character.
    *
    * Returns the converted list in list mode and the first element in scalar mode.
    *
    * @todo implement me!
    * @static
    * @param array $values    Array of values to escape
    * @return array           Array $values, but unescaped
    */
    function unescape_filter_value($values = array())
    {
        PEAR::raiseError("Not implemented!");
    }

}

?>