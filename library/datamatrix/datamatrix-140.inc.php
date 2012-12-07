<?php
/*=======================================================================
// File: 	DATAMATRIX-140.INC.PHP
// Description:	Main Datamatrix encoding class for ECC 1440
// Created: 	2006-08-20
// Ver:		$Id: datamatrix-140.inc.php 988 2008-03-25 02:50:13Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/
require_once 'conv-140.inc.php';
require_once 'encodation-140.inc.php';
require_once 'bit-placement-bin-140.inc.php';
require_once 'master-rnd-140.inc.php';
require_once 'crc-ccitt.inc.php';

class Datamatrix_140 {
    public $iPublic = 0 ;
    private $iSize = -1;
    private $iErrLevel = ECC_050; // Default to ECC050
    private $iEncodation = null;
    private $iCRC_CCITT = null;
    private $iConv = null;
    private $iTilde = false; // Preprocess data

    function Datamatrix_140($aSize=-1,$aDebug=false) {
	$this->iEncodation = new Encodation_140();
	$this->iEncodation->iSelectSchema = ENCODING_BASE11;
	$this->iCRC_CCITT = new CRC_CCITT();
	$this->iMasterRand = new MasterRandom();

	if( $this->iMasterRand === false ) {
	    $this->iError = -32;
	    return false;
	}

	$this->iBitPlacement = new BitPlacement_140();

	// User can specify a size. If -1 then the size will be automatically selected
	// to be the smallest size that fits the data. Only odd numbers between 7-47 are
	// valid sizes.
	if( $aSize >= 0 ) {
	    $aSize -= 50; // Get rid of the flag offset
	    if( $aSize < 0 || $aSize > 20 ) {
		$this->iError = -30;
		return false;
	    }
	    $this->iSize = $aSize*2+7;
	}
    }

    function SetEncoding($aEncoding=ENCODING_BASE11) {
	$this->iEncodation->SetSchema($aEncoding);
    }

    function SetSize($aSize=-1) {
	// User can specify a size. If -1 then the size will be automatically selected
	// to be the smallest size that fits the data. Only odd numbers between 7-47 are
	// valid sizes.
	if( $aSize >= 0 ) {
	    $aSize -= 50; // Get rid of the flag offset
	    if( $aSize < 0 || $aSize > 20 ) {
		$this->iError = -30;
		return false;
	    }
	    $this->iSize = $aSize*2+7;
	}
    }

    function SetErrLevel($aErrLevel) {
	$this->iErrLevel = $aErrLevel ;
    }

    function SetTilde($aFlg=true) {
	$this->iTilde = $aFlg;
    }

    // Encode data and return matrix print specification
    function Enc($aData,$aDebug=false) {

	if( $this->iTilde ) {
	    $r = tilde_process($aData);
	    if( $r === false ) {
		$this->iError = -10;
		return false;
	    }
	    $aData = $r;
	}

	$this->iConv = null;
	$this->iConv = ECC_Factory::Create($this->iErrLevel);

	// Start by splitting the input string to an array
	$data = str_split($aData);
	$ndata = count($data);

	// Automatically select the smallest encodation schema
	$this->iEncodation->AutoSelect($data);

	// Create the output bit array
	$bits = array();

	// Get 5 Prefix bits that specified format id
	$bits = $this->iEncodation->GetPrefix();
	$bidx = 5;

	// Calculate the CRC-CCITT (16 bits) for the original data and add it master bit stream. 
	$crc_prefix = array(chr($this->iEncodation->GetCRCPrefix()),chr(0));
	$crc_data = array_merge($crc_prefix,$data);
	$crc = $this->iCRC_CCITT->Get($crc_data);

	$crcbits=array();
	Word2Bits($crc,$crcbits,16);
	for( $i=0; $i < 16; ++$i ) {
	    $bits[$bidx++] = $crcbits[$i];
	}

	// Get data length as a 9 bit sequence bit reversed
	$lenbits = array();
	Word2Bits($ndata,$lenbits,9);
	$lenbits = array_reverse($lenbits);
	for($i=0; $i < 9; ++$i) {
	    $bits[$bidx++] = $lenbits[$i];
	}
	
	// Encode data and copy to master bit stream.
	$databits = array();
	$this->iEncodation->Encode($data,$databits);

	// Number of code words. Each codeword is represented as an array of bits
	$m = count($databits);

	// In preparation to adding to the master bit stream each symbol
	// must first be bit reversed according to the standard
	for($i=0; $i < $m; ++$i ) {
	    $databits[$i] = array_reverse($databits[$i]);
	}

	// Add each code word in its bit-reversed form to the master bit stream
	for($i=0; $i < $m; ++$i ) {
	    $k = count($databits[$i]);
	    for($j=0; $j < $k; ++$j ) {
		$bits[$bidx++] = $databits[$i][$j];
	    }
	}

	// Now do the convolutional coding to create the protected bit stream
	$protectedbits = array();
	$this->iConv->_Get($bits,$protectedbits);

	// Now get the header (depends on the ECC chosen)
	$headerbits = $this->iConv->GetHeader();

	// Find out how many trailer bit (set to zero) we need to either
	// a) Make it the smallest possible size of matrix or
	// b) Fill it out to the user specified size of matrix
	$totBits = count($headerbits) + count($protectedbits);
	if( $this->iSize == -1 ) {
	    // Find the smallest possible size to use
	    $mat_size = 7;
	    $mat_idx = 0;
	    while( ($mat_size <= 47) && ($mat_size*$mat_size < $totBits) ) {
		$mat_idx++;
		$mat_size += 2 ;
	    }
	    if( $mat_size > 47 ) {
		$this->iError = -31;
		return false;
	    }

	    $this->iSize = $mat_size  ;
	    $ntrailerbits = $mat_size*$mat_size - $totBits;
	}
	else{
	    // User specified size
	    $mat_size = $this->iSize;
	    if( $mat_size*$mat_size < $totBits ) {
		$this->iError = -31;
		return false;
	    }
	    $ntrailerbits = $mat_size*$mat_size - $totBits;
	    $mat_idx = ($mat_size-7)/2;
	}
	$trailerbits = array_fill(0,$ntrailerbits,0);

	// We now have the final bit stream by concatenating
	// header + protected bit stream + trailer bits
	$bits = array_merge($headerbits,$protectedbits,$trailerbits);
	$ret = $this->iMasterRand->Randomize($bits);
	if( $ret === false ) {
	    $this->iError = -33;
	    return false;
	}

	// Place the bits in the matrice according to the bit placement and
	// add alignment edges to the output matrix
	$outputMatrix=array(array(),array());
	$this->iBitPlacement->Set($mat_idx,$bits,$outputMatrix);

	$pspec = new PrintSpecification(DM_TYPE_140,$data,$outputMatrix,$this->iEncodation->iSelectSchema,$this->iErrLevel);
	return $pspec;
    }
}



?>
