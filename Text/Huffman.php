<?php

// {{{ license

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Markus Nix <mnix@docuverse.de>                              |
// |          David Holmes <exaton@free.fr> (original version)            |
// +----------------------------------------------------------------------+
//

// }}}


/**
 * This class is intented to perform Huffman static 
 * compression on files with a PHP script.
 *
 * Such compression is essentially useful for reducing 
 * the size of texts by about 43% ; it is at its best 
 * when working with data containing strong redundancies 
 * at the character level -- that is, the opposite of a 
 * binary file in which the characters would be spread 
 * over the whole ASCII alphabet.
 *
 * It is questionable whether anyone would want to do 
 * such an operation with PHP, when C implementations 
 * of much stronger and more versatile algorithms are 
 * readily avaible as PHP functions. The main drawback 
 * of this script class is slowness despite processing 
 * intensiveness (7 to 8 seconds to compress a 300Kb 
 * text, about 25 seconds to expand it back).
 *
 * USE AND FUNCTION REFERENCE :
 * 
 * The 4 PHP files having been placed in the same directory, the only ones you
 * have to include are compress.inc.php and/or expand.inc.php according to your
 * needs.
 * 
 * -----------------
 * -- Compression --
 * -----------------
 * 
 * Once a CPRS_Compress object has been constructed, the following functions
 * are available :
 * 
 * + setFiles('path/to/source/file', 'path/to/destination/file'):
 * 
 * This step is mandatory, as you give the paths to the file you want to
 * compress, and the file you want the compressed output written to. These
 * paths will be passed to the PHP fopen() function, see its reference for
 * details. Note that the paths, if local, should be relative to the location
 * of _your_ script, i.e. the one that has included this compression class.
 * 
 * + setTimeLimit(int seconds):
 * 
 * This step is optional. It allows you to force a certain timeout limit
 * for the PHP script, presumably longer than the default configuration on
 * your server, should the job take too long. It simply calls the PHP
 * set_time_limit() function.
 * 
 * + compress():
 * 
 * This is the function that actually executes the job. It receives no
 * parameters, and is of course obligatory.
 * 
 * ---------------
 * -- Expansion --
 * ---------------
 * 
 * Once a CPRS_Expand object has been constructed, the following functions
 * are available :
 * 
 * + setFiles('path/to/source/file', 'path/to/destination/file'):
 * 
 * This step is mandatory, as you give the paths to the file containing the
 * compressed data, and the file you want the expanded output written to. These
 * paths will be passed to the PHP fopen() function, see its reference for
 * details. Note that the paths, if local, should be relative to the location
 * of _your_ script, i.e. the one that has included this compression class.
 * 
 * + setTimeLimit(int seconds):
 * 
 * This step is optional. It allows you to force a certain timeout limit
 * for the PHP script, presumably longer than the default configuration on
 * your server, should the job take too long. It simply calls the PHP
 * set_time_limit() function.
 * 
 * + expand():
 * 
 * This is the function that actually executes the job. It receives no
 * parameters, and is of course obligatory.
 * 
 * 
 * EXTRA NOTICE:
 * 
 * Please also note that some technical considerations apart from the core
 * Huffman static algorithm have probably not been implemented after
 * any standard in this class. That means that any other compressed file,
 * even if you have reason to be certain that it was produced using the
 * Huffman static algorithm, would in all probability not be usable as
 * source file for data expansion with this class.
 * In short, this class can very probably only restore what it itself
 * compressed.
 * 
 * Anyway, thanks for using ! No feedback would be ignored. Feel free
 * to tell me how you came in contact with this class, why you're using
 * it (if at liberty to do so), and to suggest any enhancements, or of
 * course to point out any serious bugs.
 * 
 * @package Text
 */

class Text_Huffman
{
    // {{{ properties
    /**
     * Carrier window for reading from input 
     * @access protected
     */
    protected $_icarrier;
    
    /**
     * Length of the input carrier at any given time 
     * @access protected
     */
    protected $_icarlen;
    
    /**
     * Carrier window for writing to output 
     * @access protected
     */
    protected $_ocarrier;
    
    /**
     * Length of the output carrier at any given time 
     * @access protected
     */
    protected $_ocarlen;
    
    /**
     * Boolean to check files have been passed
     * @access protected
     */
    protected $_havefiles;
    
    /**
     * Character representing a Branch Node in Tree transmission
     * @access protected
     */
    protected $_nodeChar;
    
    /**
     * The same, character version as opposed to binary string
     * @access protected
     */
    protected $_nodeCharC;

    /**
     * Path to the input file
     * @access protected
     */ 
    protected $_ifile;
    
    /**
     * Resource handle of the input file
     * @access protected
     */
    protected $_ifhand;
    
    /**
     * Path to the output file
     * @access protected
     */
    protected $_ofile;
    
    /**
     * Resource handle of the output file
     * @access protected
     */
    protected $_ofhand;
    
    /**
     * Data eventually written to the output file
     * @access protected
     */
    protected $_odata;

    /**
     * Array of Node objects
     * @access protected
     */
    protected $_nodes;
    // }}}
    

    // {{{ constructor
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        $this->_havefiles = false;
        $this->_nodeChar  = '00000111';
        $this->_nodeCharC = chr(7);
        $this->_odata     = '';
        $this->_nodes     = array();
    }
    // }}}
    

    /**
     * setFiles() is called to specify the paths to the input and output files.
     * Having set the relevant variables, it gets resource pointers to the files
     * themselves.
     *
     * @throws Exception
     * @access public
     */
    public function setFiles($ifile = '', $ofile = '')
    {
        if (trim($ifile) == '') {
            throw new Exception('No input file provided.');
        } else {
            $this->_ifile = $ifile;
        }
    
        if (trim($ofile) == '') {
            throw new Exception('No output file provided.');
        } else {
            $this->_ofile = $ofile;
        }
    
        // Getting resource handles to the input and output files
    
        if (!($this->_ifhand = @fopen($this->_ifile, 'rb'))) {
            throw new Exception('Unable to open input file.');
        }
    
        if (!($this->_ofhand = @fopen($this->_ofile, 'wb'))) {
            throw new Exception('Unable to open output file.');
        }
        
        // Stating that files have been gotten
        $this->_havefiles = true;
    
        return true;
    }

    
    // protected methods
    
    /**
     * Bit-writing with a carrier: output every 8 bits
     *
     * @access protected
     */
    final protected function _bitWrite($str, $len)
    {
        // $carrier is the sequence of bits, in a string
        $this->_ocarrier .= $str;
        $this->_ocarlen  += $len;
    
        while ($this->_ocarlen >= 8)
        {
            $this->_odata    .= chr(bindec(substr($this->_ocarrier, 0, 8)));
            $this->_ocarrier  = substr($this->_ocarrier, 8);
            $this->_ocarlen  -= 8;
        }
    }

    /**
     * Finalizing bit-writing, writing the data. 
     *
     * @access protected
     */
    final protected function _bitWriteEnd()
    {
        // If carrier is not finished, complete it to 8 bits with 0's and write it out
        // Adding n zeros is like multipliying by 2^n
    
        if ($this->_ocarlen) {
            $this->_odata .= chr(bindec($this->_ocarrier) * pow(2, 8 - $this->_ocarlen));
        }
    
        // Writing the whole output data to file.
        fwrite($this->_ofhand, $this->_odata);
    }

    /**
     * Bit-reading with a carrier: input 8 bits at a time.
     *
     * @access protected
     */
    final protected function _bitRead($len)
    {
        // Fill carrier 8 bits (1 char) at a time until we have at least $len bits
    
        // Determining the number n of chars that we are going to have to read
        // This might be zero, if the icarrier is presently long enough
    
        $n = ceil(($len - $this->_icarlen) / 8);
    
        // Reading those chars, adding each one as 8 binary digits to icarrier
    
        for ($i = 0; $i < $n; $i++) {
            $this->_icarrier .= $this->_decBinDig(ord(fgetc($this->_ifhand)), 8);
        }
        
        // Getting the portion of icarrier we want to return
        // Then diminishing the icarrier of the returned digits
    
        $ret = substr($this->_icarrier, 0, $len);
        $this->_icarrier = substr($this->_icarrier, $len);
    
        // Adding the adequate value to icarlen, taking all operations into account
    
        $this->_icarlen += 8 * $n - $len;
    
        return $ret;
    }

    /**
     * Read 1 bit.
     *
     * @access protected
     */
    final protected function _bitRead1()
    {
        // Faster reading of just 1 bit
        // WARNING : requires icarrier to be originally empty !
        // NO keeping track of carrier length
    
        if ($this->_icarrier == '') {
            $this->_icarrier = $this->_decBinDig(ord(fgetc($this->_ifhand)), 8);
        }
    
        $ret = substr($this->_icarrier, 0, 1);
        $this->_icarrier = substr($this->_icarrier, 1);
    
        return $ret;
    }

    /**
     * Returns the binary representation of $x as a string, over $n digits, with
     * as many initial zeros as necessary to cover that. 
     *  
     * Note: $n has to be more digits than the binary representation of $x
     * originally has!
     *
     * @access protected
     */
    final protected function _decBinDig($x, $n)
    {
        return substr(decbin(pow(2, $n) + $x), 1);
    }
}

?>
