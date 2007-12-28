<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

require_once 'PEAR.php';
require_once 'Net/LDAP/Entry.php';

/**
* LDIF capabilitys for Net_LDAP, closely taken from PERLs Net::LDAP
*
* It provides a means to convert between Net_LDAP_Entry objects and LDAP entries
* represented in LDIF format files. Reading and writing are supported and may
* manipulate single entries or lists of entries.
*
* Usage example:
* <code>
* // Read and parse an ldif-file into Net_LDAP_Entry
* // objects and print out the DNs
* require 'Net/LDAP/LDIF.php';
* $options = array(
*       'onerror' => 'die'
* );
* $ldif = new Net_LDAP_LDIF('test.ldif', 'r', $options);
* do {
*       $entry = $ldif->read_entry();
*       $dn = $entry->dn();
*       echo $ldif->_input_line." done building entry: $dn\n";
* } while (!$ldif->eof());
* </code>
*
* @category Net
* @package  Net_LDAP
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @version  CVS: $Id$
* @link     http://pear.php.net/package/Net_LDAP/
* @see      http://www.ietf.org/rfc/rfc2849.txt
* @todo     Error handling should be PEARified
*/
class Net_LDAP_LDIF extends PEAR
{
	/**
	* Options
	*
	* @access private
	* @var array
	*/
	var $_options = array(
		'encode'    => 'none',
		'onerror'   => 'undef',
		'change'    => 0,
		'lowercase' => 0,
		'sort'      => 0,
		'version'   => 1,
		'wrap'      => 78,
		'raw'       => ''
	);
	
	/**
	* Errorcache
	*
	* @access private
	* @var array
	*/
	var $_error = array(
		'error' => null,
		'line'  => 0
	);
	
	/**
	* Filehandle for read/write
	*
	* @access private
	* @var array
	*/
	var $_FH = null;
	
	/**
	* Linecounter for input file handle
	*
	* @access private
	* @var array
	*/
	var $_input_line = 0;
	
	/**
	* Mode we are working in
	*
	* Either 'r', 'a' or 'w'
	*
	* @access private
	* @var string
	*/
	var $_mode = false;
	
	
	/**
	* Tells, if the LDIF version string was already written
	*
	* @access private
	* @var boolean
	*/
	var $_version_written = false;
	
	/**
	* Cache for lines that have build the current entry
	*
	* @access private
	* @var boolean
	*/
	var $_lines_cur = array();
	
	/**
	* Cache for lines that will build the next entry
	*
	* @access private
	* @var boolean
	*/
	var $_lines_next = array();
	
	
	/**
	* Open LDIF file for reading or for writing
	*
	* new (FILE):
	* Open the file read-only. FILE may be the name of a file
	* or an already open filehandle.
	* If the file doesn't exist, it will be created if in write mode.
	*
	* new (FILE, MODE, OPTIONS):
	*     Open the file with the given MODE (see PHPs fopen()), eg "w" or "a".
	*     FILE may be the name of a file or an already open filehandle.
	*     PERLs Net_LDAP "FILE|" mode does not work curently.
	*
	*     OPTIONS is an associative array and may contain:
	*       encode => 'none' | 'canonical' | 'base64'
	*         Some DN values in LDIF cannot be written verbatim and have to be encoded in some way:
	*         'none'       The default.
	*         'canonical'  See "canonical_dn()" in Net::LDAP::Util.
	*         'base64'     Use base64.
	*
	*       onerror => 'die' | 'warn' | undef
	*         Specify what happens when an error is detected.
	*         'die'  Net_LDAP_LDIF will croak with an appropriate message.
	*         'warn' Net_LDAP_LDIF will warn (echo) with an appropriate message.
	*         undef  Net_LDAP_LDIF will not warn (default), use error().
	*
	*       change => 1
	*         Write entry changes to the LDIF file instead of the entries itself. I.e. write LDAP
	*         operations acting on the entries to the file instead of the entries contents.
	*
	*       lowercase => 1
	*         Convert attribute names to lowercase when writing.
	*
	*       sort => 1
	*         Sort attribute names when writing entries according to the rule:
	*         objectclass first then all other attributes alphabetically sorted
	*
	*       version => '1'
	*         Set the LDIF version to write to the resulting LDIF file.
	*         According to RFC 2849 currently the only legal value for this option is 1.
	*         When this option is set Net_LDAP_LDIF tries to adhere more strictly to
	*         the LDIF specification in RFC2489 in a few places.
	*         The default is undef meaning no version information is written to the LDIF file.
	*
	*       wrap => 78
	*         Number of columns where output line wrapping shall occur.
	*         Default is 78. Setting it to 40 or lower inhibits wrapping.
	*
	*       raw => REGEX
	*         Use REGEX to denote the names of attributes that are to be
	*         considered binary in search results.
	*         Example: raw => "/(?i:^jpegPhoto|;binary)/i"
	*         Note: if the entry has a valid LDAP connection, then binary checks
	*               are also done through the schema.
	*
	* @param string|ressource $file    Filename or filehandle
	* @param string           $mode    Mode to open filename
	* @param array            $options Options like described above
	*/
	function Net_LDAP_LDIF($file, $mode = 'r', $options = array()) {
		// First, parse options
		// todo: maybe implement further checks on possible values
		foreach ($options as $option => $value) {
			if (!array_key_exists($option, $this->_options)) {
				$this->_dropError('Net_LDAP_LDIF error: option '.$option.' not known!');
				return;
			} else {
				$this->_options[$option] = strtolower($value);
			}
		}
		
		// setup LDIF class
		$this->version($this->_options['version']);
		
		// setup file mode
		if (!preg_match('/^[rwa](?:\+b|b\+)?$/', $mode)) {
			$this->_dropError('Net_LDAP_LDIF error: file mode '.$mode.' not supported!');
		} else {
			$this->_mode = $mode;
		}
		
		// setup filehandle
		if (is_resource($file)) {
			// checks on mode?
			$this->_FH = $file;
		} else {
			if ($this->_mode == 'r' && !is_readable($file)) {
				$this->_dropError('Unable to open '.$file.': permission denied');
				$this->_mode = false;
			} elseif (($this->_mode == 'w' || $this->_mode == 'a') && !is_writable($file)) {
				$this->_dropError('Unable to open '.$file.': permission denied');
				$this->_mode = false;
			}
			
			if ($this->_mode) {
				$this->_FH = @fopen($file, $this->_mode);
				if (false === $this->_FH) {
					$this->_dropError('Net_LDAP_LDIF error: Could not open file '.$file);
				}
			}
		}
	}
	
	/**
	* Read one entry from the file and return it as a Net::LDAP::Entry object.
	*
	* @return Net_LDAP_Entry
	*/
	function read_entry() {
		// read fresh lines, set them as current lines and create the entry
		$attrs = $this->next_lines(true);
		if (count($attrs) > 0) {
			$this->_lines_cur = $attrs;
		}
		return $this->current_entry();
	}
	
	/**
	* Returns true when the end of the file is reached.
	*/
	function eof() {
		return feof($this->_FH);
	}
	
	/**
	* Write the entries to the LDIF file.
	*
	* If you want to build an LDIF file containing several entries,
	* you must open the filehandle in append mode ("a"), otherwise you will
	* always get the last entry only.
	*
	* @param Net_LDAP_Entry|array Entry or array of entries
	*/
	function write_entry($entries) {
		if (!is_array($entries)) {
			$entries = array($entries);
		}
		
		// write Version if not already done
		if (!$this->_version_written) {
			$this->write_version();
		}
		
		// write out entries
		$entrynum = 0;
		foreach ($entries as $entry) {
			$entrynum++;
			if (!is_a($entry, 'Net_LDAP_Entry')) {
				$this->_dropError('Net_LDAP_LDIF error: unable to write corrupt entry '.$entrynum);
			} else {
				// write DN
				$dn = 'dn: '.$this->_convertDN($entry->dn())."\r\n";
				if (fwrite($this->handle(), $dn, strlen($dn)) === false) {
					$this->_dropError('Net_LDAP_LDIF error: unable to write DN of entry '.$entrynum);
				} else {
					// write attributes
					foreach ($entry->getValues() as $attr_name => $attr_values) {
						if (!is_array($attr_values)) {
							$attr_values = array($attr_values);
						}
						foreach ($attr_values as $attr_val) {
							$line = $this->_convertAttribute($attr_name, $attr_val)."\r\n";
							if (fwrite($this->handle(), $line, strlen($line)) === false) {
								$this->_dropError('Net_LDAP_LDIF error: unable to write attribute '.$attr_name.' of entry '.$entrynum);
							}
						}
					}
				
					// mark end of entry
					if (fwrite($this->handle(), "\r\n", 2) === false) {
						$this->_dropError('Net_LDAP_LDIF error: unable to close entry '.$entrynum);
					}
				}
			}
		}
	}
	
	/**
	* Write version to LDIF
	*
	* If the object's version is defined, this method allows to explicitely write the version before an entry is written.
	* If not called explicitely, it gets called automatically when writing the first entry.
	*/
	function write_version() {
		$this->_version_written = true;
		$version_string = 'version: '.$this->version()."\r\n";
		if (fwrite($this->handle(), $version_string, strlen($version_string)) === false) {
			$this->_dropError('Net_LDAP_LDIF error: unable to write version');
		} else {
			return true;
		}
	}
	
	/**
	* Get or set LDIF version
	*
	* If called without arguments it returns the version of the LDIF file or undef if no version has been set.
	* If called with an argument it sets the LDIF version to VERSION.
	* According to RFC 2849 currently the only legal value for VERSION is 1.
	*
	* @param int $version
	* @return int
	*/
	function version($version = '') {
		if ($version) {
			if ($version != 1) {
				$this->_dropError('Net_LDAP_LDIF error: illegal LDIF version set');
			} else {
				$this->_version = $version;
			}
		}
		return $this->_version;
	}
	
	/**
	* Returns the file handle the Net_LDAP_LDIF object reads from or writes to.
	*
	* You can, for example, use this to fetch the content of the LDIF file yourself
	*
	* @return null|resource
	*/
	function &handle() {
		if (!is_resource($this->_FH)) {
			$this->_dropError('Net_LDAP_LDIF error: invalid file resource');
			return null;
		} else {
			return $this->_FH;
		}
	}
	
	/**
	* Clean up
	*
	* This method signals that the LDIF object is no longer needed.
	* You can use this to free up some memory.
	*
	* @todo NOT IMPLEMENTED YET
	*/
	function done() {
	}
	
	/**
	* Returns error message if error was found.
	*
	* Example:
	* <code>
	*  $ldif->someAction();
	*  if ($ldif->error()) {
	*     echo "Error: ".$ldif->error()." at input line: ".$ldif->error_lines();
	*  }
	* </code>
	*
	* @return false|Net_LDAP_Error
	*/
	function error() {
		return (Net_LDAP::isError($this->_error['error']))? $this->_error['error'] : false;
	}
	
	/**
	* Returns lines that resulted in error.
	*
	* Perl returns an array of faulty lines in list context,
	* but we always just return an int because of PHPs language.
	*
	* @return int
	*/
	function error_lines() {
		return $this->_error['line'];
	}
	
	/**
	* Returns the current Net::LDAP::Entry object.
	*
	* @return Net_LDAP_Entry
	* @todo what about file inclusions and urls? "jpegphoto:< file:///usr/local/directory/photos/fiona.jpg"
	*/
	function current_entry() {
		// parse current lines into an array of attributes and build the entry
		$attributes = array();
		$dn = false;
		foreach ($this->current_lines() as $line) {
			preg_match('/^(\w+)(:|::|:<)(.+)$/', $line, $matches);
			$attr  =& $matches[1];
			$delim =& $matches[2];
			$data  =& $matches[3];
			
			if ($delim == ':') {
				// normal data
				$attributes[$attr][] = $data;
			} elseif($delim == '::') {
				// base64 data
				$attributes[$attr][] = base64_decode($data);
			} elseif($delim == ':<') {
				// file inclusion
				// TODO: Is this the job of the LDAP-client or the server?
				$this->_dropError('File inclusions are currently not supported');
				//$attributes[$attr][] = ...;
			} else {
				$this->_dropError('Net_LDAP_LDIF parsing error: invalid syntax at parsing entry line: '.$line);
				break;
			}
			
			// detect DN
			if (strtolower($attr) == 'dn') {
				$dn = $data;
			}
		}

		if (false === $dn) {
			$this->_dropError('Net_LDAP_LDIF parsing error: unable to detect DN for entry');
			return false;
		} else {
			$newentry = Net_LDAP_Entry::createFresh($dn, $attributes);
			return $newentry;
		}
	}
	
	/**
	* Returns the lines that generated the current Net::LDAP::Entry object.
	*
	* @return array Array of lines
	*/
	function current_lines() {
		return $this->_lines_cur;
	}
	
	/**
	* Returns the lines that will generate the next Net::LDAP::Entry object.
	*
	* If you set $force to TRUE then you can iterate over the lines that build
	* up entries manually. Otherwise, iterating is done using {@link read_entry()}
	*
	* Wrapped lines will be unwrapped. Comments are stripped.
	*
	* @param boolean $force Set this to true if you want to iterate over the lines manually
	* @return array
	*/
	function next_lines($force = false) {
		// if we already have those lines, just return them, otherwise read
		if (count($this->_lines_next) == 0 || $force) {
			$this->_lines_next = array(); // empty in case something was left (if used $force)
			$entry_done = false;
			$fh =& $this->handle();
			$commentmode = false; // if we are in an comment, for wrapping purposes
			$lines_read = 0;
			
			while (!$entry_done && !$this->eof()) {
				$this->_input_line++;
				$lines_read++;
				$data = fgets($fh);
				if ($data === false) {
					// error only, if EOF not reached after fgets() call
					if  (!$this->eof()) {
						$this->_dropError('Net_LDAP_LDIF error: error reading from file at input line '.$this->_input_line, $this->_input_line);
					}
					break;
				} else {
					if (count($this->_lines_next) > 0 && preg_match('/^$/', $data)) {
						// Entry is finished if we have an empty line after we had data
						$entry_done = true;
						
						// Look ahead if the next EOF is nearby. Comments and empty
						// lines at the file end may cause problems otherwise
						$current_pos = ftell($fh);
						$data        = fgets($fh);
						while (!feof($fh)) {
							if (preg_match('/^\s*$/', $data) || preg_match('/^#/', $data)) {
								// only empty lines or comments, continue to seek
								// TODO: Known bug: Wrappings for comments are okay but are treaten as
								//       error, since we do not honor comment mode here.
								//       This should be a very theoretically case, however so
								//       i fix this if necessary.
								$this->_input_line++;
								$current_pos = ftell($fh);
								$data        = fgets($fh);
							} else {
								// Data found if non emtpy line and not a comment!!
								// Rewind to position prior last read and stop lookahead
								fseek($fh, $current_pos);
								break;
							}
						}
						// now we have either the file pointer at the beginning of
						// a new data position or at the end of file causing feof() to return true
						
					} else {
						// build lines
						if (preg_match('/^\w+::?\s.+$/', $data)) {
							// normal attribute: add line
							$commentmode         = false;
							$this->_lines_next[] = trim($data);
						} elseif (preg_match('/^\s(.+)$/', $data, $matches)) {
							// wrapped data: unwrap if not in comment mode
							if (!$commentmode) {
								if ($lines_read == 1) {
									// first line: wrapped data is illegal
									$this->_dropError('Net_LDAP_LDIF error: illegal wrapping at input line '.$this->_input_line, $this->_input_line);
								} else {
									$last = array_pop($this->_lines_next);
									$last = $last.$matches[1];
									$this->_lines_next[] = $last;
								}
							}
						} elseif (preg_match('/^#/', $data)) {
							// LDIF comments
							$commentmode = true;
						} elseif (preg_match('/$/', $data)) {
							// empty line but we had no data for this
							// entry,so just ignore this line
						} else {
							$this->_dropError('Net_LDAP_LDIF error: invalid syntax at input line '.$this->_input_line, $this->_input_line);
							break;
						}
						
					}
				}
			}
		}
		return $this->_lines_next;
	}
	
	/**
	* Convert an attribute and value to LDIF string representation
	*
	* It honors correct encoding of values
	*
	* @access private
	* @param string $attr_name  Name of the attribute
	* @param string $attr_value Value of the attribute
	* @return string LDIF string for that attribute and value
	* @todo encoding stuff
	*/
	function _convertAttribute($attr_name, $attr_value) {
		// TODO: Encoding must be implemented, if some bad chars are detected,
		//       we must use BASE64; additionally we must take care of non-UTF8
		return $attr_name.': '.$attr_value;
	}
	
	/**
	* Convert an entries dn to LDIF string representation
	*
	* @access private
	* @param string $dn  DN
	* @return string LDIF string for that DN
	* @todo encoding stuff
	*/
	function _convertDN($dn) {
		return $dn;
	}
	
	/**
	* Optionally raises an error and pushes the error on the error cache
	*
	* @access private
	* @param string $msg  Errortext
	* @param int    $line Line in the LDIF that caused the error
	*/
	function _dropError($msg, $line = 0) {
		$this->_error['error'] = PEAR::raiseError($msg);
		$this->_error['line']  = $line;
		
		if ($this->_options['onerror'] == 'die') {
			die($msg.PHP_EOL);
		} elseif ($this->_options['onerror'] == 'warn') {
			echo $msg.PHP_EOL;
		}
	}
}
?>