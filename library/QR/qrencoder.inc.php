<?php
/*=======================================================================
 // File:          QRENCODER.INC.PHP
 // Description:   QR Encodation classes
 // Created:       2008-07-23
 // Ver:           $Id: qrencoder.inc.php 1620 2009-07-16 19:16:24Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

require_once('qrexception.inc.php');
require_once('qr-utils.inc.php');
require_once('reed-solomon.inc.php');
require_once('qrcapacity.inc.php');
require_once('qrlayout.inc.php');
require_once('backend.inc.php');

// Main class to handle encodation of input data and the creation of the
// barcode matrix.

class QREncoder {

    // Encodation modes
    const MODE_ECI = 0;        // Not used
    const MODE_NUMERIC = 1;
    const MODE_ALPHANUM = 2;
    const MODE_BYTE = 3;
    const MODE_KANJI = 4;      // Not used
    const MODE_TERMINATOR = 8;
    const MODE_STRUCTAPPEND = 5; // Not used
    const MODE_FNC1_1 = 6; // Not used
    const MODE_FNC1_2 = 7; // Not used
    const RSPrimitivePol = 0x11d; // Primitive polynom used in QR RS-Codec for the reed solomon codes

    private $iDbgLevel = -1 ; // Level of debug information in string

    // Holds the final constructed bit pattern to be put in a data matrix
    public $iFinalBitArray = array();

    // Initial symbol bit array
    private $iSymbolBitArray = array(), $iSymbolBitArrayLen = 0;

    // The created codewords
    private $iCodeWords = array();

    // final interlevae code word sequence
    private $iFinalCodewordSequence = array();

    // Used in the decoding (only for verification purposes)
    private $iDecodedChars = array();
    private $iDecodeInfo = array(), $iEncodeInfo = array(), $iInfo = array(), $iErrDebugInfostr = "";
    private $iGeneralInfo = "";

    // Data to be encoded
    private $iData = null, $iOrigData = null;

    // What version of the QR matrix we will use
    private $iVersion = -1, $iOrigVersion = -1;

    // Selected error correction level
    private $iErrLevel = QRCapacity::ErrM;

    // Conversion tables for encodations
    private $iAlphaNumTable = array();
    private $iAlphaNumInvTable = array();

    // The bit patterns used to switch between encodation formats
    private $iModeIndicator = array(
    QREncoder::MODE_ECI => array(0, 1, 1, 1),
    QREncoder::MODE_NUMERIC => array(0, 0, 0, 1),
    QREncoder::MODE_ALPHANUM => array(0, 0, 1, 0),
    QREncoder::MODE_BYTE => array(0, 1, 0, 0),
    QREncoder::MODE_KANJI => array(1, 0, 0, 0),
    QREncoder::MODE_STRUCTAPPEND => array(0, 0, 1, 1),
    QREncoder::MODE_FNC1_1 => array(0, 1, 0, 1),
    QREncoder::MODE_FNC1_2 => array(1, 0, 0, 1),
    QREncoder::MODE_TERMINATOR => array(0, 0, 0, 0));

    // String version (for verification purposes)
    private $iModeIndicatorString = array(
    QREncoder::MODE_ECI => 'MODE_ECI',
    QREncoder::MODE_NUMERIC => 'MODE_NUMERIC',
    QREncoder::MODE_ALPHANUM => 'MODE_ALPHANUM',
    QREncoder::MODE_BYTE => 'MODE_BYTE',
    QREncoder::MODE_KANJI => 'MODE_KANJI',
    QREncoder::MODE_STRUCTAPPEND => 'MODE_STRUCTAPPEND',
    QREncoder::MODE_FNC1_1 => 'MODE_FNC1_1',
    QREncoder::MODE_FNC1_2 => 'MODE_FNC1_2',
    QREncoder::MODE_TERMINATOR => 'MODE_TERMINATOR');

    private $iNbrModes = 9;

    // Flag if we are doing manual or automatic encodation
    private $iManualEncoding = false;

    // Instance of te QRCapacity class
    private $iQRCapacity = null;

    // Instance of QRMask class
    private $iQRMask = null;

    // We remember the selected levelling mask used in the layout class here
    // so that we can include it in the general encoding information for verification
    // purposes (the original of this is stored in QRLayout::iMaskIdx). This value
    // is only available until the Enc() method have been run
    private $iSelectedLayoutMaskIdx = -1;

    // Constructs a new encoder for specified version and error correction capacity
    function __construct($aVersion = -1, $aErrLevel = QRCapacity::ErrM) {

        $this->SetSize($aVersion);

        // If error level is set < 0 this is interpretated as that the user
        // wants us to choose the level of error redundancy.
        if( $aErrLevel < 0  ) {
            if( $aVersion > 30 ) {
                $aErrLevel = QRCapacity::ErrH; // Use more error redundancy for large data
                $this->iGeneralInfo .= "Automatically set error level to H\n";
            }
            elseif( $aVersion > 20 ) {
                $aErrLevel = QRCapacity::ErrQ; // Use more error redundancy for large data
                $this->iGeneralInfo .= "Automatically set error level to Q\n";
            }
            else {
                $aErrLevel = QRCapacity::ErrM;
                $this->iGeneralInfo .= "Automatically set error level to M\n";
            }
        }

        $this->iErrLevel = $aErrLevel;
        // Initialize translation table for alphanumeric
        for($i = 0; $i < 10; ++$i) {
            $this->iAlphaNumTable[ord('0') + $i] = $i;
        }
        for($i = 0; $i < 26; ++$i) {
            $this->iAlphaNumTable[ord('A') + $i] = 10 + $i;
        }
        $punct = array(ord(' ') => 36, ord('$') => 37,
        ord('%') => 38, ord('*') => 39,
        ord('+') => 40, ord('-') => 41,
        ord('.') => 42, ord('/') => 43,
        ord(':') => 44);
        $this->iAlphaNumTable = $this->iAlphaNumTable + $punct;

        foreach($this->iAlphaNumTable as $key => $val) {
            $this->iAlphaNumInvTable[$val] = $key;
        }

        $this->iQRCapacity = QRCapacity::getInstance();
        $this->iQRMask = QRMask::getInstance();
    }

    // Convert all the information gathered on the encodation process to a string
    function __toString() {
        $r = '';
        $rb = implode($this->iSymbolBitArray);
        $r .= str_repeat('-',40)."\nEncodation details:\n".str_repeat('-',40)."\n\n";
        $errStr = array('L', 'M', 'Q', 'H');
        $r .= 'QR Version: ' . $this->iVersion . '-' . $errStr[$this->iErrLevel] . "\n";
        $r .= 'Data capacity: ' . $this->iQRCapacity->getNumData($this->iVersion, $this->iErrLevel) . "\n";
        $r .= 'Error codewords: ' . $this->iQRCapacity->getNumErr($this->iVersion, $this->iErrLevel) . "\n";
        $r .= 'Layout mask used (index): '.$this->iSelectedLayoutMaskIdx . "\n";
        if( is_string($this->iOrigData) )  {
            $data = 'Input string: '.$this->iOrigData;
        }
        else {
            $data = "Input strings (manual encodation schemas) :\n";
            $n = count($this->iOrigData);
            for($i=0; $i < $n; $i++) {
                $data .= " (" . $this->iOrigData[$i][0] . " : " . $this->iOrigData[$i][1] . ")\n" ;
            }
        }
        $r .= $data . "\n\n";

        $r .= "General info:\n-------------\n";
        $r .= $this->iGeneralInfo . "\n\n";

        $rb = str_split($rb, 50);
        $n = count($rb);

        if( $this->iDbgLevel >= 1 ) {
            $r .= "Encoding process:\n";
            $r .= $this->encodeToString() . "\n";

            $r .= "Output bits excluding pad byte(s) (len=" . $this->iSymbolBitArrayLen . "):\n";
            for($i = 0; $i < $n; ++$i) {
                $r .= $rb[$i] . "\n";
            }
        }

        if( $this->iDbgLevel >= 2 ) {
            $n = count($this->iCodeWords);
            $cw = '';
            for($i = 0; $i < $n; ++$i) {
                $cw .= sprintf('%08s (0x%02x)', decbin($this->iCodeWords[$i]), $this->iCodeWords[$i]);
                if(($i + 1) % 4 == 0) {
                    $cw .= ",\n";
                }
                elseif($i < $n-1) {
                    $cw .= ', ';
                }
            }

            $r .= "\nCodewords from bit sequence (len=$n):\n" . $cw . "\n";
            $r .= "Addition of Error correction code blocks.\n";
            $r .= $this->iErrDebugInfostr;
        }

        return $r;
    }

    // Set QR version
    function SetSize($aVersion) {
        if( $aVersion != -1 && $aVersion < 1 || $aVersion > 40 ) {
            //throw new QRException("QR Version specified as $aVersion. Version must be specified as a value in the range [1,40]");
            throw new QRExceptionL(1400,$aVersion);
        }
        $this->iVersion = $aVersion;

        // We also store the original version since the iVersion will be set to a
        // possible automatic determined version
        $this->iOrigVersion = $aVersion;
    }

    // Set level of debug version
    function SetDebugLevel( $aDbgLevel=FALSE ) {
        if( $aDbgLevel !== FALSE )
        $this->iDbgLevel = $aDbgLevel;
    }

    // Encode the data given as parameter and return the internal
    // representation for the data matrix - the layout
    function Enc($aData, $aManual = false) {

        if( empty($aData) ) {
            //throw new QRException('Input data to barcode is empty!');
            throw new QRExceptionL(1401);
        }

        // Reset all data structures
        $this->iOrigData = $aData ;
        $this->iData = '';
        $this->iVersion = $this->iOrigVersion;

        // Holds the final constructed bit pattern to be put in a data matrix
        $this->iFinalBitArray = array();

        // Initial symbol bit array
        $this->iSymbolBitArray = array();
        $this->iSymbolBitArrayLen = 0;

        // The created codewords
        $this->iCodeWords = array();

        // final interlevae code word sequence
        $this->iFinalCodewordSequence = array();

        // First some sanity check of input data
        if(is_array($aData)) {
            if($aManual == false) {
                //throw new QRException("Automatic encodation mode was specified but input data looks like specification for manual encodation.");
                throw new QRExceptionL(1402);
            }
            if(!is_array($aData[0])) {
                //throw new QRException("Was expecting an array of arrays as input data for manual encoding", -1);
                throw new QRExceptionL(1403);
            }
            // Check each entry in the input data
            $n = count($aData);
            for($i = 0; $i < $n; ++$i) {
                $nn = count($aData[$i]);
                if($nn != 2) {
                    //throw new QRException("Each input data array element must consist of two entries. Element $i has of $nn entries", -1);
                    throw new QRExceptionL(1404);
                }
                if(!is_numeric($aData[$i][0]) || !is_string($aData[$i][1])) {
                    // throw new QRException("Each input data array element must consist of two entries with first entry being the encodation constant and the second element the data string. Element $i is incorrect in this respect.", -1);
                    throw new QRExceptionL(1405,$i);
                }
            }
        }
        elseif(!is_string($aData)) {
            //throw new QRException("Was expecting either a string or an array as input data", -1);
            throw new QRExceptionL(1406);
        }
        elseif($aManual) {
            //throw new QRException("Manual encodation mode was specified but input data looks like specification for automatic encodation.");
            throw new QRExceptionL(1407);
        }
        // Now we have some valid data so do the encodations
        // Now it's time to autosize the symbol if no version was specified
        // Select the smallest version that can fit the generated number of
        // data codewords. Unfortunately we cannot calculate this in advance but
        // have no reason to try to do final bit placement until we find a sybmol size large
        // enough.
        if( $this->iVersion <= 0 ) {

            $this->iGeneralInfo .= "Automatic determination of the QR Version\n";

            // Make an educated guess on the minimum version we need to we don't waste
            // tmie searching too small versions.
            // We'll make a guess that we will have an average encodation ratio of 2.5
            // Since numerics have an 1:3 encodation ratio and alpha has 1:2 and byte 1:1
            // Worst case here is that we might for an all numeric data string select 1 version
            // larger than strictly necessary. But that is acceptable if the user hasn't specified
            // a size since the majoriy of data will be alphanumeric in real life examples
            if( is_string($aData) ) {
                $guess_codewordlen = floor(strlen($aData) / 2.5);
            }
            else {
                // ToDo: We could exactly calculate the needed length since we now
                // the encodation for each part in the manually specified data
                $n = count($aData);
                $guess_codewordlen = 0;
                for($i=0; $i < $n; $i++) {
                    $guess_codewordlen += floor(strlen($aData[$i][1]) / 2.5);
                }
            }

            $vers = 0;
            do {
                ++$vers;
                $nbrdata = $this->iQRCapacity->getNumData($vers, $this->iErrLevel);
            } while( $nbrdata <= $guess_codewordlen )  ;

            do {
                // Reset all internal data structures used
                $this->iVersion = $vers;
                $this->iData = $aData;
                $this->iSymbolBitArray = array();
                $this->iSymbolBitArrayLen = 0;
                $this->iCodeWords = array();
                $this->iEncodeInfo = array();

                $this->iGeneralInfo .= " ... Trying version $vers with ";

                // Try encoding with this symbol size
                if($aManual) {
                    $this->iGeneralInfo .= " manual encodation \n";
                    $this->doManual();
                }
                else {
                    $this->iGeneralInfo .= " automatic encodation \n";
                    $this->doAuto();
                }
                $this->bitPadSymbols();
                $this->splitInBytes();

                $nbrdata = $this->iQRCapacity->getNumData($vers, $this->iErrLevel);
                $dlen = count($this->iCodeWords);
                ++$vers;
            }
            while ($vers <= 40 && $nbrdata < $dlen);

            if($nbrdata < $dlen) {
                //throw new QRException("Input data too large to fit into one QR Symbol", -1);
                throw new QRExceptionL(1408);
            }
        }
        else {
            $this->iGeneralInfo .= "Using manual specified version = {$this->iVersion} with ";

            $this->iData = $aData;
            if($aManual) {
                $this->iGeneralInfo .= " manual encodation \n";
                $this->doManual();
            }
            else {
                $this->iGeneralInfo .= " automatic encodation \n";
                $this->doAuto();
            }
            $this->bitPadSymbols();
            $this->splitInBytes();

            $nbrdata = $this->iQRCapacity->getNumData($this->iVersion, $this->iErrLevel);
            $dlen = count($this->iCodeWords);
            if($dlen > $nbrdata) {
                // throw new QRException("The selected symbol {$this->iVersion} is too small to fit the specied data and selected error correction level.", -1);
                throw new QRExceptionL(1409,$this->iVersion);
            }
        }

        // Add Pad codewords to completely fill the available data capacity
        $this->addPadCodewords();

        // Add error correction codewords
        $this->addErrorCorrection();

        // Create the matrix and put the bitstream in the matrix
        $l = new QRMatrixLayout($this->iVersion,$this->iErrLevel);
        $l->SetDebugLevel($this->iDbgLevel);
        $l->placeBitStream($this->iFinalBitArray);

        // Select the masking pattern and apply it to the matrix
        $l->selectApplyMask();
        $this->iSelectedLayoutMaskIdx = $l->iMaskIdx; // Remember the selected mask for _toString() method

        // Add possible Version information
        $l->addVersionInfo();

        // Flatten the information in the layout so we convert everything to
        // only 1:s or 0:s in the representation (until now we have separated the different
        // zones for more efficient verification)
        $l->flatten();

        // Return the representation of the layout
        return $l;
    }

    // Add error correction code words to the created data codewords
    function addErrorCorrection() {
        // Now take the constructed codewords so far and add the
        // requried number of error correcting words at the end.
        // Depending on the symbol version and error corrction level
        // we normally have break up the whole data in blocks and add
        // error correction to each block. The required block structure
        // is specified in the standard. For small versions the whole data is
        // considered to be one block which makes it easy to start with.
        // Get block structur specification
        // (Total nbr blocks, (total words in block, data words in block, error words in block))
        $blks = $this->iQRCapacity->getBlockStructure($this->iVersion, $this->iErrLevel);
        // We can have all same block structure or we can have two different
        // block structures so we need to find ut which
        $ntype = count($blks);
        $this->iInfo = array();

        $blocks = array();
        $dataIdx = 0;
        $availableCodewords = count($this->iCodeWords);
        $this->iErrDebugInfostr = '';
        $blockoffset = 0;
        $blockinfo = array();
        $totalwords = 0;
        if($ntype == 1 || $ntype == 2) {
            for($k = 0; $k < $ntype;++$k) {
                // -------------------------------------------------------------------------------
                // Handle each block structure
                // -------------------------------------------------------------------------------
                // 1. Fird find out how many block we need to use
                $nbrBlocks = $blks[$k][0];
                // 2. Find out total number of words (data+error correction) is in each block
                $nbrWordsInBlock = $blks[$k][1][0];
                // 3. Find out data words in each block
                $nbrDataInBlock = $blks[$k][1][1];
                // 4. Find out error correcting words in each block
                $nbrErrInBlock = $blks[$k][1][2];
                // Prepare RS coder
                $rs = new ReedSolomon($nbrErrInBlock, QREncoder::RSPrimitivePol);
                // Now split the data words in block and add the error correcting words to each block
                for($i = 0; $i < $nbrBlocks; ++$i) {
                    // Extract the needed number of codewords for this block
                    for($j = 0; $j < $nbrDataInBlock; ++$j) {
                        if($dataIdx >= $availableCodewords) {
                            //throw new QRException('Trying to read past the last available codeword in block split', -1);
                            throw new QRExceptionL(1410);
                        }
                        $blocks[$i + $blockoffset][$j] = $this->iCodeWords[$dataIdx++];
                    }
                    // For easier split we save the structure of each block so we can easily find
                    // out info on each block we we loop throgh each block when we constructre the
                    // final codeword sequence
                    $blockinfo[$i + $blockoffset] = array($nbrWordsInBlock, $nbrDataInBlock, $nbrErrInBlock);
                    // Now append the requested number of error correcting codewords to the block
                    $rs->append($blocks[$i + $blockoffset]);
                    // Keep a sanity count on the total number of words (data+error correction)
                    $totalwords += count($blocks[$i + $blockoffset]);

                    $this->iErrDebugInfostr .= "\n=============\nSymbol block: Type=$k, number=" . ($i + 1) . "/$nbrBlocks (Tot=$nbrWordsInBlock, Data=$nbrDataInBlock, Err=$nbrErrInBlock)\n=============\n";
                    $err = 0;
                    for($j = 0; $j < $nbrWordsInBlock; ++$j) {
                        $fmt = '%08s (%02x)';
                        $this->iErrDebugInfostr .= sprintf($fmt, decbin($blocks[$i + $blockoffset][$j]), $blocks[$i + $blockoffset][$j]);

                        if($j < $nbrWordsInBlock-1)
                        $this->iErrDebugInfostr .= ', ';
                        if($j == $nbrDataInBlock-1) {
                            $this->iErrDebugInfostr .= "\nError correction word in block:";
                        }
                        if($j >= $nbrDataInBlock) {
                            ++$err;
                            if($err % 4 == 0)
                            $this->iErrDebugInfostr .= "\n";
                        }
                        elseif((($j + 1) % 4 == 0) || ($j == $nbrDataInBlock-1) ) {
                            $this->iErrDebugInfostr .= "\n";
                        }
                    }
                    $this->iErrDebugInfostr .= "\n";
                }
                $blockoffset += $nbrBlocks;
            }
        }
        else {
            //throw new QRException('Internal error: Expected 1 or 2 as the number of block structures.');
            throw new QRExceptionL(1411);
        }
        // Now when we have all block we need to create the final message codeword sequence which we do by
        // taking data and error correction codewords from each block in turn: data block
        // 1, codeword 1; data block 2, codeword 1; data block 3, codeword 1; and similarly to data block n - 1, final
        // codeword; data block n, final codeword; then error correction block 1, codeword 1, error correction block 2,
        // codeword 1, ... and similarly to error correction block n - 1, final codeword; error correction block n, final codeword.
        // QR Code symbols contain data and error correction blocks which always exactly fill the symbol codeword capacity.
        // In certain versions, however, there may be a need for 3, 4 or 7 Remainder Bits to be appended to the final
        // message bit stream in order exactly to fill the number of modules in the encoding region.
        $totalNbrOfBlocks = $blockoffset;
        // Collect all data words
        $this->iFinalCodewordSequence = array();
        $idx = 0;
        $cwidx = 0;
        while($idx < $availableCodewords) {
            for($i = 0; $i < $totalNbrOfBlocks; ++$i) {
                if($cwidx < $blockinfo[$i][1])
                $this->iFinalCodewordSequence[$idx++] = $blocks[$i][$cwidx];
            }
            ++$cwidx;
        }
        // Collect all error correction words
        $erridx = 0;
        while($erridx < $nbrErrInBlock) {
            for($i = 0; $i < $totalNbrOfBlocks; ++$i) {
                $this->iFinalCodewordSequence[$idx++] = $blocks[$i][ $blockinfo[$i][1] + $erridx ];
            }
            ++$erridx;
        }

        $n = count($this->iFinalCodewordSequence);
        // Do a sanity check that we have the correct total number of data+error codewords
        if($totalwords != $n) {
            throw new QRExeption('Internal error: Number of total codewords does not match after split!!');
        }
        // Create the final bitsequence to be put in the symbol matrix
        $this->iFinalBitArray = array();
        Utils::ByteArray2Bits($this->iFinalCodewordSequence, $this->iFinalBitArray);

        $this->iErrDebugInfostr .= "\n=========== INTERLEAVED CODE SEQUENCE (Total=$n =============\n";
        for($i = 0; $i < $n; ++$i) {
            // $fmt = '%08s (%02x)';
            $v = $this->iFinalCodewordSequence[$i];
            $this->iErrDebugInfostr .= sprintf($fmt, decbin($v), $v);
            if($i < $n-1)
            $this->iErrDebugInfostr .= ', ';
            if(($i + 1) % 4 == 0)
            $this->iErrDebugInfostr .= "\n";
        }
        $this->iErrDebugInfostr .= "\n";
    }

    // Add pad codewords so that we fill the stream to maximum capacity
    function addPadCodewords() {
        $capacity = $this->iQRCapacity->getNumData($this->iVersion, $this->iErrLevel);
        $nbrPad = $capacity - count($this->iCodeWords);
        if($nbrPad < 0) {
            //throw new QRException('Too many codewords for chosen symbol version');
            throw new QRExceptionL(1412);
        }

        $pads = array('11101100', '00010001');

        for($i = 0; $i < $nbrPad; ++$i) {
            $this->pushEncodeInfo(8, bindec($pads[$i % 2]), 'PAD_BYTE');
            $this->iCodeWords[] = bindec($pads[$i % 2]);
        }
    }

    // Add zero bits so that there is a whole number of 8-bit blocks
    function bitPadSymbols() {
        if ($this->iSymbolBitArrayLen % 8 > 0) {
            $n = 8 - ($this->iSymbolBitArrayLen % 8);
            $fill = array_fill(0, $n, 0);
            $this->iSymbolBitArray = array_merge($this->iSymbolBitArray, $fill);
            $this->iSymbolBitArrayLen += $n;

            $this->pushEncodeInfo($n, str_repeat('0', $n), 'PAD_BITS');
        }
    }

    // Split the created bit array into codewords
    function splitInBytes() {
        $this->iCodeWords = array();
        if(count($this->iSymbolBitArray) % 8 > 0) {
            //throw new QRException('splitInBytes: Expected an even number of 8-bit blocks');
            throw new QRExceptionL(1413);
        }
        $this->iCodeWords = array_chunk($this->iSymbolBitArray, 8);
        $this->iCodeWords = array_map('implode', $this->iCodeWords);
        $this->iCodeWords = array_map('bindec', $this->iCodeWords);
    }

    // Create a string of all gathered decoding info (used for verification)
    function decodeToString() {
        $r = sprintf("%-8s%-15s%-15s%-20s\n", 'Length', 'Bits', 'Value', 'Comment');
        $r .= str_repeat('-', 65) . "\n";
        for($i = 0; $i < $n; ++$i) {
            $b = sprintf('%0' . $this->iDecodeInfo[$i][0] . 's', decbin($this->iDecodeInfo[$i][1]));
            $r .= sprintf("%02s      %-15s%-15s%-20s\n", $this->iDecodeInfo[$i][0], $b, $this->iDecodeInfo[$i][1], $this->iDecodeInfo[$i][2]);
        }

        return $r;
    }

    // Create a string of all gathered encoding info (used for verification)
    function encodeToString() {
        $n = count($this->iEncodeInfo);
        $r = sprintf("%-8s%-25s%-20s\n", 'Length', 'Value', 'Comment');
        $r .= str_repeat('-', 65) . "\n";
        for($i = 0; $i < $n; ++$i) {
            $r .= sprintf("%02s      %-28s%-20s\n", $this->iEncodeInfo[$i][0], $this->iEncodeInfo[$i][1], $this->iEncodeInfo[$i][2]);
        }

        return $r;
    }

    // Store decode verification data
    function pushDecodeInfo($aBitLen, $aVal, $aComment) {
        $this->iDecodeInfo[] = array($aBitLen, $aVal, $aComment);
    }

    // Store encode verification data
    function pushEncodeInfo($aBitLen, $aVal, $aComment) {
        $this->iEncodeInfo[] = array($aBitLen, $aVal, $aComment);
    }

    // Store general debug info
    function pushInfo($aBitLen, $aVal, $aComment) {
        $this->iInfo[] = array($aBitLen, $aVal, $aComment);
    }

    // Decode the created symbol bit array (used for verification)
    /*
    function decode($aData = null) {
    if($aData == null)
    $aData = $this->iSymbolBitArray;
    $d = new DataStorage($aData);

    $this->iDecodeInfo = array();

    $n = $d->Remaining();
    while(! $d->isEnd()) {
    if($d->Remaining() < 4)
    throw new QRExeption('Decode: Expecting four bit mode indicator');
    // Get mode indicator (4 bits always)
    $mode = $d->GetSlice(4);
    $mode = implode($mode);
    $i = 0;
    // Identify this mode indicator
    while($i < $this->iNbrModes) {
    $_m = implode($this->iModeIndicator[$i]);
    if($mode == $_m)
    break;
    else
    ++$i;
    }
    if($i >= $this->iNbrModes) {
    throw new QRException('Unknown mode indicator');
    }
    $modeIndicator = $i;

    $this->pushDecodeInfo(4, bindec(implode($this->iModeIndicator[$i])), $this->iModeIndicatorString[$i]);

    if($i == QREncoder::MODE_TERMINATOR) {
    $padbits = $d->Remaining();
    if($padbits > 0) {
    $pad = $d->GetSlice($padbits);
    $this->pushDecodeInfo($padbits, '-', 'PAD_BITS');
    }
    return;
    }
    // Get the character count indicator
    $cntLen = $this->getCountBits($modeIndicator);
    if($cntLen > $d->Remaining())
    throw new QRExeption("Decode: Expecting $cntLen bit long character count");

    $cnt = $d->GetSlice($cntLen);
    $cnt = bindec(implode($cnt));

    $this->pushDecodeInfo($cntLen, $cnt, 'Symbol count');
    switch($modeIndicator) {
    case QREncoder::MODE_ECI :
    // Consume $cnt number of characters in this encoding
    break;

    case QREncoder::MODE_NUMERIC:
    // Consume $cnt number of characters in this encoding
    $n3 = floor($cnt / 3);
    $n2 = floor($cnt - $n3 * 3);
    if($n2 == 2)
    $nbits = 7;
    else
    $nbits = 4;

    for($i = 0; $i < $n3; ++$i) {
    $word = bindec(implode($d->GetSlice($cntLen)));
    array_push($this->iDecodedChars, strval($d1 = floor($word / 100)));
    array_push($this->iDecodedChars, strval($d2 = floor(($word-100 * $d1) / 10)));
    array_push($this->iDecodedChars, strval($d3 = floor($word % 10)));
    $this->pushDecodeInfo(10, $word, "Data word ('$d1','$d2','$d3')");
    }

    $d1 = $d2 = $d3 = '';
    $word = bindec(implode($d->GetSlice($nbits)));
    if($nbits == 7) {
    array_push($this->iDecodedChars, strval($d1 = floor($word / 10)));
    array_push($this->iDecodedChars, strval($d2 = floor($word % 10)));
    $this->pushDecodeInfo(7, $word, "Data word ('$d1','$d2')");
    }
    else {
    array_push($this->iDecodedChars, strval($d1 = floor($word % 10)));
    $this->pushDecodeInfo(4, $word, "Data word ('$d1')");
    }
    break;

    case QREncoder::MODE_ALPHANUM:
    $n2 = floor($cnt / 2);
    $n = floor($cnt % 2);
    for($i = 0; $i < $n2; ++$i) {
    $word = bindec(implode($d->GetSlice(11)));
    array_push($this->iDecodedChars, $c1 = chr($dd1 = $this->iAlphaNumInvTable[$d1 = floor($word / 45)]));
    array_push($this->iDecodedChars, $c2 = chr($dd2 = $this->iAlphaNumInvTable[$d2 = floor($word-45 * $d1)]));
    $this->pushDecodeInfo(11, $word, "Data word ('$c1',$d1), ('$c2',$d2)");
    }
    if($n == 1) {
    $word = bindec(implode($d->GetSlice(6)));
    array_push($this->iDecodedChars, $c1 = chr($dd1 = $this->iAlphaNumInvTable[$d1 = $word]));
    $this->pushDecodeInfo(6, $word, "Data word ('$c1',$d1)");
    }
    break;

    case QREncoder::MODE_BYTE:
    $n = $cnt;
    for($i = 0; $i < $n; ++$i) {
    $word = bindec(implode($d->GetSlice(8)));
    array_push($this->iDecodedChars, $word);
    $this->pushDecodeInfo(8, $word, "Data word ($word)");
    }

    break;

    case QREncoder::MODE_KANJI:
    throw new QRException("Decoding MODE_KANJI not yet supported",-1);
    break;

    case QREncoder::MODE_TERMINATOR:
    return;
    break;

    case QREncoder::MODE_STRUCTAPPEND:
    throw new QRException("Decoding MODE_STRUCTAPPEND not yet supported",-1);
    break;

    case QREncoder::MODE_FNC1_2:
    throw new QRException("Decoding using MODE_FNC1_2 not yet supported",-1);
    break;

    case QREncoder::MODE_FNC1_1:
    throw new QRException("Decoding using MODE_FNC1_1 not yet supported",-1);
    break;
    }
    }
    }
    */
     
    // Return the number of bits to use for the character count
    // Table 3. In the specs
    function getCountBits($aMode) {
        $version = $this->iVersion;
        $cntBits0109 = array(
        QREncoder::MODE_NUMERIC => 10, QREncoder::MODE_ALPHANUM => 9,
        QREncoder::MODE_BYTE => 8,     QREncoder::MODE_KANJI => 8,
        QREncoder::MODE_TERMINATOR => 0 );
        $cntBits1026 = array(
        QREncoder::MODE_NUMERIC => 12, QREncoder::MODE_ALPHANUM => 11,
        QREncoder::MODE_BYTE => 16,    QREncoder::MODE_KANJI => 10,
        QREncoder::MODE_TERMINATOR => 0 );
        $cntBits2740 = array(
        QREncoder::MODE_NUMERIC => 14, QREncoder::MODE_ALPHANUM => 13,
        QREncoder::MODE_BYTE => 16,    QREncoder::MODE_KANJI => 12,
        QREncoder::MODE_TERMINATOR => 0 );

        if    ($version >= 1  && $version <= 9)   return $cntBits0109[$aMode];
        elseif($version >= 10 && $version <= 26)  return $cntBits1026[$aMode];
        elseif($version >= 27 && $version <= 40)  return $cntBits2740[$aMode];
        else {
            // throw new QRException("Internal error: getCountBits() illegal version number (=$version).", -1);
            throw new QRExceptionL(1414,$version);
        }
    }

    // Encode the data with user specified encodation schema
    function doManual() {
        $nchunks = count($this->iData);
        $originalData = $this->iData;
        for($i = 0; $i < $nchunks; ++$i) {
            $encoding = $originalData[$i][0];
            $this->iData = new DataStorage($originalData[$i][1]);
            switch($encoding) {
                case QREncoder::MODE_NUMERIC:
                    $digitsleft = $this->iData->Remaining(DataStorage::DIGIT);
                    if( $digitsleft > 0 )
                    $this->encodeNumeric();
                    else {
                        // throw new QRException("Manually specified encodation schema MODE_NUMERIC has no data that can be encoded using this schema.");
                        throw new QRExceptionL(1415);
                    }
                    break;

                case QREncoder::MODE_ALPHANUM:
                    $alnumleft = $this->iData->Remaining(DataStorage::ALPHANUM);
                    if( $alnumleft > 0 )
                    $this->encodeAlphaNum();
                    else {
                        // throw new QRException("Manually specified encodation schema MODE_ALPHANUM has no data that can be encoded using this schema.");
                        throw new QRExceptionL(1416);
                    }
                    break;

                case QREncoder::MODE_BYTE:
                    $byteleft = $this->iData->Remaining(DataStorage::BYTE);
                    if( $byteleft > 0 )
                    $this->encodeByte();
                    else {
                        // throw new QRException("Manually specified encodation schema MODE_BYTE has no data that can be encoded using this schema.");
                        throw new QRExceptionL(1417);
                    }
                    break;

                default:
                    // throw new QRException('Unsupported encodation schema specified ($encoding)', -1);
                    throw new QRExceptionL(1418,$encoding);
                    break;
            }
        }

        // Check that we don't have any characters letf that we haven't been able to encode
        if( !$this->iData->isEnd() ) {
            // throw new QRException("Found character in data stream that cannot be encoded with the selected manual encodation mode.");
            throw new QRExceptionL(1419);
        }

        $this->PutBitSequence($this->iModeIndicator[QREncoder::MODE_TERMINATOR]);
        $this->pushEncodeInfo(4, implode($this->iModeIndicator[QREncoder::MODE_TERMINATOR]), "MODE_TERMINATOR");
    }

    // Encode the data with automatic determined encodatinos schemas
    function doAuto() {
        $this->iData = new DataStorage($this->iData);

        // Find out initial starting encoding
        // This is a slightly simplified finding of the initial encodation
        // since we are not taking into account the next encodation mode after
        // the first one
        $nbrdigits = $this->iData->Remaining(DataStorage::DIGIT);
        $nbralpha = $this->iData->Remaining(DataStorage::ALPHA);
        $nbrbyte = $this->iData->Remaining(DataStorage::BYTEUNIQUE);
        // $nbrkanji = $this->iData->Remaining(DataStorage::KANJI);

        if( $nbrbyte > 0 )
            $currentEncoding = QREncoder::MODE_BYTE;
        elseif( $nbrdigits >= 7 )
            $currentEncoding = QREncoder::MODE_NUMERIC;
        else
            $currentEncoding = QREncoder::MODE_ALPHANUM;

        // Encode the entire data string switching modes as necessary
        while( $currentEncoding != QREncoder::MODE_TERMINATOR ) {
            switch($currentEncoding) {
                case QREncoder::MODE_NUMERIC:
                    $currentEncoding = $this->encodeNumeric();
                    break;

                case QREncoder::MODE_ALPHANUM:
                    $currentEncoding = $this->encodeAlphaNum(false);
                    break;

                case QREncoder::MODE_BYTE:
                    $currentEncoding = $this->encodeByte(false);
                    break;

                case QREncoder::MODE_KANJI:
                    //throw new QRException("Encodation using KANJI mode not yet supported.");
                    throw new QRExceptionL(1420);

                    //$currentEncoding = $this->encodeKanji(false);
                    break;

                default:
                    // throw new QRException("Internal error: Unsupported encodation mode doAuto().");
                    throw new QRExceptionL(1421);
            }
        }

        if( !$this->iData->isEnd() ) {
            // This error can not really happen since the worst case is that the automatic encodation
            // switches to byte mode and then all character in a data stream can be encoded (unless
            // we have a 2-byte string encodation schema)
            // throw new QRException("Found unknown characters in the data strean that can't be encoded with any available encodation mode.") ;
            throw new QRExceptionL(1422);
        }

        $this->PutBitSequence($this->iModeIndicator[QREncoder::MODE_TERMINATOR]);
        $this->pushEncodeInfo(4, implode($this->iModeIndicator[QREncoder::MODE_TERMINATOR]), "MODE_TERMINATOR");
    }

    // Encode a squence in numeric encodation
    function encodeNumeric() {
        $cnt = 0;
        $bits = array();
        $this->iInfo = array();

        $digitsleft = $this->iData->Remaining(DataStorage::DIGIT);
        while( $digitsleft > 0 ) {
            $tmpbits = array();
            if ($digitsleft >= 3) {
                $val = ($d1 = $this->iData->Get()) * 100;
                $val += ($d2 = $this->iData->Get()) * 10;
                $val += ($d3 = $this->iData->Get());
                Utils::Word2Bits($val, $tmpbits, 10);
                $cnt += 3;

                $this->pushInfo(10, $val, "3 digits ($d1,$d2,$d3) in MODE_NUMERIC");
            }
            elseif ($digitsleft == 2) {
                $val = ($d1 = $this->iData->Get()) * 10;
                $val += ($d2 = $this->iData->Get());
                Utils::Word2Bits($val, $tmpbits, 7);
                $cnt += 2;

                $this->pushInfo(7, $val, "2 digits ($d1,$d2) in MODE_NUMERIC");
            }
            elseif ($digitsleft == 1) {
                $val = ($d1=$this->iData->Get());
                Utils::Word2Bits($val, $tmpbits, 4);
                $cnt += 1;

                $this->pushInfo(4, $d1, "1 digit ($d1) in MODE_NUMERIC");
            }
            $bits = array_merge($bits, $tmpbits);
            $digitsleft = $this->iData->Remaining(DataStorage::DIGIT);
        }

        $this->StoreBitsAndCounter($cnt,$bits,QREncoder::MODE_NUMERIC);
         
        // Determine the next encodation mode
        $alnumleft = $this->iData->Remaining(DataStorage::ALPHANUM);
        $byteleft = $this->iData->Remaining(DataStorage::BYTEUNIQUE);
        if( $byteleft > 0 )
            $nextmode = QREncoder::MODE_BYTE;
        elseif( $alnumleft > 0 )
            $nextmode = QREncoder::MODE_ALPHANUM;
        else
            $nextmode = QREncoder::MODE_TERMINATOR;
        return $nextmode;
    }

    // Encode a sequence in alphanumeric encodation
    function encodeAlphaNum($aGreedy = true) {
        $cnt = 0;
        $bits = array();
        $this->iInfo = array();

        $alnumleft = $this->iData->Remaining(DataStorage::ALPHANUM);
        $switchbyte = $switchnumeric = false;
        while ( $alnumleft > 0 && !$switchbyte && !$switchnumeric ) {
            $tmpbits = array();
            if ($alnumleft >= 2) {
                $c1 = $this->iData->GetVal();
                $val = $this->iAlphaNumTable[$c1] * 45;
                $c2 = $this->iData->GetVal();
                $val += $this->iAlphaNumTable[$c2];
                Utils::Word2Bits($val, $tmpbits, 11);
                $cnt += 2;

                $this->pushInfo(11, $val, "2 characters (".chr($c1).",".chr($c2).") in MODE_ALPHANUM");
            }
            else {
                $c1 = $this->iData->GetVal();
                $val = $this->iAlphaNumTable[$c1];
                Utils::Word2Bits($val, $tmpbits, 6);
                $cnt += 1;

                $this->pushInfo(6, $val, "1 character (".chr($c1).") in MODE_ALPHANUM");
            }
            $bits = array_merge($bits, $tmpbits);

            if( !$aGreedy ) {
                $switchbyte = $this->iData->Remaining(DataStorage::BYTEUNIQUE) >= 1;
                $switchnumeric = $this->iData->Remaining(DataStorage::DIGIT) >= 13;
            }
            $alnumleft = $this->iData->Remaining(DataStorage::ALPHANUM);
        }

        $this->StoreBitsAndCounter($cnt,$bits,QREncoder::MODE_ALPHANUM);

        // Determine the next encodation mode
        if( $switchbyte )
            $nextmode = QREncoder::MODE_BYTE;
        elseif( $switchnumeric )
            $nextmode = QREncoder::MODE_NUMERIC;
        else
            $nextmode = QREncoder::MODE_TERMINATOR;
        return $nextmode;
    }

    // Encode a squence in byte encodation
    function encodeByte($aGreedy = true) {
        $cnt = 0;
        $bits = array();
        $this->iInfo = array();

        $bytesleft = $this->iData->Remaining(DataStorage::BYTE);
        $switchalnum = $switchnumeric = false;
        while ( $bytesleft > 0 && !$switchalnum && !$switchnumeric ) {
            $val = $this->iData->GetVal();
            $tmpbits = array();
            Utils::Word2Bits($val, $tmpbits, 8);
            ++$cnt;

            $hval = sprintf('%02x', $val);
            $this->pushInfo(11, $val, "1 byte ($val = $hval) in MODE_BYTE");

            $bits = array_merge($bits, $tmpbits);

            if( !$aGreedy ) {
                $switchalnum = $this->iData->Remaining(DataStorage::ALPHA) >= 11;
                $switchnumeric = $this->iData->Remaining(DataStorage::DIGIT) >= 6;
            }

            $bytesleft = $this->iData->Remaining(DataStorage::BYTE);
        }

        $this->StoreBitsAndCounter($cnt,$bits,QREncoder::MODE_BYTE);

        // Determine the next encodation mode
        if( $switchnumeric )
            $nextmode = QREncoder::MODE_NUMERIC;
        elseif( $switchalnum )
            $nextmode = QREncoder::MODE_ALPHANUM;
        else
            $nextmode = QREncoder::MODE_TERMINATOR;
        return $nextmode;
    }

    /* Encode a squence in kanji encodation
     function encodeKanji($aGreedy = true) {
     $cnt = 0;
     $bits = array();
     $this->iInfo = array();

     $kanjileft = $this->iData->Remaining(DataStorage::KANJI);
     while ( $kanjileft > 0 ) {
     $val = $this->iData->GetVal();
     if( $val >= 0x8140 && $val <= 0x9ffc ) {
     $val -= 0x8140;
     }
     elseif( $val >= 0xe040 && $val <= 0xebbf) {
     $val -= 0xe040;
     }
     $val = (($val & 0xff00) >> 8) * 0xc0 + ($val & 0xff);

     $tmpbits = array();
     Utils::Word2Bits($val, $tmpbits, 13);
     ++$cnt;

     $hval = sprintf('%02x', $val);
     $this->pushInfo(13, $val, "1 Kanji character as ($val = $hval) in MODE_KANJI");

     $bits = array_merge($bits, $tmpbits);

     $kanjileft = $this->iData->Remaining(DataStorage::KANJI);
     }

     $this->StoreBitsAndCounter($cnt,$bits,QREncoder::MODE_KANJI);

     // Once we are in Kanji mode we will consume all remaining characters in the string
     // so the only possible next mode is termination
     $nextmode = QREncoder::MODE_TERMINATOR;
     return $nextmode;
     }
     */

    // Helper method for the encode methods above
    function StoreBitsAndCounter($aCnt,&$aBits,$aMode) {
        // Store the bit sequence
        $cntBits = array();
        Utils::Word2Bits($aCnt, $cntBits, $this->getCountBits($aMode));
        $this->PutBitSequence($this->iModeIndicator[$aMode]);
        $this->PutBitSequence($cntBits);
        $this->PutBitSequence($aBits);

        // Write debug information about the encoding process
        $this->pushEncodeInfo(4, implode($this->iModeIndicator[$aMode]), $this->iModeIndicatorString[$aMode]);
        $this->pushEncodeInfo($this->getCountBits($aMode), implode($cntBits), "Counter (=$aCnt)");
        $b = implode($aBits);
        if(strlen($b) > 15) {
            $b = substr($b, 0, 15) . '... ';
        }
        $this->pushEncodeInfo(count($aBits), $b, 'Bit sequence for '.$this->iModeIndicatorString[$aMode]);
        $ni = count($this->iInfo);
        for($i = 0; $i < $ni; ++$i) {
            $b = array();
            Utils::Word2Bits($this->iInfo[$i][1], $b, $this->iInfo[$i][0]);
            $this->pushEncodeInfo($this->iInfo[$i][0], '(' . implode($b) . ')', $this->iInfo[$i][2]);
        }
    }

    // Put the specified bit sequence in the output bit array
    function putBitSequence(&$aBitSeq) {
        $this->iSymbolBitArray = array_merge($this->iSymbolBitArray, $aBitSeq);
        $this->iSymbolBitArrayLen += count($aBitSeq);
    }
}

// Helper class to manipulate a data store to handle reading of an input stream
// of data and selecting data depending on its type.
// ToDo: This is not yet a multibyte safe data store !! Needs to be expanded
// to properly handle SHIFT-JIS encoded Kanji
class DataStorage {
    const DIGIT = 1;
    const ALPHANUM = 2;
    const ALPHA = 3;
    const BYTE = 4;
    const BYTEUNIQUE = 5;
    const KANJI = 6;

    private $iData = array(), $iDataLen = 0, $iDataIdx = 0;
    function __construct($aData) {
        if(is_string($aData)) {
            $this->iData = str_split($aData);
        }
        else {
            $this->iData = $aData;
        }

        $this->iDataLen = count($this->iData);
        $this->iDataIdx = 0;
    }

    function __toString() {
        return implode($this->iData);
    }

    function Init() {
        $this->iDataIdx = 0;
    }

    function Len() {
        return $this->iDataLen;
    }

    function isDigit($aChar) {
        return ctype_digit($aChar);
    }

    function isAlnum($aChar) {
        return ctype_digit($aChar) || $this->isAlpha($aChar);
    }

    function isAlpha($aChar) {
        $found = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-/:', $aChar);
        if( $found === false )
        return false;
        else
        return true;
    }

    function isByte($aChar, $aUnique = false) {
        if(!$aUnique) {
            return ord($aChar) < 256;
        }
        else {
            return ord($aChar) < 256 && !$this->isAlnum($aChar);
        }
    }

    function Remaining($aType = null) {
        if($aType === null) {
            return $this->iDataLen - $this->iDataIdx ;
        }

        $idx = $this->iDataIdx;
        switch($aType) {
            case DataStorage::DIGIT:
                while($idx < $this->iDataLen && $this->isDigit($this->iData[$idx]))
                    ++$idx;
                break;

            case DataStorage::ALPHANUM:
                while($idx < $this->iDataLen && $this->isAlnum($this->iData[$idx]))
                    ++$idx;
                break;

            case DataStorage::ALPHA:
                while($idx < $this->iDataLen && $this->isAlpha($this->iData[$idx]))
                    ++$idx;
                break;

            case DataStorage::BYTE:
                while($idx < $this->iDataLen && $this->isByte($this->iData[$idx]))
                    ++$idx;
                break;

            case DataStorage::BYTEUNIQUE:
                while($idx < $this->iDataLen && $this->isByte($this->iData[$idx], true))
                    ++$idx;
                break;

            case DataStorage::KANJI:
                // throw new QRException("Kanji character set not yet supported.");
                throw new QRExceptionL(1423);
                break;

            default:
                //throw new QRException("Internal error: Unsupported character mode ($aType) DataStorage::Remaining()");
                throw new QRExceptionL(1424,$aType);
                break;
        }
        return $idx - $this->iDataIdx ;
    }

    function isEnd() {
        return $this->iDataIdx >= $this->iDataLen;
    }

    function GetSlice($aLen, $aType = null) {
        $l = $this->Remaining($aType);
        if($aLen > $l) {
            //throw new QRException('DataStorage:: Trying to extract slice of len=' . $aLen . ' (with type ' . $aType . ') when there are only ' . $l . ' elements left', -1);
            throw new QRExceptionL(1425,$aLen,$aType,$l);
        }
        $d = array();
        for($i = 0; $i < $aLen; ++$i) {
            $d[$i] = $this->iData[$this->iDataIdx++] ;
        }
        return $d;
    }

    function GetVal($aType = null) {
        return ord($this->Get($aType));
    }

    function Get($aType = null) {
        if ($this->iDataIdx >= $this->iDataLen) {
            //throw new QRException('Trying to read past input data length', -2);
            throw new QRExceptionL(1426);
        }
        else {
            if($aType === null) {
                return $this->iData[$this->iDataIdx++];
            }
            else {
                $c = $this->iData[$this->iDataIdx++];
                switch($aType) {
                    case DataStorage::DIGIT:
                        if($this->isDigit($c))
                        return $c;
                        break;

                    case DataStorage::ALPHANUM:
                        if($this->isAlnum($c))
                        return $c;
                        break;

                    case DataStorage::BYTEUNIQUE:
                        if($this->isByte($c, true))
                        return $c;
                        break;

                    case DataStorage::BYTE:
                        if($this->isByte($c))
                        return $c;
                        break;

                    default:
                        //throw new QRException('Expected either DIGIT, ALNUM or BYTE but found ASCII code=' . ord($c), -3);
                        throw new QRExceptionL(1427,ord($c));
                        break;
                }
            }
        }
    }

    function Peek($aLookAhead = 0) {
        if ($this->iDataIdx + $aLookAhead >= $this->iDataLen) {
            //throw new QRException('Trying to peek past input data length', -3);
            throw new QRExceptionL(1428);
        }
        else {
            return $this->iData[$this->iDataIdx + $aLookAhead];   
        }
    }
}

?>
