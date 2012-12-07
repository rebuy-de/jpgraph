<?php
/*=======================================================================
// File: 	ENCODATION-200.INC.PHP
// Description:	Encodation schemas for ECC 200 Datamatrix variant
// Created: 	2006-08-21
// Ver:		$Id: encodation-200.inc.php 1094 2009-01-23 16:02:30Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

class Encodation_200 {
    public $iSymbols = array(),$iSymbolShapeIdx=-1,$iSelectSchema = ENCODING_ASCII;
    // -1 Data does not fit symbol size 
    // -2 Length specification for BASE256 encoding is larger than available symbol length
    // -5 Trying to read data past end of data array.
    // -6 Trying to peek data beyond end of data array
    // -7 Data is too large to fit any available symbol size
    public $iError=0;
    private $i144NonStandard=false; // Should we use the non-standard way of interleaving for 144x144 symbol
    private  $iShift=0;
    private $iCurrentEncoding = ENCODING_ASCII; // Keep track of the current encoding we are in
    private $iDataIdx = 0, $iDataLen = 0, $iData = array();
    private $iSymbolIdx = 0,  $iSymbolMaxDataLen = 0;
    private $iRSLen = 0 ;
    private $iFillBASE256 = true;
    private $iSymbolSizes = array(array(3,5),array(5,7),array(8,10),array(12,12),array(18,14),
			      array(22,18),array(30,20),array(36,24),array(44,28),array(62,36),
			      array(86,42),array(114,48),array(144,56),array(174,68),array(204,84),
			      array(280,112),array(368,144),array(456,192),array(576,224),array(696,272),
			      array(816,336),array(1050,408),array(1304,496),array(1558,620),
			      array(5,7),array(10,11),array(16,14),array(22,18),array(32,24),array(49,28)),
	$iShape = array(
	    "10x10","12x12","14x14","16x16","18x18","20x20","22x22",
	    "24x24","26x26","32x32","36x36","40x40","44x44","48x48","52x52","64x64",
	    "72x72","80x80","88x88","96x96","104x104","120x120","132x132","144x144","155x62",
	    "8x18","8x32","12x26","12x36","16x36","16x48"),
	$iMappingShape = array(
	    "8x8","10x10","12x12","14x14","16x16","18x18","20x20","22x22",
	    "24x24","28x28","32x32","36x36","40x40","44x44","48x48",
	    "56x56","64x64","72x72","80x80","88x88","96x96","108x108","120x120","132x132",
	    "6x16","6x28","10x24","10x32","14x32","14x44"),
	$iDataRegion = array(
	    "8x8","10x10","12x12","14x14","16x16","18x18","20x20","22x22",
	    "24x24","14x14","16x16","18x18","20x20","22x22","24x24",
	    "14x14","16x16","18x18","20x20","22x22","24x24","18x18","20x20","22x22",
	    "6x16","6x14","10x24","10x16","14x16","14x22"),
	$iNDataRegions = array(
	    1, 1, 1, 1, 1, 1, 1, 1, 
	    1, 4, 4, 4, 4, 4, 4, 
	    16, 16, 16, 16, 16, 16, 36, 36, 36, 
	    1, 2, 1, 2, 2, 2),
	$iEncodingName = array(
	    'ENCODING_C40','ENCODING_TEXT','ENCODING_X12','ENCODING_EDIFACT','ENCODING_ASCII','ENCODING_BASE256','ENCODING_AUTO'),
	$iInterleaving = array(array(3,5,1),array(5,7,1),array(8,10,1),array(12,12,1),array(18,14,1),
			       array(22,18,1),array(30,20,1),array(36,24,1),array(44,28,1),array(62,36,1),
			       array(86,42,1),array(114,48,1),array(144,56,1),array(174,68,1),
			       array(102,42,2),array(140,56,2),
			       array(92,36,4),array(114,48,4),array(144,56,4),array(174,68,4),
			       array(136,56,6),array(175,68,6),array(163,62,8),array(156,62,10),
			       array(5,7,1),array(10,11,1),array(16,14,1),array(22,18,1),
			       array(32,24,1),array(49,28,1));

    

    function Encodation_200($aSchema=ENCODING_ASCII) {
	$this->iSelectSchema = $aSchema;
    }

    function SetSchema($aSchema) {
	$this->iSelectSchema = $aSchema;
    }

    function GetTextValues(&$aValues,$aSmallestChunk=false) {
	// Encode the data characters in the argument using TEXT encoding
	// until the end of the data. 
	$idx = 0 ;
	while(  $this->iDataIdx < $this->iDataLen ) {
	    $v = ord($this->iData[$this->iDataIdx++]);
	    
	    if( $v > 127 ) { 
		// Prepare to encode an extended ASCII character
		$v -= 128;
		$aValues[$idx++] = 1 ;
		$aValues[$idx++] = 30 ;
	    }
	    
	    if(  $v == 32 || ($v>=48 && $v <= 57) || ($v >= 97 && $v <= 122) ) { 
		$shift = 0 ;
	    }
	    elseif( $v <= 31 ) {
		$shift = 1 ; $aValues[$idx++] = 0 ;
	    }
	    elseif( ($v >= 33 && $v <= 47) || ($v >= 58 && $v <= 64) || ($v >= 91 && $v <= 95) ) {
		$shift = 2 ; $aValues[$idx++] = 1 ;
	    }
	    elseif( $v == 96 ||  ($v >= 65 && $v <= 90) || ($v >= 123 && $v <= 127) ) { 
		$shift = 3 ; $aValues[$idx++] = 2 ;
	    }

	    switch( $shift ) {
		case 0:
		    // Basic set */
		    if( $v == 32 ) $v = 3;
		    elseif( $v >= 48 && $v <= 57 ) $v -= 44;
		    elseif( $v >= 97 && $v <= 122 ) $v -= 83;
		    break;
		case 1:
		    // Shift 1 Control characters */
		    // $v = $v
		    break;
		case 2:
		    // Shift 2 Punctuation chars */
		    if( $v >= 33 && $v <= 47 ) $v -= 33;
		    elseif( $v >= 58 && $v <= 64 ) $v -= 43;
		    elseif( $v >= 91 && $v <= 95 ) $v -= 69;
		    break;
		case 3:
		    // Shift 3 Upper case and some punctuation */
		    if( $v == 96 ) $v = 0;
		    elseif( $v >= 65 && $v <= 90 ) $v -= 64;
		    elseif( $v >= 123 && $v <= 127 ) $v -= 96;
		    break;
	    }
	    $aValues[$idx++] = $v ;
	    if( $aSmallestChunk && ($idx % 3 == 0) )
		return $idx;
	}
    }

    function GetC40Values(&$aValues,$aSmallestChunk=false) {
	// Encode the data characters in the argument using C40 encoding
	// until the end of the data. 
	$idx = 0 ;
	while( $this->iDataIdx < $this->iDataLen ) {
	    $v = ord($this->iData[$this->iDataIdx++]);
	    
	    if( $v > 127 ) {
		// Prepare to encode an extended ASCII character
		$v -= 128;
		$aValues[$idx++] = 1 ;
		$aValues[$idx++] = 30 ;
	    }
	    
	    if(  $v == 32 || ($v>=48 && $v <= 57) || ($v >= 65 && $v <= 90) ) { 
		$shift = 0 ;
	    }
	    elseif( $v <= 31 ) {
		$shift = 1 ; $aValues[$idx++] = 0 ; 
	    }
	    elseif( ($v >= 33 && $v <= 47) || ( $v >= 58 && $v <= 64) || ( $v >= 91 && $v <= 95) ) {
		$shift = 2 ; $aValues[$idx++] = 1 ; 
	    }
	    elseif( $v >= 96 && $v <= 127  ) { 
		$shift = 3 ; $aValues[$idx++] = 2 ; 
	    }

	    switch( $shift ) {
		case 0:
		    // Basic set */
		    if( $v == 32 ) $v = 3;
		    elseif( $v >= 48 && $v <= 57 ) $v -= 44;
		    elseif( $v >= 65 && $v <= 90 ) $v -= 51;
		    break;
		case 1:
		    // Shift 1 Control characters */
		    // $v = $v
		    break;
		case 2:
		    // Shift 2 Punctuation chars */
		    if( $v >= 33 && $v <= 47 ) $v -= 33;
		    elseif( $v >= 58 && $v <= 64 ) $v -= 43;
		    elseif( $v >= 91 && $v <= 95 ) $v -= 69;
		    break;
		case 3:
		    // Shift 3 Lower case and some punctuation */
		    if( $v >= 96 && $v <= 127 ) $v -= 96;
		    break;
	    }
	    $aValues[$idx++] = $v ;
	    if( $aSmallestChunk && ($idx % 3 == 0) )
		return $idx;
	}
    }


    function Encode_TEXT_C40($aEncoding=ENCODING_TEXT,$aSmallestChunk=false) {
	$values = array();
	if( $aEncoding == ENCODING_TEXT ) 
	    $this->GetTextValues($values,$aSmallestChunk);
	else
	    $this->GetC40Values($values,$aSmallestChunk);

	// Now create the code words using the equation
	// (1600*C1 + 40*C2 + C3 + 1)
	$n = count($values);
	$nchunks = floor(count($values)/3);
	$rest = $n % 3;	
	$shift = false;
	if( $nchunks > 0 ) { 
	    for($i=0; $i < $nchunks-1; ++$i ) {	
			
		$v1 = $values[$i*3];
		$shift = $v1 <= 2 && !$shift;
		$v2 = $values[$i*3+1];
		$shift = $v2 <= 2 && !$shift;
		$v3 = $values[$i*3+2];
		$shift = $v3 <= 2 && !$shift;
	
	    	$val = 1600*$v1 + 40*$v2 + $v3 + 1;
	    	$this->_Put( floor($val/256) );
	    	$this->_Put( $val % 256 );
	    }
	
	    // Special care if we are at the last chunk. We need to make sure that
	    // we dont have a single shift ending this chunk that belongs to a 
	    // single shifted character that is the last character afer this chunk
	    $i = $nchunks-1;
	    $v1 = $values[$i*3];
	    $shift = $v1 <= 2 && !$shift;
	    $v2 = $values[$i*3+1];
	    $shift = $v2 <= 2 && !$shift;
	    $v3 = $values[$i*3+2];
	    $shift = $v3 <= 2 && !$shift;
	
	    // If there are only one code value left that is a shifted value then 
	    // we remove the shift indicator in the last posiion in the last chunk
	    // The final character will be encoded in ASCII with or possibly without
	    // unlatch depending on the number of characters in the symbol size
	    if( $rest == 1 && $shift ) {
		$v3 = 0;
	    }
	    $val = 1600*$v1 + 40*$v2 + $v3 + 1;
	    $this->_Put( floor($val/256) );
	    $this->_Put( $val % 256 );
	}
	if( $this->iError < 0 ) 
	    return false;

	// Handle the special cases where there are less than 3 codewords left
	// so we cannot use the standard formatting equation above

	//$rest = $n % 3;
	$remainingSymbols = $this->iSymbolMaxDataLen - $this->iSymbolIdx;
	if( $rest == 2 && $remainingSymbols >= 2 ) {
	    // Special cases to handle two remaining values add a '0' pad words
	    $val = 1600*$values[$nchunks*3] + 40*$values[$nchunks*3+1] + 0 + 1;
	    $v1 = floor($val/256);
	    $v2 = $val % 256 ;
	    $this->_Put( $v1 );
	    $this->_Put( $v2 );
	}
	elseif( $rest == 1 && $remainingSymbols >= 2 ) {
	    // Special cases to handle one remaining values and two symbol values
	    // Encoded as a latch + the value in ASCII encoding
	    $this->_Put( 254 ); // Unlatch character to get back to ASCII
	    $this->iCurrentEncoding = ENCODING_ASCII;
	    $this->_Put( ord($this->iData[$this->iDataLen-1])+1 ); // ASCII encoding
	}
	elseif( $rest == 1 && $remainingSymbols == 1 ) {
	    // Special cases to handle one remaining values and one symbol values
	    // This does not the the un-latch since it is implicitely assumed if
	    // this is the last symbol in the data part of the symbol
	    $this->iCurrentEncoding = ENCODING_ASCII;
	    $this->_Put( ord($this->iData[$this->iDataLen-1])+1 ); // ASCII encoding
	}
	elseif( $rest >= 1  ) {
	    $this->iError = -1;
	}
	elseif( $rest == 0 && $remainingSymbols >= 0 ) {
	    // Nothing ...
	}
	else {
	    $this->iError = -7;
	}
	return $this->iError >= 0;

    }

    function Encode_ASCII($aCnt=-1) {
	// A count == -1 means read the rest of the data
	if( $aCnt == -1 ) 
	    $aCnt = $this->iDataLen - $this->iDataIdx;
	$i=0;
	while( $i < $aCnt ) {
	    $c1 = $this->iData[$this->iDataIdx++];
	    $c2 = false;
	    if( $this->iDataIdx < $this->iDataLen ) {
		$c2 = $this->iData[$this->iDataIdx];
	    }
	    if( ctype_digit($c1) && ctype_digit($c2) ) {
		$this->_Put( intval($c1.$c2)+130 );
		++$this->iDataIdx;
		++$i;
	    }
	    else {
		$v = ord($c1);
		if( $v <= 127 ) {
		    $this->_Put( $v+1 );
		}
		else {
		    // Extended ASCII
		    $this->_Put( 235 );	// Upper shift in ASCII encoding	
		    $this->_Put( $v-128 );
		}
	    }
	    ++$i;
	}
	return $this->iError >= 0 ;
    }

    function _Put($aCW) {
	if( $this->iSymbolIdx >= $this->iSymbolMaxDataLen ) {
	    $this->iError = -1;
	    return;
	}
	$this->iSymbols[$this->iSymbolIdx++] = $aCW;
    }

    function _Get() {
	if( $this->iDataIdx >= $this->iDataLen ) {
	    $this->iError = -5;
	    return -1;
	}
	else
	    return $this->iData[$this->iDataIdx++];
    }

    function _Peek($aLookAhead=0) {
	if( $this->iDataIdx+$aLookAhead >= $this->iDataLen ) {
	    $this->iError = -6;
	    return -1;
	}
	else
	    return $this->iData[$this->iDataIdx+$aLookAhead];
    }

    function GetX12Value($v) {
	if( $v == 32 ) return 3;
	elseif( $v >= 48 && $v <= 57 ) return $v-44;
	elseif( $v >= 65 && $v <= 90 ) return $v-51;
	elseif( $v == 13 ) return 0;
	elseif( $v == 42 ) return 1;
	elseif( $v == 62 ) return 2;
	else {
	    $this->iError = -8;
	    return false;
	}
    }

    function Encode_X12($aSmallestChunk=false) {
	$remaining = $this->iDataLen - $this->iDataIdx;
	$n = floor($remaining/3);
	$idx = 0;
	for( $i=0; $i < $n; ++$i ) {
	    $v1 = $this->GetX12Value(ord($this->iData[$this->iDataIdx++]));
	    $v2 = $this->GetX12Value(ord($this->iData[$this->iDataIdx++]));
	    $v3 = $this->GetX12Value(ord($this->iData[$this->iDataIdx++]));
	    if( $v1 === false || $v2 === false || $v3 === false ) {
		return false;
	    }
	    $remaining -= 3;
	    $val = $v1*1600 + $v2*40 + $v3 + 1;
	    $this->_Put( floor($val/256) );
	    $this->_Put( $val % 256 );	
	    if( $aSmallestChunk )
		return $this->iError >= 0 ;
	}
	if( $remaining > 0  ) {
	    $available = $this->iSymbolMaxDataLen - $this->iSymbolIdx;
	    if( $remaining > $available ) {
		$this->iError = -1;
		return false;
	    }
	    if( $available > 1 ) {
	    	$this->_Put( 254 ); // Latch to ASCII
		$this->iCurrentEncoding = ENCODING_ASCII;
	    }
	    $this->_Put( ord($this->iData[$this->iDataIdx++])+1 );
	    if( $remaining > 1 ) {
		$this->_Put( ord($this->iData[$this->iDataIdx++])+1 );
	    }
	}
	return $this->iError >= 0 ;
    }

    function Encode_EDIFACT() {
	// ToDo: Finish EDIFACT encoding
	$this->iError = -99;
	return false;


	$remaining = $this->iDataLen - $this->iDataIdx;
	$n = floor($remaining/4);
	$idx = $this->iDataIdx;
	for( $i=0; $i < $n; ++$i ) {
	    $c1 = $this->GetEDIFACTValue(ord($this->iData[$idx++]));
	    $c2 = $this->GetEDIFACTValue(ord($this->iData[$idx++]));
	    $c3 = $this->GetEDIFACTValue(ord($this->iData[$idx++]));
	    $c4 = $this->GetEDIFACTValue(ord($this->iData[$idx++]));
	    $remaining -= 4;

	    $v1 = ((0x3f & $c1) << 2) | ((0x30 & $c1) >> 4) ;
	    $v2 = ((0x0F & $c2) << 4) | ((0x3F & $c3) >> 2) ;
	    $v3 = ((0x03 & $c3) << 6) | (0x3F & $c4) ;
	}
	return $this->iError >= 0 ;
    }

    // A length of == 0 indicates to the end of data
    function Encode_BASE256($aCnt=-1,$aStoreLengthCnt=true) {
	// A count == -1 means read the rest of the data
	if( $aCnt == -1 ) 
	    $aCnt = $this->iDataLen - $this->iDataIdx;

	if( $aCnt > ($this->iSymbolMaxDataLen-$this->iSymbolIdx) ) {
	    $this->iError = -2;
	    return false;
	}
	
	if( $aStoreLengthCnt ) {
	    if( $aCnt >= 1 && $aCnt <= 249 ) {
		$v = $aCnt;
		$rand = ((149*($this->iSymbolIdx+1)) % 255) + 1;
		$v += $rand;
		$v = $v <= 255 ? $v : $v - 256;
		$this->_Put( $v ) ;
	    }
	    else {
		$v = floor($aCnt / 250) + 249;
		$rand = ((149*($this->iSymbolIdx+1)) % 255) + 1;
		$v += $rand;
		$v = $v <= 255 ? $v : $v - 256;
		$this->_Put( $v ) ;
	    
		$v = $aCnt % 250 ;
		$rand = ((149*($this->iSymbolIdx+1)) % 255) + 1;
		$v += $rand;
		$v = $v <= 255 ? $v : $v - 256;		
		$this->_Put( $v ) ;
	    }
	}

	if( $this->iError < 0 ) {
	    return false;
	}

	$i=0;
	while($i < $aCnt && $this->iError >= 0 ) {
	    $v = ord($this->iData[$this->iDataIdx++]) ;
	    $rand = ((149*($this->iSymbolIdx+1)) % 255) + 1;
	    $v += $rand;
	    $v = $v <= 255 ? $v : $v - 256;
	    $this->_Put( $v ); 
	    ++$i;
	}
	return $this->iError >= 0 ;
    }

    function AutoSize(&$aData) {
	$this->iRSLen = 0;;
	// Don't bother try with any symbol size that holds less than 1/2 of the original
	// length of the data since we can never hope to have more than 50% reduction in
	// size due to the encoding.
	$i = 0;
	$n = count($this->iSymbolSizes);
	$m = floor(count($aData)/2); 
	while( $i < $n && $this->iSymbolSizes[$i][0] < $m ) 
	    ++$i;

	if( $i >= $n ) { 
	    // Data is too large to fit in any symbol size
	    $this->iError = -1;
	    return false;
	}
	do {
	    $symbols = array();
	    $this->iSymbolShapeIdx = $i;
	    $this->_Encode($aData,$symbols);
	    if( $this->iError < -2 ) {
		// If we encounter an encodation error with the selected encodation schema
		// we return this as an error directly
		return false;
	    }
	    ++$i;
	} while( $i < $n && $this->iError < 0 ) ;

	if( $this->iError < 0 ) {
	    // Data is too large to fit in any symbol size
	    $this->iError = -1;
	    return false;
	}
	else {
	    return $this->iSymbolShapeIdx;
	}
    }


    function GetError($aAsString=false) {
	return $this->iError;
    }

    function AddErrorCoding() {
	// Read the interleaving specification for this symbol shape from the
	// definition table
	$spec = $this->iInterleaving[$this->iSymbolShapeIdx];

	// Remember the nuymber of data symbols for this symbol shape
	$nDataSymbols = $this->iSymbolSizes[$this->iSymbolShapeIdx][0];

	$nBlocks = $spec[2];	// Number of interleaving blocks
	$nd = $spec[0];		// Number data words in each block
	$ne = $spec[1];		// Number of error correcting code words in each block

	// Initialize the block array that will hold each RS block
	$block = array();

	// Now put the symbol words in alternating interleaving blocks as preparation
	// for the calculation of the RS error correction codewords.
	for($i=0; $i < $nDataSymbols; ++$i) {
	    $bidx = $i % $nBlocks;
	    $block[$bidx][floor($i/$nBlocks)] = $this->iSymbols[$i]; 
	}

	// Now we calculate the RS code for each block and put the resulting
	// error corection words in the error correction codewords section of
	// the stream according to the interleaving. 
	// Note: For the first 14 shapes (0-13) the interleaving is 1 which
	// means that this is just an elaborate way to just add the error
	// corecting codewords to the data without interleaving.
	$rs = new ReedSolomon(8,$ne);

	if( $rs === false ) {
	    $this->iError = -16;
	    return false;
	}

	// Interleaving for symbol 144x144 has a peculiarity that is incorrectly
	// documented in the ISO specs (16022). The interleaving starts with an offset
	// of 2 instead of 0 which is used for very other code.
	$offset = 0 ;
	if( $this->iSymbolShapeIdx == 23 && $this->i144NonStandard) 
	    $offset = 2;

	for($i=0; $i < $nBlocks; ++$i) {

	    // The block contains the data so we now calculate the error correction
	    // words and append them ot the end of this block
	    $rs->AppendCode($block[$i]);
	    
	    // The last two block for 144x144 symbols have one less data word
	    // compared with the first 8 so we need to decrease the data counter (nd)
	    // that is used as an offset into the block to mark the start of the error
	    // correction words.
	    if( $this->iSymbolShapeIdx == 23 && $i == 8 ) {
		--$nd;
	    }

	    // Now put the error check words from each of the RS block into there
	    // right position in the symbol stream. 
	    // Example: 
	    // For a 88x88 (4 RS blocks) : 
	    // Block 0:s error correction words will/ be put in index position 0,4,8,  .. 216, 220
	    // Block 1:s error correction words will/ be put in index position 1,5,9,  .. 217, 221
	    // Block 2:s error correction words will/ be put in index position 2,6,10, .. 218, 222
	    // Block 3:s error correction words will/ be put in index position 3,7,11, .. 219, 223
	    //
	    // The offset value specifies the start of the first error check for each block. As can
	    // bee seen from the example above this will be 0,1,2,3 for 88x88.
	    //
	    // The need to introduce this extra paramater is that it is needed for 144x144 since 
	    // this seems by some program use a start with an offset 3 (starting at index positoin 2)  
	    // instead.
	    for($j=0; $j < $ne; ++$j) {
		$this->iSymbols[$nDataSymbols+$offset+$j*$nBlocks] =  $block[$i][$nd+$j];
	    }
	    ++$offset;
	    $offset %= $nBlocks;
	}
    }
    
    function NextAutoMode($aCurrentMode) {
	// Initialize counters
	if( $aCurrentMode == ENCODING_ASCII ) {
	    $cntASCII = 0;
	    $cntC40 = 1;
	    $cntTEXT = 1;
	    $cntX12 = 1;
	    $cntEDF = 1;
	    $cntB256 = 1.25;
	}
	else {
	    $cntASCII = 1;
	    $cntC40 = 2;
	    $cntTEXT = 2;
	    $cntX12 = 2;
	    $cntEDF = 2;
	    $cntB256 = 2.25;
	}
	switch( $aCurrentMode ) {
	    case ENCODING_C40: $cntC40 = 0; break;
	    case ENCODING_TEXT: $cntTEXT = 0; break;
	    case ENCODING_X12: $cntX12 = 0; break;
	    case ENCODING_EDIFACT: $cntEDF = 0; break;
	    case ENCODING_BASE256: $cntB256 = 0; break;
	}
	$idx = $this->iDataIdx;
	while( $idx < $this->iDataLen ) {
	    $c1 = $this->iData[$idx++];
	    $o = ord($c1);
	    $isExtASCII = $o > 127;
	    $isDigit = ctype_digit($c1);

	    // Process ASCII count
	    if( $isDigit ) $cntASCII += 0.5; 
	    elseif( $isExtASCII ) $cntASCII = ceil($cntASCII)+2;	  
	    else $cntASCII = ceil($cntASCII)+1; 

	    // Process C40 count
	    if( $o == 32 || $isDigit || ($o >= 65 && $o <= 90) ) $cntC40 += 2/3;
	    elseif( $isExtASCII ) $cntC40 += 8/3;
	    else $cntC40 += 4/3;

	    // Process TEXT count
	    if( $o == 32 || $isDigit || ($o >= 97 && $o <= 122) ) $cntTEXT += 2/3;
	    elseif( $isExtASCII ) $cntTEXT += 8/3;
	    else $cntTEXT += 4/3;

	    // Process X12 count
	    if( $o == 32 || $o == 13 || $o == 42 || $o == 62 || $isDigit || ($o >= 65 && $o <= 90) ) $cntX12 += 2/3;
	    elseif( $isExtASCII ) $cntX12 += 13/3;
	    else $cntX12 += 10/3;
	
	    // Process EDF count
	    /*
	    if( $o >= 32 && $o <= 94 ) $cntEDF += 3/4;
	    elseif( $isExtASCII ) $cntEDF += 17/4;
	    else $cntEDF += 13/4;
	    */

	    // Process the BASE256 count
	    // FNC1 , Structured append, Reader program, Code page add 4
	    if( $o == 232 || $o == 233 || $o == 234 || $o == 241 ) $cntB256 += 4;
	    else ++$cntB256 ;

	    // Have we processed at least 4 chars
	    $n = $idx - $this->iDataIdx; 
	    if( $n >= 4 ) {
		$ret = -1;
		if( $cntASCII+1 <= $cntC40 && $cntASCII+1 <= $cntTEXT && $cntASCII+1 <= $cntX12 &&
		    /* $cntASCII+1 <= $cntEDF && */ $cntASCII+1 <= $cntB256  ) {
		    $ret = ENCODING_ASCII;
		}
		elseif( ($cntB256+1 <= $cntASCII) || 
			($cntB256 < $cntC40 && $cntB256 < $cntX12 &&
			 /* $cntB256 < $cntEDF  && */ $cntB256 < $cntTEXT) ) {
		    $ret = ENCODING_BASE256;
		}
		/*
		elseif( $cntEDF+1 < $cntC40 && $cntEDF+1 < $cntTEXT && $cntEDF+1 < $cntX12 &&
			$cntEDF+1 < $cntB256 && $cntEDF+1 < $cntASCII ) {
		    $ret = ENCODING_EDIFACT;
		}
		*/
		elseif( $cntTEXT+1 < $cntC40 && $cntTEXT+1 < $cntB256 && $cntTEXT+1 < $cntX12 &&
			/* $cntTEXT+1 < $cntEDF && */ $cntTEXT+1 < $cntASCII ) {
		    $ret = ENCODING_TEXT;
		}
		elseif( $cntX12+1 < $cntC40 && $cntX12+1 < $cntTEXT && $cntX12+1 < $cntB256 &&
			/* $cntX12+1 < $cntEDF  && */ $cntX12+1 < $cntASCII ) {
		    $ret = ENCODING_X12;
		}
		elseif( $cntC40+1 < $cntTEXT && $cntC40+1 < $cntB256 &&
			/* $cntC40+1 < $cntEDF && */ $cntC40+1 < $cntASCII ) {
		    if( $cntC40 < $cntX12 )
			$ret = ENCODING_C40;
		    elseif( $cntC40 == $cntX12 ) {
			$ret = ENCODING_C40;
			if( $idx < $this->iDataLen ) {
			    $c2 = $this->iData[$idx];
			    if( ord($c2) == 13 || ord($c2) == 42 || ord($c2) == 62 )
				$ret = ENCODING_X12;
			}
		    }
		}
		if( $ret >= 0 ) {
		    return $ret;
		}
	    }
	}

	// The data is processed but we have not yet encountered a return condition
	// so now we need to analyze the statistics and return the best encoding option.
	$cntASCII = round($cntASCII); $cntC40 = round($cntC40);
	$cntTEXT = round($cntTEXT); $cntX12 = round($cntX12);
	$cntB256 = round($cntB256);
	// $cntEDF = round($cntEDF); 

	if( $cntASCII <= $cntC40 && $cntASCII <= $cntTEXT && $cntASCII <= $cntX12 &&
	    /* $cntASCII <= $cntEDF && */ $cntASCII <= $cntB256  ) {
	    $ret = ENCODING_ASCII;
	}
	elseif( $cntB256 < $cntC40 && $cntB256 < $cntTEXT && $cntB256 < $cntX12 &&
		/* $cntB256 < $cntEDF && */ $cntB256 < $cntASCII ) {
	    $ret = ENCODING_BASE256;
	}
	elseif( $cntTEXT < $cntC40 && $cntTEXT < $cntB256 && $cntTEXT < $cntX12 &&
		/* $cntTEXT < $cntEDF && */ $cntTEXT < $cntASCII ) {
	    $ret = ENCODING_TEXT;
	}
	elseif( $cntX12 < $cntC40 && $cntX12 < $cntTEXT && $cntX12 < $cntB256 &&
		/* $cntX12 < $cntEDF && */ $cntX12 < $cntASCII ) {
	    $ret = ENCODING_X12;
	}
	/*
	elseif( $cntEDF < $cntC40 && $cntEDF < $cntTEXT && $cntEDF < $cntX12 &&
		$cntEDF < $cntB256 && $cntEDF < $cntASCII ) {
	    $ret = ENCODING_EDIFACT;
	}
	*/
	else
	    $ret = ENCODING_C40;
	return $ret;
    }

    function Encode_Auto() {
	$latchTo = array(ENCODING_BASE256 => 231, ENCODING_C40 => 230,
			 ENCODING_TEXT => 239, ENCODING_X12 => 238,
			 ENCODING_EDIFACT => 240);

	$this->iCurrentEncoding = ENCODING_ASCII;
	$startBASE256 = true;
	$cntBASE256 = 0;
	while($this->iDataIdx < $this->iDataLen ) {
	    if( $this->iError < 0 ) {
		return false;
	    }
	    $charsLeft = $this->iDataLen - $this->iDataIdx ;
	    $c1 = $this->iData[$this->iDataIdx];
	    $c2 = false; $c3 = false;
	    if( $charsLeft >= 2 )
		$c2 = $this->iData[$this->iDataIdx+1];
	    if( $charsLeft >= 3 )
		$c3 = $this->iData[$this->iDataIdx+2];

	    switch( $this->iCurrentEncoding ) {
		case ENCODING_ASCII:
		    if( ctype_digit($c1) && ctype_digit($c2) ) {
			$this->Encode_ASCII(2);
		    }
		    $this->iCurrentEncoding = $this->NextAutoMode(ENCODING_ASCII);
		    if( $this->iCurrentEncoding != ENCODING_ASCII ) {
			$this->_Put($latchTo[$this->iCurrentEncoding]);
		    }
		    elseif( !ctype_digit($c1) || !ctype_digit($c2) ) {
			$this->Encode_ASCII(1);
		    }
		    break;
		case ENCODING_C40:
		    $this->Encode_TEXT_C40(ENCODING_C40,true);
		    if( $this->iDataIdx < $this->iDataLen ) {
		    	$this->iCurrentEncoding = $this->NextAutoMode(ENCODING_C40);
		    	if( $this->iCurrentEncoding != ENCODING_C40 ) {
			    // Switch back to ASCII
			    $this->_Put(254);
			    if( $this->iCurrentEncoding != ENCODING_ASCII ) {
				$this->_Put($latchTo[$this->iCurrentEncoding]);
			    }
		    	}
		    }
		    break;
		case ENCODING_TEXT:
		    $this->Encode_TEXT_C40(ENCODING_TEXT,true);
		    if( $this->iDataIdx < $this->iDataLen ) {		    
			$this->iCurrentEncoding = $this->NextAutoMode(ENCODING_TEXT);
			if( $this->iCurrentEncoding != ENCODING_TEXT ) {
			    // Switch back to ASCII
			    $this->_Put(254);
			    if( $this->iCurrentEncoding != ENCODING_ASCII ) {
				$this->_Put($latchTo[$this->iCurrentEncoding]);
			    }
		    	}
		    }
		    break;
		case ENCODING_BASE256:
		    $this->iCurrentEncoding = $this->NextAutoMode(ENCODING_BASE256);
		    if( $this->iCurrentEncoding == ENCODING_BASE256 ) {
			if( $startBASE256 ) {
			    // Remeber index snce we need to back patch the length byte(s)
			    $base256CounterIdx = $this->iSymbolIdx;
			    $startBASE256 = false;
			}
			$this->Encode_BASE256(1,$startBASE256);
			++$cntBASE256;
		    }
		    else {
			// Reset start indicator for next round
			$startBASE256 = true;
			// Backpatch length byte (or 2)
			if( $$cntBASE256 >= 1 && $cntBASE256 <= 249 ) {
			    $v = $cntBASE256;
			    $rand = ((149*($base256CounterIdx+1)) % 255) + 1;
			    $v += $rand;
			    $v = $v <= 255 ? $v : $v - 256;
			    $this->iSymbols[$base256CounterIdx] = $v;
			}
			else {
			    // We need two bytes for the counter so we need to
			    // make room for it in the main symbol array by moving all
			    // already stored BASE256 symbols one step up
			    $n = count($this->iSymbols);
			    for($i=$n; $i > $base256CounterIdx; --$i) {
				$this->iSymbols[$i] = $this->iSymbols[$i-1];
			    }
			    $v = floor($cntBASE256 / 250) + 249;
			    $rand = ((149*($base256CounterIdx+1)) % 255) + 1;
			    $v += $rand;
			    $v = $v <= 255 ? $v : $v - 256;
			    $this->iSymbols[$base256CounterIdx] = $v;
	    
			    $v = $cntBASE256 % 250 ;
			    $rand = ((149*($base256CounterIdx+2)) % 255) + 1;
			    $v += $rand;
			    $v = $v <= 255 ? $v : $v - 256;		
			    $this->iSymbols[$base256CounterIdx+1] = $v;
			}
			$cntBASE256 = 0;
			// Switch back to ASCII
			$this->_Put(254);
			if( $this->iCurrentEncoding != ENCODING_ASCII ) {
			    $this->_Put($latchTo[$this->iCurrentEncoding]);
			}

		    }
		    break;
		case ENCODING_X12:
		    $this->Encode_X12(true);
		    if( $this->iDataIdx < $this->iDataLen ) {		    
		    	$this->iCurrentEncoding = $this->NextAutoMode(ENCODING_X12);
		    	if( $this->iCurrentEncoding != ENCODING_X12 ) {
			    // Switch back to ASCII
			    $this->_Put(254);
			    if( $this->iCurrentEncoding != ENCODING_ASCII ) {
				$this->_Put($latchTo[$this->iCurrentEncoding]);
			    }
		    	}
		    }
		    break;
		case ENCODING_EDIFACT:
		    $this->iError = -99;
		    return false;
	    }
	}
    }

    function Encode($aData, &$aSymbols, $aSymbolShapeIdx = -1 ) {
	$this->iSymbolShapeIdx = $aSymbolShapeIdx;
	if( $aSymbolShapeIdx >= 0 && $aSymbolShapeIdx < count($this->iSymbolSizes)) {
	    $symbolDataSize = $this->iSymbolSizes[$aSymbolShapeIdx][0];
	    $this->_Encode($aData, $aSymbols);
	}
	elseif( $this->iSelectSchema != ENCODING_AUTO ) {
	    // Auto determine minimum symbol size to fit data
	    $this->AutoSize($aData);
	    // We know that the last trial left the encoded symbols in iSymbols
	    $aSymbols = $this->iSymbols;
	}
	else {
	    // ENCODING_AUTO
	    $aSymbols = array();
	    $this->_Encode($aData,$aSymbols);	 
	}

	// Check if we had any error and in that case return false
	if( $this->iError < 0 ) {
	    $aSymbols= array();
	    return false;
	}
	else
	    return true;
    }

    function _Encode($aData, &$aSymbols) {
	if( $this->iSelectSchema != ENCODING_AUTO ) {
	    $this->iSymbolMaxDataLen = $this->iSymbolSizes[$this->iSymbolShapeIdx][0];
	}
	$this->iDataLen = count($aData);
	$this->iData = $aData;
	$this->iDataIdx = 0;
	$this->iSymbolIdx = 0;
	$this->iSymbols = array();
	$this->iError = 0 ;
	while( $this->iDataIdx < $this->iDataLen  && $this->iError >= 0 ) {
	    switch( $this->iSelectSchema ) { 
	        case ENCODING_AUTO:
		    // Use max len as assumption when AUTO sizing is in effect. 
		    // After the encoding is done we choose the smallest
		    // possible symbol that will fit the encoded characters.
		    // Note: This is a small catch 22 since it is possible that we will choose one
		    // symbol size too large since the encoding also depends on the symbol size (and
		    // the symbol size depends on the encoded string). However in practice this is
		    // most likely minor problem. The only way to guarantee a small symbol size would
		    // be to try again to encode the data with one size smaller symbol and see if it
		    // fits.
		    $idx = $this->iSymbolShapeIdx < 0 ? 23 : $this->iSymbolShapeIdx ;
		    $this->iSymbolMaxDataLen = $this->iSymbolSizes[$idx][0];
		  
		    // After the auto encodation is ready it will return the mode
		    // it left in as a return value. This is needed for any possible 
		    // pad characters in the end. We need to know if we need to switch to
		    // ASCII mode or if we are already in ASCII mode.
		    $this->Encode_Auto();

		    if( $this->iSymbolShapeIdx < 0 ) {
			// Use the smallest symbol that fits the symbol length
			$i=0;
			while( $i < 23 && ($this->iSymbolSizes[$i][0] < $this->iSymbolIdx) )
			    ++$i;
			if( $i >= 23 ) {
			    $this->iError = -1; 
			    return false;
			}
			$this->iSymbolShapeIdx = $i;
			$this->iSymbolMaxDataLen = $this->iSymbolSizes[$i][0];
		    }
		    break;
		case ENCODING_ASCII:
		    $this->iCurrentEncoding = ENCODING_ASCII;
		    $this->Encode_ASCII();
		    break;
		case ENCODING_C40:
		    if( $this->iDataLen < 3 ) {
			$this->iError = -3; 
			return false;
		    }
		    $this->_Put( 230 );
		    $this->iCurrentEncoding = ENCODING_C40;
		    $this->Encode_TEXT_C40(ENCODING_C40);
		    break;
		case ENCODING_TEXT:
		    if( $this->iDataLen < 3 ) {
			$this->iError = -4; 
			return false;
		    }
		    $this->_Put( 239 );
		    $this->iCurrentEncoding = ENCODING_TEXT;
		    $this->Encode_TEXT_C40(ENCODING_TEXT);
		    break;
		case ENCODING_X12:
		    if( $this->iDataLen < 3 ) {
			$this->iError = -10; 
			return false;
		    }
		    $this->iCurrentEncoding = ENCODING_X12;
		    $this->_Put( 238 );
		    $this->Encode_X12();
		    break;
		case ENCODING_EDIFACT:
		    $this->_Put( 240 );
		    $this->iCurrentEncoding = ENCODING_EDIFACT;
		    $this->Encode_EDIFACT();
		    break;
		case ENCODING_BASE256:
		    $this->_Put( 231 );
		    $this->iCurrentEncoding = ENCODING_BASE256;
		    $this->Encode_BASE256();
		    break;
	    }
	}

	if( $this->iError < 0 ) {
	    return false;
	}

	// Add pad characters to fill out to specified symbol size
	// Randomize the pad characters using the 253-state algotithm
	// Ref: Annexe H.1
	$n = $this->iSymbolMaxDataLen - $this->iSymbolIdx;
	$firstPadPos = $this->iSymbolIdx;

	// Check if we need an unlatch character back to ASCII encoding
	// This is only happening after TEXT or C40 encoding.
	// Note the special case when there is only one symbol left
	// and we put the pad (129) directly without adding a latch
	// character.

	if( $n > 1 && $this->iSelectSchema != ENCODING_ASCII && $this->iSelectSchema != ENCODING_BASE256 ) {
	    // We might already be in ASCII mode which has been set by the actual encoding
	    // For example if TEXT have an odd symbol to endode it wiil switch to ASCII and then output
	    // that symbol.
	    if( $this->iCurrentEncoding != ENCODING_ASCII ) {
		$this->_Put( 254 );
		--$n;
	    }
	}
	
	if( $n > 0 ) {
	    $this->_Put( 129 );
	    while( $n > 1 ) {
		$pad = 129;
		$rand = (149*($this->iSymbolIdx+1)) % 253 + 1;
		$pad += $rand;
		$pad = $pad <= 254 ? $pad : $pad - 254;
		$this->_Put($pad); 
		--$n;
	    }
	}
	$aSymbols = $this->iSymbols;
	return $this->iError >= 0 ;
    }
}

?>
