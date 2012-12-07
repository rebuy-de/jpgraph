<?php
/*=======================================================================
// File: 	CONV.INC.PHP
// Description:	Classes to handle convolution FEC codes
// Created: 	2006-08-18
// Ver:		$Id: conv-140.inc.php 988 2008-03-25 02:50:13Z ljp $
//
// Copyright (c) 2006 Asial Corporation. All rights reserved.
//========================================================================
*/

require_once('dm-utils.inc.php');

// Abstract base class for all convolutional coders
class ConvolutionCoding {
    private $iSpec = array();   // Output bits, Input bits, Memory length
    private $iGenMat = array(); // Matrice for generating functions
    private $iName = '';
    private $iHeader = 0;
    private $iDebugPrintLevel = 0;

    function ConvolutionCoding($aOutputBits,$aInputBits,$aNumShifts,&$aGenMat,$aName,$aHeader) {
	$this->iSpec = array($aOutputBits,$aInputBits,$aNumShifts);
	$this->iGenMat = $aGenMat;
	$this->iName = $aName;
	$this->iHeader = $aHeader;
    }

    // Matrix multiplication to generate output bits from function 
    // generation matrix
    function _genOutput($aMem) {
	$v = array();
	for($i=0; $i < $this->iSpec[0]; ++$i ) {
	    $sum=0;
	    for($j=0; $j < $this->iSpec[1]; ++$j ) {
		$n = $this->iSpec[2]+1;
		for($k=0; $k < $n; ++$k ) {
		    $sum ^= $this->iGenMat[$i][$j*$n + $k] * 
			$aMem[$j][$k];
		}
	    }
	    $v[$i] = $sum;
	}
	return $v;
    }

    function GetHeader() {
	return array_reverse(str_split($this->iHeader));
    }

    function Get($aData) {
	$bits = array();
	ByteArray2Bits($aData,$bits);
	return $this->_Get($bits);
    }

    function UnitTestInfo($aTxt,$aLevel=1) {
	if( $aLevel <= $this->iDebugPrintLevel ) 
	    echo $aTxt;
    }

    // The input to this method is the bit array corresponding to the
    // data buffer. Position 0 <==> MSB 
    function _Get($aDataBits,&$outputBits,$aAddFlushBits=true) {

	// Number of loops required to pass all data through the shift
	// registers = dataLength / inputBits
	// Note: The data should be padded with 0 to clear out all the
	// shift registers att the end. Needs (memory length * input bits) bits
	$totInputBits = count($aDataBits);

	if( $totInputBits % $this->iSpec[1] ) {
	    // Add necessary padding bits
	    $padBits = $this->iSpec[1] - ($totInputBits % $this->iSpec[1]);
	    for($i=0; $i < $padBits; ++$i ) {
		$aDataBits[$totInputBits+$i] = 0;
	    }
	    $totInputBits += $padBits;
	    echo "Adding $padBits pad bits\n";
	}

	$nbrLoops = $totInputBits / ($this->iSpec[1]);
	$bitIndex = 0;
	$outputBitIndex = 0;
	$outputBits = array();

	if( $aAddFlushBits ) {
	    // Add zero bits to flush the shift registers
	    $m = $this->iSpec[1]*$this->iSpec[2];
	    $this->UnitTestInfo("Adding $m flush bits\n");
	    for( $i=$totInputBits; $i < $totInputBits+$m; ++$i ) {
		$aDataBits[$i] = 0;
	    }
	    $totInputBits += $m;
	    $nbrLoops = $totInputBits / ($this->iSpec[1]);
	}

	// $this->UnitTestInfo(sprintf("Number of state machine cycles=%d\n",$nbrLoops));

	// Initialize shift register to 0
	for($m=0; $m < $this->iSpec[1]; ++$m ) {
	    for($n=0; $n < $this->iSpec[2]+1; ++$n ) {
		$u[$m][$n] = 0;
	    }
	}

	for( $i=0; $i < $nbrLoops; ++$i ) {

	    //  $this->UnitTestInfo("\nState machine cycle=".($i+1)."\n",2);

	    // Print curent state before new input
	    /*
	    $txt = "";
	    for($m=0; $m < $this->iSpec[1]; ++$m ) {
		$txt .= "  u$m [";
		for($n=0; $n < $this->iSpec[2]; ++$n ) {
		    if( $n > 0) $txt .= ',';
		    $txt .= $u[$m][$n+1];
		}
		$txt .= "]\n";
	    }

	    $this->UnitTestInfo($txt,2);
	    */

	    // Store the new current bit in "dummy" shift 0
	    // to facilitate output generation in the same loop
	    for($j=0; $j < $this->iSpec[1]; ++$j ) {
		$u[$j][0] = $aDataBits[$bitIndex++];
	    }

	    /*
	    $txt = "  u => [";
	    for($n=0; $n < $this->iSpec[1]; ++$n ) {
		if( $n > 0) $txt .= ',';
		$txt .= $u[$n][0];
	    }
	    $txt .= "]\n";
	    $this->UnitTestInfo($txt,2);
	    */

	    // Generate output bits
	    $v = $this->_genOutput($u);
	    for($j=0; $j < $this->iSpec[0]; ++$j ) {
		$outputBits[$outputBitIndex++] = $v[$j];
	    }

	    /*
	    $txt = "";
	    $txt .= "  v => [";
	    for($n=0; $n < $this->iSpec[0]; ++$n ) {
		if( $n > 0) $txt .= ',';
		$txt .= $v[$n];
	    }
	    $txt .= "]\n";
	    $this->UnitTestInfo($txt,2);
	    */

	    // Shift all memblocks one position
	    for($j=0; $j < $this->iSpec[1]; ++$j ) {
		for($k=$this->iSpec[2]; $k > 0 ; --$k ) {
		    $u[$j][$k] = $u[$j][$k-1];
		}
	    }

	    for($j=0; $j < $this->iSpec[1]; ++$j ) {
		$u[$j][1] = $u[$j][0];
	    }

	    
	}
    }

/*
    function _UnitTest($bitTestData,$bitTestDataVer) {

	$this->UnitTestInfo("\nUnit Test {$this->iName}\n");

	$outputBits=array();
	$this->_Get($bitTestData,$outputBits);

	$n = count($outputBits);
	$m = count($bitTestDataVer);
	$nn = count($bitTestData);

	$this->UnitTestInfo("\nInput size: $nn, Output size: $n\n");

	if( $m == 0 ) {
	    $this->UnitTestInfo("\nNo Verification data specified.\n");
	}
	else {
	    $this->UnitTestInfo("\nVerifying result... ");
	    $err=false;
	    if( $n != $m ) {
		$err=true;
		$this->UnitTestInfo("\nWrong number of output bits ($n) expected ($m) ");
	    }
	    else {
		for($i=0; $i < $m; ++$i) {
		    if( $outputBits[$i] != $bitTestDataVer[$i] ) {
			$err=true;
			$this->UnitTestInfo("\nOutput bit $i is Wrong. \n");
		    }
		}
	    }
	    if( !$err ) {
		$this->UnitTestInfo("CORRECT\n\n");
		return true;
	    }
	    else {
		$this->UnitTestInfo("FAILED\n\n");
		return true;
	    }
	}
    }
*/

}

/*------------------------------------------------------------------------
 ** ECC 050
 **
 ** Convolutional (4-3-3) code
 **
 ** Function generators
 **
 ** v1 = u10 + u23 + u31 + u32 + u33
 ** v2 = u12 + u13 + u20 + u21 + u23
 ** v3 = u11 + u12 + u13 + u21 + u30 + u31
 ** v4 = u10 + u11 + u20 + u21 + u22 + u30 + u31 + u33
 **------------------------------------------------------------------------
*/
class ECC_050 extends ConvolutionCoding {

    function ECC_050() {

	$genMat = array(

	/* v1 Generator */
	array(1,0,0,0,  /* u10 */ 
	      0,0,0,1,  /* u23 */
	      0,1,1,1),  /* u31, u32, u33 */

	/* v2 Generator */
	array(0,0,1,1,	/* u12, u13 */
	      1,1,0,1,	/* u20, u21, u23 */
	      0,0,0,0),

	/* v3 Generator */
	array(0,1,1,1,	/* u11, u12, u13 */
	      0,1,0,0,	/* u21 */
	      1,1,0,0), /* u30, u31*/

	/* v4 Generator */
	array(1,1,0,0,	/* u10, u11 */
	      1,1,1,0,	/* u20,u21,u22 */
	      1,1,0,1), /* u30, u31*/
	);
    
	parent::ConvolutionCoding(4,3,3,$genMat,'ECC_050','0001110000000001110');
    }

/*
    function _UnitTest() {
	// Test array and result from ISO/IEC 16022:2000(E) 
	$bitTestData = array(
	    0,0,0,1,0,1,0,0,1,1,0,1,0,1,0,1,0,1,1,1,0,0,1,1,0,0,0,0,0,0,0,0,1,
	    0,0,1,0,1,1,1,1,0,1,1,0,0,1,1,1,1,1,0,1,1,1,1,1,1,1,1,1,1,0);
	$bitTestDataCorrectOutput = array(
	    0,0,0,0,1,0,1,0,1,0,1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,0,1,0,0,0,0,0,0,1,0,0,
	    0,0,1,1,0,1,1,0,1,0,0,0,0,1,0,1,0,0,0,1,1,0,0,0,0,0,0,0,1,1,1,0,1,0,1,0,
	    1,0,0,1,1,0,1,0,1,0,0,1,1,0,0,0,0,1,0,0,1,0,1,0 );

	parent::_UnitTest($bitTestData,$bitTestDataCorrectOutput);

    }
*/

}


/*------------------------------------------------------------------------
 ** ECC 080
 **
 ** Convolutional (3-2-11) code
 **
 ** Function generators
 **
 ** v1 = u10 + u11 + u13 + u15 + u16 + u17 + u1_10+ u23 + u27 + u28 + u2_11
 ** v2 = u11 + u14 + u15 + u18 + u19 + u1_10 + u20 + u23 + u26 + u28 + u29
 ** v3 = u10 + u15 + u16 + u17 + u20 + u21 + u22 + u24 + u27 + u29 + u2_11
 **------------------------------------------------------------------------
*/
class ECC_080 extends ConvolutionCoding {

    function ECC_080() {

	$genMat = array(

	/* v1 Generator */
	array(1,1,0,2,0,1,1,1,0,0,1,0, 
	      0,0,0,1,0,0,0,1,1,0,0,1), 

	/* v2 Generator */
	array(0,1,0,0,1,1,0,0,1,1,1,0,	
	      1,0,0,1,0,0,1,0,1,1,0,0),

	/* v3 Generator */
	array(1,0,0,0,0,1,1,1,0,0,0,0,	
	      1,1,1,0,1,0,0,1,0,1,0,1)

	);
    
	parent::ConvolutionCoding(3,2,11,$genMat,'ECC_080','1110001110000001110');
    }    

/*
    function _UnitTest() {
	$bitTestData = array(
	    0,0,0,1,0,1,0,0,1,1,0,1,0,1,0,1,0,1,1,1,0,0,1,1,0,0,0,0,0,0,0,0,1,
	    0,0,1,0,1,1,1,1,0,1,1,0,0,1,1,1,1,1,0,1,1,1,1,1,1,1,1,1,1);
	$bitTestDataCorrectOutput = array();

	parent::_UnitTest($bitTestData,$bitTestDataCorrectOutput);
    }
*/

}

    
/*------------------------------------------------------------------------
 ** ECC 100
 **
 ** Convolutional (2-1-15) code
 **
 ** Function generators
 **
 ** v1 = u10 + u12 + u15 + u16 + u17 + u18 + u19 + u1_10 + u1_15
 ** v2 = u10 + u11 + u13 + u14 + u16 + u1_11 + u1_13 + u1_14 + u1_15
 **------------------------------------------------------------------------
*/
class ECC_100 extends ConvolutionCoding {

    function ECC_100() {

	$genMat = array(

	/* v1 Generator */
	array(1,0,1,0,0,1,1,1,1,1,1,0,0,0,0,1),
	    
	/* v2 Generator */
	array(1,1,0,1,1,0,1,0,0,0,0,1,0,1,1,1)

	);
    
	parent::ConvolutionCoding(2,1,15,$genMat,'ECC_100','1111111110000001110');
    }

/*
    function _UnitTest() {
	$bitTestData = array(
	    0,0,0,1,0,1,0,0,1,1,0,1,0,1,0,1,0,1,1,1,0,0,1,1,0,0,0,0,0,0,0,0,1,
	    0,0,1,0,1,1,1,1,0,1,1,0,0,1,1,1,1,1,0,1,1,1,1,1,1,1,1,1,1);
	$bitTestDataCorrectOutput = array();

	parent::_UnitTest($bitTestData,$bitTestDataCorrectOutput);
    }
*/

}
    
/*------------------------------------------------------------------------
 ** ECC 140
 **
 ** Convolutional (4-1-13) code
 **
 ** Function generators
 **
 ** v1 = u10 + u14 + u17 + u1_10 + u1_12 + u1_13
 ** v2 = u10 + u13 + u14 + u17 + u18 + u19 + u1_10 + u1_11 + u1_13
 ** v3 = u10 + u11 + u12 + u14 + u15 + u17 + u19 + u1_11 + u1_12 + u1_13
 ** v4 = u10 + u11 + u12 + u14 + u15 + u17 + u19 + u1_10 + u1_11 + u1_12 + u1_13
 **------------------------------------------------------------------------
*/
class ECC_140 extends ConvolutionCoding {

    function ECC_140() {

	$genMat = array(

	/* v1 Generator */
	array(1,0,0,0,1,0,0,1,0,0,1,0,1,1),
	    
	/* v2 Generator */
	array(1,0,0,1,1,0,0,1,1,1,1,1,0,1),

	/* v3 Generator */
	array(1,1,1,0,1,1,0,1,0,1,0,1,1,1),

	/* v4 Generator */
	array(1,1,1,0,1,1,0,1,0,1,1,1,1,1)

	);
    
	parent::ConvolutionCoding(4,1,13,$genMat,'ECC_140','1111110001110001110');
    }

/*
    function _UnitTest() {
	$bitTestData = array(
	    0,0,0,1,0,1,0,0,1,1,0,1,0,1,0,1,0,1,1,1,0,0,1,1,0,0,0,0,0,0,0,0,1,
	    0,0,1,0,1,1,1,1,0,1,1,0,0,1,1,1,1,1,0,1,1,1,1,1,1,1,1,1,1);
	$bitTestDataCorrectOutput = array();

	parent::_UnitTest($bitTestData,$bitTestDataCorrectOutput);
    }
*/

}

/*------------------------------------------------------------------------
 ** ECC None
 **
 ** Dummy ECC class that just returns the data
 **------------------------------------------------------------------------
 */
class ECC_NONE {
    private $iHeader = '1111110';

    function Get($aData) {
	$bits = array();
	ByteArray2Bits($aData,$bits);
	return $bits;
    }

    function GetHeader() {
	return array_reverse(str_split($this->iHeader));
    }  

    function _Get($aInputBits,&$aOutputBits) {
	$aOutputBits = $aInputBits;
    }

}

DEFINE("ECC_NONE",0);
DEFINE("ECC_050",1);
DEFINE("ECC_080",2);
DEFINE("ECC_100",3);
DEFINE("ECC_140",4);

class ECC_Factory {
    function Create($aCode) {
	$names = array("NONE","050","080","100","140");
	$className = 'ECC_'.$names[$aCode];
	return new $className;
    }
}


?>
