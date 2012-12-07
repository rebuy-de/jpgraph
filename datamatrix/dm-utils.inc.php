<?php
/*=======================================================================
// File: 	DM-UTILS.INC.PHP
// Description:	Utility function for bit and string manipulation
// Created: 	2006-08-20
// Ver:		$Id: dm-utils.inc.php 988 2008-03-25 02:50:13Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

/*--------------------------------------------------------------------------------------------------------------------
// Convert an array of aWordSize integers in the corresponding array
// of consecutive bits
//--------------------------------------------------------------------------------------------------------------------
*/
function ByteArray2Bits($aData,&$aBits,$aWordSize=8) {
    $resIdx = 0;
    $n = count($aData);
    $maskbit = 1 << ($aWordSize-1);
    $mask = (1 << $aWordSize)-1;
    for($i=0; $i < $n; ++$i) {
	$b = $aData[$i];
	for($j=0; $j < $aWordSize; ++$j) {
	    if( $b & $maskbit ) 
		$aBits[$resIdx++] = 1;
	    else
		$aBits[$resIdx++] = 0;
	    $b = ($b << 1) & $mask;
	}
    }
}

/*--------------------------------------------------------------------------------------------------------------------
// Convert an integer of aWordSize into the corresponding array
// of consecutive bits
//--------------------------------------------------------------------------------------------------------------------
*/
function Word2Bits($aByte,&$aBits,$aWordSize=8) {
    $resIdx = 0;
    $aBits = array();
    $maskbit = 1 << ($aWordSize-1);
    $mask = (1 << $aWordSize)-1;
    $b = $aByte;
    for($j=0; $j < $aWordSize; ++$j) {
	if( $b & $maskbit ) 
	    $aBits[$resIdx++] = 1;
	else
	    $aBits[$resIdx++] = 0;
	$b = ($b << 1) & $mask;
    }
}

/*--------------------------------------------------------------------------------------------------------------------
 ** Preprocess the data to be encoded for easier textual specifications. Note that some of the format have stricts
 ** rules on whan in the data string they can be applied. If any of these rules are violated the function will return
 ** boolean FALSE, otherwise it will return the translated string.
 ** 
 ** ~X , X in [@,Z] Used to specify the first 26 ASCII values. ~@ == 0 , ~A == 1 , ...
 **
 ** ~1: represents the character FNC1. Alternate Data Type Identifier. See Table 6: ISO 16022
 **
 ** ~2nnmmffffff: Structured Append. The digits following ~2 is nn=particular index (01-16), mm=total number (02-16)
 **
 ** ~5 and ~6: 05 and 06 Macro. Can only be in the first position and is used to encode industri standard headers
 ** in certain structured formats.
 ** 
 ** Macro 05 is translated by the barcode reader to : 
 **  Symbol prefix: chr(30) chr(05) chr(29) 
 **  Symbol postfix: chr(30) chr(04)
 ** Macro 06 is translated by the barcode reader to :
 **  Symbol prefix: chr(30) chr(06) chr(29) 
 **  Symbol postfix: chr(30) chr(04)
 **
 ** ~7nnnnnn : Extended Channel Interpretation (ECI) 6-digit channel number 
 ** See "Extended Channel Interpretation Assignments" document for a list of channels and there meaning.
 **
 ** ~9: The symbol contains reader programming information
 **
 ** ~dNNN : Character value as 3 digits. 
 **---------------------------------------------------------------------------------------------------------------------
 */
function tilde_process($aStr) {

    // Replace ~@ == 0, ~A == 1, ~B == 2 , .., ~Z == 26
    $r = str_replace('~@',chr(0),$aStr);
    for($i=0; $i < 26; ++$i ) {
	$r = str_replace('~'.chr($i+65),chr($i+1),$r);
    }

    // Replace ~1 with chr(232) which is the codeword for FNC1
    // Alternate Data Type Identifier. See Table 6: ISO 16022
    if( ($n = strpos($r,'~1')) !== false ) {
	// If ~1 is found in position 1,2 or 5,6 then it shall be encoded as
	// 232 in any other position as 29 according to the specification
	if( $n == 0 || $n == 1 || $n == 4 || $n == 5 ) 
	    $r = str_replace('~1',chr(232),$r);
	else
	    $r = str_replace('~1',chr(29),$r);
    }

    // Structured append ~2NNN
    if( ($n = strpos($r,'~2')) !== false ) {
	if( $n == 0 ) {
	    // Check that is doesn't exist anywhere else
	    if( strpos($r,'~2',$n+2) === false ) {
		$nn = substr($r,2,2);
		if( !ctype_digit($nn) ) return false;
		$mm = substr($r,4,2);
		if( !ctype_digit($mm) ) return false;
		if( $nn < 1 || $mm < 2 || $mm > 16 || $nn > $mm ) return false;
		$mm = 17-$mm;
		$nn = $nn-1;
		$ff1 = substr($r,6,3);
		$ff2 = substr($r,9,3);
		if( !ctype_digit($ff1) || !ctype_digit($ff2) )
		    return false;
		$rr = chr(233).chr(($nn << 4) | $mm).chr($ff1).chr($ff2);
		$r = $rr.substr($r,12) ;
	    }
	    else
		return false;
	}
	else 
	    return false;
    }

    if( ($n = strpos($r,'~5')) !== false ) {
	// 05 Macro character
	if( $n == 0 ) {
	    if( strpos($r,'~5',$n+2) === false )
		$r = str_replace('~5',chr(236),$r);
	    else
		return false;
	}
	else 
	    return false;
    }

    if( ($n = strpos($r,'~6')) !== false ) {
	// 06 Macro character
	if( $n == 0 ) {
	    if( strpos($r,'~6',$n+2) === false )
		$r = str_replace('~6',chr(237),$r);
	    else
		return false;
	}
	else 
	    return false;
    }

    // Replace ~9 as a reader programming character
    if( ($n = strpos($r,'~9')) !== false ) {
	// Reader programming
	if( $n == 0 ) {
	    if( strpos($r,'~9',$n+2) === false )
		$r = str_replace('~9',chr(234),$r);
	    else
		return false;
	}
	else 
	    return false;
    }

    // Extended channel interpretation
    // See section 6.4 ISO-16022
    $offset=0;
    while( ($n = strpos($r,'~7',$offset)) !== false ) {
	// Get the 6 digits that follow
	$seci = substr($r,$n+2,6);
	if( strlen($seci) < 6 || !is_numeric($seci)) 
	    return false;
	
	$eci = intval($seci);
	if( $eci >= 0 && $eci <= 126 ) {
	    $code = chr(241).chr($eci+1);
	}
	elseif( $eci >= 127 && $eci <= 16382 ) {
	    $c1 = floor(($eci-127)/254) + 128;
	    $c2 = ($eci-127) % 254 + 1;
	    $code = chr(241).chr($c1).chr($c2);
	}
	else {
	    $c1 = floor(($eci-16383)/64516) + 192;
	    $c2 = floor(($eci-16383)/254) % 254 + 1;
	    $c3 = ($eci-16383) % 254 + 1;
	    $code = chr(241).chr($c1).chr($c2).chr($c3);
	}
	$r = str_replace('~7'.$seci,$code,$r);
	$offset = $n+strlen($code);
    }
    
    // Replace ~dNNN with the character chr(NNN)
    $offset=0;
    while( ($n = strpos($r,'~d',$offset)) !== false ) {
	// Get the 3 digits that follow
	$sv = substr($r,$n+2,3);
	if( strlen($sv) < 3 || !ctype_digit($sv)) 
	    return false;
	
	$v = intval($sv);
	$r = str_replace('~d'.$sv,chr($v),$r);
	$offset = $n+1;
    }
    
    return $r;
}

?>
