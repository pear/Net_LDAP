<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */

require_once 'PEAR.php';

/**
* LDIF capabilitys for Net_LDAP, closely taken from PERLs Net::LDAP
*
* @category Net
* @package  Net_LDAP
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @version  CVS: $Id$
* @link     http://pear.php.net/package/Net_LDAP/
* @see      http://www.ietf.org/rfc/rfc2849.txt
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
	* Linecounter for input file handle (FH or FHPIPE)
	*
	* @access private
	* @var array
	*/
	var $_input_line = 0;
	
	/**
	* Pipe filehandle, if we are in piped mode
	*
	* Either STDIN or STDOUT. This way, we know wheter we are in piped
	* mode and if so, what mode it is.
	*
	* @access private
	* @var array
	*/
	var $_FHPipe = null;
	
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
	* Cache for lines that will build the enxt entry
	*
	* @access private
	* @var boolean
	*/
	var $_lines_next = array();
	
	
	/**
	* Open LDIF file for read only or read/write
	*
	* new (FILE):
	* Open the file read-only. FILE may be the name of a file
	* or an already open filehandle.
	* If the file doesn't exist, it will be created in write mode.
	*
	* new (FILE, MODE, OPTIONS):
	*     Open the file with the given MODE (see PHPs fopen()), eg "w" or "a".
	*     FILE may be the name of a file or an already open filehandle.
	*     If FILE begins or ends with a | then FILE will be passed directly to open.
	*     ("|FILE" reads from STDIN, "FILE|" writes to STDOUT)
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
	*         'die'  Net::LDAP::LDIF will croak with an appropriate message.
	*         'warn' Net::LDAP::LDIF will warn (echo) with an appropriate message.
	*         undef  Net::LDAP::LDIF will not warn (default), use error().
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
	*         When this option is set Net::LDAP::LDIF tries to adhere more strictly to
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
	* @param string           $mode    Mode to open that file
	* @param array            $options Options like described above
	*/
	function Net_LDAP_LDIF($file, $mode = 'r', $options = array()) {
		// First, parse options
		// todo: maybe implement further checks on possible values
		foreach ($options as $option => $value) {
			if (!array_key_exists($option, $this->_options)) {
				$this->_addError('Net_LDAP_LDIF error: option '.$option.' not known!');
				return();
			} else {
				$this->_options[$option] = strtolower($value);
			}
		}
		
		// setup LDIF class
		$this->version($version);
		
		// setup file mode
		// todo: maybe check on allowed modes
		$this->_mode = $mode;
		
		// Decide operational mode
		if (is_resource($file)) {
			$this->_FH = $file;
		} else {
			// Look for piped mode and initialize streams
			if (preg_match('/^\|(.+)$/', $file, $fmatch)) {
				// Start-pipemode
				$file = $fmatch[1];
				if (!defined(STDIN)) {
					$this->_addError('Net_LDAP_LDIF error: |FILE only works with the CLI version of PHP');
				} else {
					$this->_FHPipe = STDIN;
				}
			} elseif(preg_match('/^(.+)\|$/', $file, $fmatch)) {
				// End-pipemode
				$file = $fmatch[1];
				if (!defined(STDOUT)) {
					$this->_addError('Net_LDAP_LDIF error: FILE| only works with the CLI version of PHP');
				} else {
					$this->_FHPipe = STDOUT;
				}
			}
			
			// Open file
			$fh = @fopen($file, $mode);
			if (false === $fh) {
				$this->_addError('Net_LDAP_LDIF error: Could not open file '.$file);
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
		$this->_lines_cur = $this->next_lines(true);
		return $this->current_entry();
	}
	
	/**
	* Returns true when the end of the file is reached.
	*/
	function eof() {
		if ($this->_fromSTDIN()) {
			return false;
		} else {
			return feof($this->_FH);
		}
	}
	
	/**
	* Write the entries to the LDIF file.
	*
	* If you want to build an LDIF file containing several entries,
	* you must open the filehandle in append mode ("a"), otherwise you will
	* always get the last entry only.
	*
	* @param Net_LDAP_Entry|array Entry or array of entries
	* @todo NOT IMPLEMENTED YET
	*/
	function write_entry($entries) {
		if (!is_array($entries)) {
			$entries = array($entries);
		}
		
		// write Version if not already done
		if (!$this->_version_written) {
			$this->write_version();
		}
		
		// write out entry
		foreach ($entries as $entry) {
			if (!is_a($entry, 'Net_LDAP_Entry')) {
				$this->_addError('Net_LDAP_LDIF error: unable to write corrupt entry');
			} else {
				// TODO: Convert and write to file
			}
		}
	}
	
	/**
	* Write version to LDIF
	*
	* If the object's version is defined, this method allows to explicitely write the version before an entry is written.
	* If not called explicitely, it gets called automatically when writing the first entry.
	*
	* @todo NOT IMPLEMENTED YET
	*/
	function write_version() {
		$this->_version_written = true;
		// TODO: Write to file
	}
	
	/**
	* Get or set LDIF version
	*
	* If called without arguments it returns the version of the LDIF file or undef if no version has been set.
	* If called with an argument it sets the LDIF version to VERSION.
	* According to RFC 2849 currently the only legal value for VERSION is 1.
	*
	* @param int $version
	* @todo NOT IMPLEMENTED YET
	*/
	function version($version = '') {
		if ($version) {
			if ($version != 1) {
				$this->_dropError('Net_LDAP_LDIF error: illegal LDIF version set');
			} else {
				$this->_version = $version;
			}
		}
	}
	
	/**
	* Returns the file handle the Net::LDAP::LDIF object reads from or writes to.
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
	* You can use this to free up memory.
	*
	* @todo NOT IMPLEMENTED YET
	*/
	function done() {
	}
	
	/**
	* Returns error message if error was found.
	*
	* @todo NOT IMPLEMENTED YET
	* @return true|Net_LDAP_Error
	*/
	function error() {
		return (Net_LDAP::isError($this->_error['error']))? $this->_error['error'] : true;
	}
	
	/**
	* Returns lines that resulted in error.
	*
	* Perl returns an array of faulty lines in list context,
	* but we always just return an int because of PHPs language.
	*
	* @todo NOT IMPLEMENTED YET
	* @return int
	*/
	function error_lines() {
		return $this->_error['line'];
	}
	
	/**
	* Returns the current Net::LDAP::Entry object.
	*
	* @todo NOT IMPLEMENTED YET
	* @return Net_LDAP_Entry
	* @todo what about file inclusions? "jpegphoto:< file:///usr/local/directory/photos/fiona.jpg"
	*/
	function current_entry() {
		// parse current lines into an array of attributes and build the entry
		$attributes = array();
		$dn = '';
		foreach ($this->current_lines() as $line) {
			if (preg_match('/^(\w+):\s(\w+)$/', $line, $data)) {
				// normal data
				$attributes[$data[1]] = $data[2];
			} elseif(preg_match('/^(\w+)::\s(\w+)$/', $line, $data)) {
				// base64 data
				$attributes[$data[1]] = base64_decode($data[2]);
			} elseif(preg_match('/^(\w+):<\s(\w+)$/', $line, $data)) {
				// file inclusion
				// TODO
				$this->_dropError('File inclusions are currently not supported');
				//$attributes[$data[1]] = ...;
			} else {
				$this->_dropError('Net_LDAP_LDIF parsing error: invalid syntax at parsing lines')
				break;
			}
			
			// detect DN
			if (strtolower($data[1]) == 'dn') {
				$dn = $data[1];
			}
		}
		
		$newentry = Net_LDAP_Entry::createfresh($dn, $attributes);
		return $newentry;
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
		if (count($lines) == 0 || $force) {
			$this->_lines_next = array(); // empty in case something was left (if used $force)
			$entry_done = false;
			$fh =& $this->_getInputStream();
			$commentmode = false; // if we are in an comment, for wrapping purposes
			while ($entry_done || $this->eof() {
				$this->_input_line++;
				$data = fgets($fh);
				if ($data === false) {
					$this->_dropError('Net_LDAP_LDIF error: error reading from file', $this->_input_line)
					break;
				} else {
					if (count($this->_lines_next) > 0 && $data != '') {
						// entry is finished if we have an empty line
						$entry_done = true;
					} else {
						// build lines
						if (preg_match('/^\w+::?\s.+)$/', $data)) {
							// normal attribute: add line
							$commentmode         = false;
							$this->_lines_next[] = $data;
						} elseif (preg_match('/^\s(.+)$/', $data, $matches)) {
							// wrapped data: unwrap if not in comment mode
							if (!$commentmode) {
								$last = array_pop($this->_lines_next[]);
								$last = $last.$matches[1];
								$this->_lines_next[] = $last;
							}
						} elseif (preg_match('/^#/', $data, $matches)) {
							$commentmode = true;
						} else {
							$this->_dropError('Net_LDAP_LDIF error: invalid syntax', $this->_input_line)
							break;
						}
						
					}
				}
			}
		}
		
		return $this->_lines_next;
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
	
	/**
	* Are we reading from a pipe?
	*
	* @access private
	* @return boolean
	*/
	function _fromSTDIN() {
		return ($this->_FHPipe === STDIN)? true : false;
	}
	
	/**
	* Are we writing to a pipe?
	*
	* @access private
	* @return boolean
	*/
	function _toSTDOUT() {
		return ($this->_FHPipe === STDOUT)? true : false;
	}
	
	/**
	* Returns the input file handle
	*
	* @access private
	* @return resource
	*/
	function &_getInputStream() {
		if ($this->_fromSTDIN()) {
			return $this->_FHPipe;
		} else {
			return $this->handle();
		}
	}
	
	/**
	* Returns the output file handle
	*
	* @access private
	* @return resource
	*/
	function &_getOutputStream() {
		if ($this->_toSTDOUT()) {
			return $this->_FHPipe;
		} else {
			return $this->handle();
		}
	}
}
?>