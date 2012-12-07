<?php
/*=======================================================================
 // File:        REED-SOLOMON.INC.PHP
 // Description: Classes to create Reed-Solomon code words
 //              and compute within a Galois field
 // Created:     2006-08-18
 // Ver:         $Id: reed-solomon.inc.php 988 2008-03-25 02:50:13Z ljp $
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

    function Log($aArg) {
        return $this->iLogTable[$aArg];
    }

    function Add($a,$b) {
        return ($a ^ $b ) & 0xFF;
    }

    function Mul($a,$b) {
        if( $a==0 || $b == 0 ) {
            return 0;
        }
        else {
            return $this->iInvLogTable[($this->iLogTable[$a] + $this->iLogTable[$b]) % ($this->iOrder-1)];
        }
    }
}

class ReedSolomon {
    private $iGalois;
    private $iC;
    private $iCodeWords=-1;

    function __construct($aCodeWords,$aPrimPol,$aZeroStart=true,$aWordSize=8) {

        $this->iGalois = new Galois($aWordSize,$aPrimPol);
        $this->iCodeWords = $aCodeWords;
        $this->InitGenPolynomial($aCodeWords,$aZeroStart);

    }

    function InitGenPolynomial($aN,$aZeroStart=true) {
        /*
        // Generate the generator polynomial.
        // The generator polynom order equals the number of error correcting
        // words wanted.
        //
        // This loop below calculates (within the Galois field selected)
        // the polynomial with roots (2^i), i.e
        //   (x-2^0) * (x-2^1) * (x-2^2) * ... * (x-2^(N-1))
        // where N = number of wanted error correcting words         
        */

        $this->iC = array();
        for($i=1; $i <= $aN; ++$i ) {
            $this->iC[$i] = 0;
        }

        /*
        // Polynomial multiplication - Algorithm description
        // The double loop below is only an elaborate way of calculating the product
        // of the linear factors (x-2^0)*(x-2^1)* ... *(x-2^(N-1))
        // This is done by realizing that we can calculate the factors for each power
        // recursively. This is most easy seen if we write the coefficients using the
        // following schema
        //
        //  q_N * X^N + q_(N-1) * X^(N-1) + ... + q_0 * X^0
        //
        //  For example: (using a as the primitive element)
        //
        //  (x+a_0) = 1*x + a_0                               => q1 = 1, q0 = a_0
        //  (x+a_0)*(x+a_1) = 1*x^2 + (a_1 + a_2)*x + a_1*a_2 => q2=1, q1 = a_1+a_2, q0 = a_1*a_2
        //
        //   and so on. If we now calculate this in a loop we can calculate successive factors
        //   recursively. By observing the following fact
        //
        //   f1(x) = (x+a_0)  = 1*x + a_0  => q1 = 1, q0 = a_0
        //   f2(x) = (x+a_0)*(x+a_1) = f1(x)*(x+a_1) = x*f1(x) + a_1*f1(x)
        //   f3(x) = (x+a_0)*(x+a_1)*(x+a_2) = f2(x)*(x+a_2) = x*f2(x) + a_2*f2(x)
        //   f4(x) = f3(x)*(x+a_3) = x*f3(x) + a_3*f3(x)
        //
        //   Writing this out and giving it some thought it is now relatively straightforward to
        //   realize that the coefficients in each step can be calculated as follows
        //
        //   q0=1
        //   q1=q0, q0=a_0
        //   q2=q1, q1=q0+a_1*q1, q0=q0*a_1
        //   q3=q2, q2=q1+q2*a_2, q1=q0+q1*a_2, q0=q0*a_2
        //
        //    or in genereal for each step, each term can be expressed as
        //
        //    q_n = q_(n-1) + a_(n-1)*q_n
        //
        //   In order to calculate the roots (i.e 2^0, 2^1, and so on) we use the inverse log in
        //   the specified Galois field, i.e. a^i = InvLog(i)
        //
        //   Since we start with a^0 in the loop we use InvLog($i-1) since $i starts at 1
        //   PLEASE NOTE that some applications of RS codes calculate the generatror polynoms
        //   starting with (2^1) instead of (2^0) as we do here.  For example the datamatrix
        //   code starts its generator polynoms at 2^1
		*/
        
        $zstart = $aZeroStart ? 1 : 0;

        $this->iC[0] =  1;
        for( $i=1; $i <= $aN; ++$i ) {
            $this->iC[$i] = $this->iC[$i-1];
            $alpha = $this->iGalois->InvLog($i-$zstart);
            for( $j=$i-1; $j >= 1; --$j ) {
                $this->iC[$j] = $this->iC[$j-1] ^ $this->iGalois->Mul($this->iC[$j],$alpha);
            }
            $this->iC[0] = $this->iGalois->Mul($this->iC[0],$alpha);
        }

    }

    function append(&$aData) {
        // Add error correcting codeWords to the end of the data
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

     echo "Generator polynom coefficients:\n";
     for($i=0; $i<count($this->iC); ++$i)
     echo $this->iC[$i].', ' ;
     echo "<br>\n";
     echo "Generator polynom ln(coefficients):\n";
     for($i=0; $i<count($this->iC); ++$i)
     echo $this->iGalois->Log($this->iC[$i]).', ' ;
     echo "<br>\n";

     return true;
     }
     */

}

//$rs = new ReedSolomon(7,285,true,8);
//$rs->_UnitTest();
//echo("<br>\n<b>Correct answer should be:</b><br>\n");
//echo("Generator polynom coefficients: 117, 68, 11, 164, 154, 122, 127, 1<br>\n");
//echo("Generator polynom ln(coefficients): 21, 102, 238, 149, 146, 229, 87, 255<br>\n");


?>
