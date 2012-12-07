<?php
/*=======================================================================
// File: 	BIT-PLACEMENT-BIN-140.INC.PHP
// Description:	Bit placement for ECC 140 using binary data files instead of
//		included array to reduce PHP parse time.
// Created: 	2006-08-21
// Ver:		$Id: bit-placement-bin-140.inc.php 1096 2009-01-23 16:48:38Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

class BitPlacement_140 {
    public $iError = 0 ;

    // Pre-computed CRC32 checksums for the bit aplcement matrices to make sure the
    // data is not corrupted. (Note CRC32 checksums are by definition unsigned but since
    // PHP internally only have signed ints this will make large unsigned integers be 
    // interpretated as negative integers.
    private $iBitPosCRC32 = array(-1218165820,-1642971647,398894782,1874383969,-389282195,1785029150,527100582,
			      850234052,-659011183,1232248065,983196112,183884585,1491393156,610282677,
			      -821932643,-89670890,-1337417251,669008347, 1977297474,917193336,2107802295);

    function BitPlacement_140() {
	// Empty
    }
    
    function Set($aIdx,$aDataBits,&$aOutputMatrice) {
	if( $aIdx < 0 || $aIdx > 20 ) {
	    $this->iError = -14;
	    return false;
	}
	$size = $aIdx*2+7;
	
	$sapi = php_sapi_name();
	$fname = dirname(__FILE__)."/bindata/bitplacement-$size.dat";
	$fp=fopen($fname,'r');
	if( $fp === false ) {
	    $this->iError = -26;
	    return false;
	}
	$s = fread($fp,8192);

	// We use the trick with array_merge() to get a 0-based array instead of the 
	// 1-based array that is normally returned from unpack()
	$m = array_merge(unpack('n*',$s));

	$crc32 = crc32(implode('',$m));
	if( $crc32 != $this->iBitPosCRC32[$aIdx] ) {
	    $this->iError = -22;
	    return false;
	}

	$aOutputMatrice = array();
	for($i=0; $i < $size; ++$i ) {
	    for($j=0; $j < $size; ++$j ) {
		$aOutputMatrice[$i+1][$j+1] = $aDataBits[$m[$i*$size+$j]];
	    }
	}

	// Add alignment pattern to all sides according to specifications
	$b=1;
	for($i=0; $i<$size+2; ++$i) {
	    $aOutputMatrice[$i][0] = 1 ;
	    $aOutputMatrice[$i][$size+1] = $b ;
	    $b ^= 1;	    
	}
	$b = 1;
	for($i=0; $i<$size+2; ++$i) {
	    $aOutputMatrice[$size+1][$i] = 1;
	    $aOutputMatrice[0][$i] = $b;
	    $b ^= 1;
	}
    }
}

?>
