<?php
/*=======================================================================
// File: 	REED-SOLOMON.INC.PHP
// Description:	Classes to create Reed-Solomon code words
//		and compute within a Galois field
// Created: 	2006-08-18
// Ver:		$Id: reed-solomon.inc.php 988 2008-03-25 02:50:13Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

// Galois field GF(2^N)/Pol arithmetic
class Galois {
    private $iOrder = -1;
    private $iPrimPol = -1;
    private $iLogTable = array();
    private $iInvLogTable = array();

    // Create the field GF(2^aN)/aPol
    function Galois($aN,$aPol) {
	$this->iOrder = 1 << $aN;
	$this->iPrimPol = $aPol ;
	$this->InitLogTables();
    }

    function InitLogTables() {
	$this->iLogTable[0] = 1 - $this->iOrder;
	$this->iInvLogTable[0] = 1;

	for( $i=1; $i < $this->iOrder; ++$i ) {
	    $this->iInvLogTable[$i] = $this->iInvLogTable[$i-1] << 1;
	    if( $this->iInvLogTable[$i] >= $this->iOrder) {
		$this->iInvLogTable[$i] ^= $this->iPrimPol;
	    }
	    $this->iLogTable[$this->iInvLogTable[$i]] = $i;
	}
    }

    function InvLog($aArg) {
	return $this->iInvLogTable[$aArg];
    }

    function Mul($a,$b) {
	if( $a==0 || $b == 0 ) {
	    return 0;
	}
	else {
	    return $this->iInvLogTable[($this->iLogTable[$a] + $this->iLogTable[$b]) % ($this->iOrder-1)];
	}
    }

    /*
    function _UnitTest() {
	if( $this->iOrder == 1 << 8 && $this->iPrimPol == 0x012d ) {
	    $t = $this->Mul(2,4);
	    $t = $this->Mul($t,8);
	    $t = $this->Mul($t,16);
	    $t = $this->Mul($t,32);
	    echo "2*4*8*16*32 = $t\n";
	    if( $t == 228 ) {
		echo "CORRECT\n";
	    }
	    else {
		echo "FAILED. Expected 228\n";
	    }
	}
	else {
	    echo "No test data exists for order={$this->iOrder} and poly={$this->iPrimPol}\n";
	}
	return false;
    }
    */

}

class ReedSolomon {
    private $iGalois;
    private $iC;
    private $iCodeWords=-1;

    function ReedSolomon($aWordSize,$aCodeWords) {

	// Which primitive polynomial to use for each word length
	$poly = array(6 => 67, 8 => 301, 10 => 1033, 12 => 4201);

	$keys = array_keys($poly);
	if( !in_array($aWordSize,$keys) ) {
	    return false;
	}

	$this->iGalois = new Galois($aWordSize,$poly[$aWordSize]);
	$this->iCodeWords = $aCodeWords;
	$this->InitGenPolynomial($aCodeWords);

    }

    function InitGenPolynomial($aN) {
	// Generate the generator polynomial.
	// The generator polynom order equals the number of error correcting
	// words wanted. 
	//
	// This loop below calculates (within the Galois field selected)
	// the polynomial with roots (2^i), i.e 
	//   (x-2^0) * (x-2^1) * (x-2^2) * ... * (x-2^(N-1)) 
	// where N = number of wanted error correcting words

	$this->iC = array();
	$this->iC[0] = 1;
	for($i=1; $i <= $aN; ++$i ) {
	    $this->iC[$i] = 0;
	}
	for($i=1; $i <= $aN; ++$i ) {
	    $this->iC[$i] = $this->iC[$i-1];
	    $tmp = $this->iGalois->InvLog($i);
	    for($j=$i-1; $j >= 1; --$j ) {
		$this->iC[$j] = $this->iC[$j-1] ^ $this->iGalois->Mul($this->iC[$j],$tmp);
	    }
	    $this->iC[0] = $this->iGalois->Mul($this->iC[0],$tmp);
	}
    }

    function AppendCode(&$aData) {
	// Add codeWords to the end of the data
	$n = count($aData);
	for($i=$n; $i <= ($n+$this->iCodeWords); ++$i ) 
	    $aData[$i] = 0;

	for($i=0; $i < $n; ++$i ) {
	    $k = $aData[$n] ^ $aData[$i];
	    for($j=0; $j < $this->iCodeWords; ++$j ) {
		$aData[$n+$j] = $aData[$n+$j+1] ^ $this->iGalois->Mul($k,$this->iC[$this->iCodeWords-$j-1]);
	    }
	}
	// Need to unset the last element since that is not part of the final data
	// But only used in the loop above (initialized to zero)
	unset($aData[$n+$this->iCodeWords]);
    }
    
    /*
    function _UnitTest() {
	$this->iGalois->_UnitTest();

	echo "Generator polynom coefficients:\n";
	print_r($this->iC);

	echo "\n";
	return true;
    }
    */
}

// x^8 + x^5 + x^3 + x^2 + 1 == 0x12d
//$g = new Galois(8,0x012d);

//$rs = new ReedSolomon(6,7);
//$rs->_UnitTest();

?>
