<?php
/*=======================================================================
// File: 	DATAMATRIX-200.INC.PHP
// Description:	Main Datamatrix encoding class for ECC 200
// Created: 	2006-08-23
// Ver:		$Id: datamatrix-200.inc.php 988 2008-03-25 02:50:13Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

require_once('reed-solomon.inc.php');
require_once('encodation-200.inc.php');
require_once('bit-placement-bin-200.inc.php');

class Datamatrix {
    public $iError = 0;
    private $iShapeIdx = -1; // Matrix shape index (-1 = smallest possible)
    private $iEncodation = null;
    private $iBitPlacement = null;
    private $iDebug=false;
    private $iTilde = false; // Preprocess data

    function Datamatrix($aShapeIdx=-1,$aDebug=false) {
	$this->iBitPlacement = new BitPlacement();
	$this->iEncodation = new Encodation_200();
	$this->iShapeIdx = $aShapeIdx;
	$this->iDebug = $aDebug;
    }

    function SetEncoding($aEncoding=ENCODING_ASCII) {
	$this->iEncodation->SetSchema($aEncoding) ;
    }

    function SetSize($aShapeIdx) {
	$this->iShapeIdx = $aShapeIdx;
    }

    function SetTilde($aFlg=true) {
	$this->iTilde = $aFlg;
    }

    function Enc($aData,$aDebug=false) {

	if( $this->iTilde ) {
	    $r = tilde_process($aData);
	    if( $r === false ) {
		$this->iError = -9;
		return false;
	    }
	    $aData = $r;
	}

	$data = str_split($aData);
	$ndata = count($data);
	$symbols = array();

	if( $this->iEncodation->Encode($data,$symbols,$this->iShapeIdx) === false ) {
	    $this->iError = $this->iEncodation->iError;
	    return false; 
	}
	
	/*
	if( $aDebug ) {
	    $n = count($this->iEncodation->iSymbols);
	    echo "Encoded symbols (len=$n)\n";
	    echo "=========================\n";
	    for($i=0; $i < $n; ++$i ) {
		printf("%3d:%3d,",$i,$this->iEncodation->iSymbols[$i]);
		if( ($i+1) % 15 == 0 ) echo "\n";
	    }
	    echo "\n";
	}
	*/

	// Calculate error codes and add them ot the end of the codeword data stream
	$this->iEncodation->AddErrorCoding();
	
	if( $this->iDebug ) 
	    $this->iEncodation->_printDebugInfo();

	$outputMatrix = array();
	$databits = array();
	ByteArray2Bits($this->iEncodation->iSymbols,$databits);
	$res = $this->iBitPlacement->Set($this->iEncodation->iSymbolShapeIdx,$databits,$outputMatrix);
	if( $res === false ) {
	    $this->iError = $this->iBitPlacement->iError;
	    return false; 	    
	}
	$pspec = new PrintSpecification(DM_TYPE_200,$data,$outputMatrix,$this->iEncodation->iSelectSchema);
	return $pspec;
    }
}

?>
