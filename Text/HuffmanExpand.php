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


require_once 'Text/Huffman.php';


/**
 * Huffman Expansion Class
 *
 * @package Text
 */
 
class Text_HuffmanExpand extends Text_Huffman
{
    // {{{ properties
    /**
     * Size of the output file, in bytes
     * @access protected
     */ 
    protected $_ofsize;
    
    /**
     * For use in Huffman Tree reconstruction
     * @access protected
     */
    protected $_ttlnodes;
    // }}}
    
    
    // {{{ constructor
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
    
        // Initializing expansion-specific variables
        $this->_icarrier = '';
        $this->_icarlen  = 0;
    }
    // }}}
    

    /**
     * Perform expansion.
     *
     * @access public
     */
    public function expand()
    {
        if (!$this->_havefiles) {
            throw new Exception('Files not provided.');
        }
    
        // From header: reading Huffman tree (with no weights, mind you)
        $this->_reconstructTree();
    
        // From header: number of characters to read (ie. size of output file)
        $this->_ofsize = bindec($this->_bitRead(24));
    
        // Reading bit-by-bit and generating output
        $this->_readToMakeOutput();
    
        // Writing the output and closing resource handles
        fwrite($this->_ofhand, $this->_odata);

        fclose($this->_ofhand);
        fclose($this->_ifhand);
    }

    
    // private methods
    
    /**
     * Reconstruct the Huffman tree transmitted in header.
     *
     * @access private
     */
    private function _readTPForChild($par, $child, $childid, $charin)
    {
        // Creating child, setting right parent and right child for parent
        $this->_nodes[$par][$child] = $childid;
    
        $char = ($charin == $this->_nodeCharC)? '' : $charin;
    
        $node = array(
            '_char'   => $char,
            '_w'      => 0,
            '_par'    => $par,
            '_child0' => -1,
            '_child1' => -1,
            '_lndone' => false
        );
        
        $this->_nodes[$childid] = $node;
    
        // Special business if we have a Branch Node
        // Doing all of this for the child!
        if ($char === '') {
            $this->_readTreePart($childid);
        }
    }   

    /**
     * @access private
     */
    private function _readTreePart($nodenum)
    {
        // Reading from the header, creating a child
        $charin = fgetc($this->_ifhand);
        $this->_readTPForChild($nodenum, '_child0', ++$this->_ttlnodes, $charin);
    
        $charin = fgetc($this->_ifhand);
        $this->_readTPForChild($nodenum, '_child1', ++$this->_ttlnodes, $charin);
    }

    /**
     * @access private
     */
    private function _reconstructTree()
    {
        // Creating Root Node. Here root is indexed 0.
        // It's parent is -1, it's children are as yet unknown.
        // NOTE : weights no longer have the slightest importance here
    
        $node = array(
            '_char'   => '',
            '_w'      => 0,
            '_par'    => -1,
            '_child0' => -1,
            '_child1' => -1,
            '_lndone' => false
        );
        
        $this->_nodes[0] = $node;
    
        // Launching the business
        $this->_ttlnodes = 0; // Init value 
        $this->_readTreePart(0);
    }

    /**
     * Reading the compressed data bit-by-bit and generating the output.
     *
     * Huffman Compression has unique-prefix property, so as soon as 
     * we recognise a code, we can assume the corresponding char. 
     * All adding up, by reading $ofsize chars from the file, we should get
     * to the end of it !
     *
     * @access private
     */
    private function _readUntilLeaf($curnode)
    {
        if ($curnode['_char'] !== '') {
            return $curnode['_char'];
        }
    
        if ($this->_bitRead1()) {
            return $this->_readUntilLeaf($this->_nodes[$curnode['_child1']]);
        }
    
        return $this->_readUntilLeaf($this->_nodes[$curnode['_child0']]);
    }

    /**
     * We follow the Tree down from Root with the successive bits read
     * We know we have found the character as soon as we hit a leaf Node.
     *
     * @access private
     */
    private function _readToMakeOutput()
    {   
        for ($i = 0; $i < $this->_ofsize; $i++) {
            $this->_odata .= $this->_readUntilLeaf($this->_nodes[0]);
        }
    }
}

?>
