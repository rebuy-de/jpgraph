<?php
/*=======================================================================
// File: 	BIT-PLACEMENT-BIN-200.INC.PHP
// Description:	Bit placement for ECC 200 using binary data files instead of
//		included array to reduce PHP parse time.
// Created: 	2006-08-22
// Ver:		$Id: bit-placement-bin-200.inc.php 1096 2009-01-23 16:48:38Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

class BitPlacement {
    public $iError=0;

    // Pre-computed CRC32 checksums for the bit aplcement matrices to make sure the
    // data is not corrupted. (Note CRC32 checksums are by definition unsigned but since
    // PHP internally only have signed ints this will make large unsigned integers be 
    // interpretated as negative integers.
    private $iBitPosCRC32 = array(2143887467, -1305697335, 1793684084, -1017869156, 1046660619, 1237736757, 
			      -777210908, 1896751866, -1387262519, 7163776, 1806399672, 1576954574, -711156989, 
			      1920394314, 175006902, -1460505690, 222060193, -1636275718, -58775795, 2062387741, 
			      -1113378016, 288660764, 241782375, -1403075103, -1375291187, -294574403, 782120793, 
			      144314971, 1922868282, -2001681531);
    private $iMappingSize = array(array( 8, 8),array(10,10),array(12,12),array(14,14),array(16,16),
			      array(18,18),array(20,20),array(22,22),array(24,24),array(28,28),
			      array(32,32),array(36,36),array(40,40),array(44,44),array(48,48),
			      array(56,56),array(64,64),array(72,72),array(80,80),array(88,88),
			      array(96,96),array(108,108),array(120,120),array(132,132),
			      array(6,16),array(6,28),array(10,24),array(10,32),array(14,32),array(14,44) );

    function BitPlacement() {
	// Empty
    }

    function GetError() {
	return $this->iError;
    }
    
    function Set($aIdx,&$aDataBits,&$aOutputMatrice,$aInverse=false) {
	if( $aIdx < 0 || $aIdx >= 30 ) {
	    $this->iError = -20;
	    return false;
	}
	$dataLen = count($aDataBits);
	
	$nrow = $this->iMappingSize[$aIdx][0];
	$ncol = $this->iMappingSize[$aIdx][1];	    

	$fname = dirname(__FILE__)."/bindata/bitplacement_ECC200-{$nrow}x{$ncol}.dat";
	$fp = @fopen($fname,'r');
	if( $fp === false ) {
	    $this->iError = -21;
	    return false;
	}
	$s = fread($fp,40000);

	// We use the trick with array_merge() to get a 0-based array instead of the 
	// 1-based array that is normally returned from unpack()
	$m = array_merge(unpack('n*',$s));

	/*$crc32 = crc32(implode('',$m));
	if( $crc32 != $this->iBitPosCRC32[$aIdx] ) {
	    $this->iError = -22;
	    return false;
	}*/

	if( $aInverse ) {
	    // All internal alignment patterns must have been removed 
	    $rows = count($aOutputMatrice);
	    $cols = count($aOutputMatrice[0]);
	    if(  $nrow > $rows || $ncol > $cols ) {
		$this->iError = -23;
		return false;
	    }
	    $aDataBits = array();
	    for($i=0; $i < $nrow; ++$i ) {
		for($j=0; $j < $ncol; ++$j ) {
		    $bitidx = $m[$i*$ncol+$j];
		    // The special values 2^15 and 2^15-1 in the mapping matrice are used to indicate fixed
		    // 1 or 0 in the final output matrice to make it square that doesn't have any corresponding data
		    if( $bitidx != 32767 && $bitidx != 32768 ) {
			$aDataBits[$bitidx] = $aOutputMatrice[$i+1][$j+1];
		    }
		}
	    }	

	    return array($nrow,$ncol);
	}
	else {

	    $aOutputMatrice = array();
	    for($i=0; $i < $nrow; ++$i ) {
		for($j=0; $j < $ncol; ++$j ) {
		    $bitidx = $m[$i*$ncol+$j];
		    if( $bitidx == 32767 ) 
			$aOutputMatrice[$i+1][$j+1] = 1;
		    elseif( $bitidx == 32768 ) 
			$aOutputMatrice[$i+1][$j+1] = 0;
		    else {
			if( $bitidx >= $dataLen ) {
			    $this->iError = -24;
			    return false;
			}
			$aOutputMatrice[$i+1][$j+1] = $aDataBits[$bitidx];
		    }
		}
	    }

	    // Add alignment pattern to all sides according to specifications
	    // The way this is doen depends on the chosen symbol shape (and size)
	    if( $aIdx >= 0 && $aIdx <= 23 ) {
		// Square symbols
		if( $aIdx >= 0 && $aIdx <= 8 ) {
		    $nrow += 2; $ncol += 2;
		    $nregions = 1; // SQR(Number of data regions)
		    $regionsize = ($nrow-2)/1; // Mapping size 
		}
		elseif( $aIdx >= 9 && $aIdx <= 14 ) {
		    $nrow += 4; $ncol += 4;
		    $nregions = 2; // SQR(Number of data regions)
		    $regionsize = ($nrow-4)/2; // Mapping size / 2
		}
		elseif( $aIdx >= 15 && $aIdx <= 20 ) {
		    $nrow += 8; $ncol += 8;
		    $nregions = 4; // SQR(Number of data regions)
		    $regionsize = ($nrow-8)/4; // Mapping size / 4
		}
		else {
		    $nrow += 12;  $ncol += 12;
		    $nregions = 6; // SQR(Number of data regions)
		    $regionsize = ($nrow-12)/6; // Mapping size / 6
		}

		// Initialize the temprary matrice that is used when adding alignment 
		// patterns
		$tmp = array();
		for($i=0; $i < $nrow; ++$i ) {
		    for($j=0; $j < $ncol; ++$j ) {
			$tmp[$i][$j] = 0 ;
		    }
		}
		
		// Copy the data into the appropriate data regions
		for($i=0; $i < $nregions; ++$i ) {
		    for($j=0; $j < $nregions; ++$j ) {
			for($k=0; $k < $regionsize; ++$k ) {
			    for($l=0; $l < $regionsize; ++$l ) {
				$tmp[$i*$regionsize + $i*2 + $k + 1][$j*$regionsize + $j*2 + $l + 1 ] = 
				    $aOutputMatrice[$i*$regionsize + $k + 1][$j*$regionsize + $l + 1];
			    }
			}
		    }
		}

		// Horizontal patterns
		$b = 1;
		for($i=0; $i < $nregions-1; ++$i ) {
		    for($j=0; $j < $ncol; ++$j) {
			$tmp[($i+1)*$regionsize+1 + $i*2][$j] = 1 ;
			$tmp[($i+1)*$regionsize+2 + $i*2][$j] = $b ;
			$b ^= 1;	    
		    }
		}

	    }
	    else {
		// aIdx >= 24 and indicates a rectangular pattern
		if( $aIdx == 24 || $aIdx == 26 ) {
		    $nrow += 2; $ncol += 2;
		    $nregions = 1; // SQR(Number of data regions)
		    $regionsize = ($ncol-2)/1; // Mapping size 
		}
		elseif( $aIdx==25 || ($aIdx >= 27 && $aIdx <= 29) ) {
		    $nrow += 2; $ncol += 4;
		    $nregions = 2; // SQR(Number of data regions)
		    $regionsize = ($ncol-4)/2; // Mapping size 
		}
		else {
		    $this->iError = -25;
		    return false;
		}

		// Initialize the temporary matrice that is used when adding alignment 
		// patterns
		$tmp = array();
		for($i=0; $i < $nrow; ++$i ) {
		    for($j=0; $j < $ncol; ++$j ) {
			$tmp[$i][$j] = 0 ;
		    }
		}

		// Copy the data into the appropriate data regions
		for($j=0; $j < $nregions; ++$j ) {
		    for($k=0; $k < $nrow-2; ++$k ) {
			for($l=0; $l < $regionsize; ++$l ) {
			    $tmp[$k + 1][$j*$regionsize + $j*2 + $l + 1 ] = $aOutputMatrice[$k + 1][$j*$regionsize + $l + 1];
			}
		    }
		}
	    }

	    // Add Vertical alignment patterns
	    $b = 0;
	    for($i=0; $i < $nregions-1; ++$i ) {
		for($j=0; $j < $nrow; ++$j) {
		    $tmp[$j][($i+1)*$regionsize+1 + $i*2] = $b ;
		    $tmp[$j][($i+1)*$regionsize+2 + $i*2] = 1 ;
		    $b ^= 1;	    
		}
	    }

	    // Add outer vertical alignment pattern 
	    $b = 0; 
	    for($i=0; $i<$nrow; ++$i) {
		$tmp[$i][0] = 1 ;
		$tmp[$i][$ncol-1] = $b ;
		$b ^= 1;	    
	    }

	    // Add outer horizontal alignment pattern 
	    $b = 1;
	    for($i=0; $i<$ncol; ++$i) {
		$tmp[0][$i] = $b;
		$tmp[$nrow-1][$i] = 1;
		$b ^= 1;
	    }

	    $aOutputMatrice = $tmp;
	}
	return true;
    }
}

?>
