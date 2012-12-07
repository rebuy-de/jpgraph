<?php
/*=======================================================================
 // File:          QRLAYOUT.PHP
 // Description:   Classes to layout the QR code in a matrix
 // Created:       2008-07-24
 // Ver:           $Id: qrlayout.inc.php 1106 2009-02-22 20:16:35Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

require_once ('qrmask.inc.php');

// This class is responsible for taking an array of code words and place them in a
// QR Matrix of the specified version
class QRMatrixLayout {
    const UNINIT = '?';
    const FINDER = 'X';
    const TIMING = '#';
    const ZERO = '0';
    const ONE = '1';
    const ALIGNMENT = 'X';
    const QUIET = ' ';
    const VERSION = 'V';
    const FORMAT = 'F';


    // The following three variable must be public since they are used by the backend

    // Since we can use this backend with other 2D codes that are not necessarily
    // square record the width and height
    public $iSize = array(); // Number of rows and columns
    // Matrix to hold the actual matrix values
    public $iMatrix = array();
    // Left and right border is not used for QR barcodes
    public $iDrawLeftBottomBorder = false;
    // QR code does not allow color inversion
    public $iAllowColorInversion = false;
    // QR Quiet zone is 4 modules
    public $iQuietZone = 4;

    //
    // Mainly for verification purposes we can force a selected mask pattern
    // by setting this instance variable to the mask value 0<= x <= 7
    public $iForcedMaskIdx = -1;

    private $iDbgInfo = '', $iDbgLevel = 0;
    // Remember which matrix mask we selected
    public $iMaskIdx = -1;
    // Instance of QRCapacity to get format data
    private $iQRCap = null;
    // Keep track of the version and error correction level we are using
    public $iVersion = -1, $iErrLevel = -1;

    function __construct($aVersion, $aErrLevel) {
        $this->iErrLevel=$aErrLevel;
        $this->iVersion=$aVersion;
        $this->QRCap=QRCapacity::getInstance();
        $n=$this->QRCap->getDimension($aVersion);

        $this->iSize=array ( $n, $n );

        for( $i = 0; $i < $n; ++$i ) {
            for( $j = 0; $j < $n; ++$j ) {
                $this->iMatrix[$i][$j] = QRMatrixLayout::UNINIT;
            }
        }

        // ----------------------------------------------------
        // Add finder box pattern to upper left, upper right and
        // lower left corner of the matrix
        // ----------------------------------------------------
        $pos=array ( array ( 0, 0 ), array ( 0, $n - 7 ), array ( $n - 7, 0 ) );

        for( $f = 0; $f < 3; ++$f ) {
            $px = $pos[$f][0];
            $py=$pos[$f][1];

            for( $i = 0; $i < 7; ++$i ) {
                for( $j = 0; $j < 7; ++$j ) {
                    $this->iMatrix[$i + $px][$j + $py] = QRMatrixLayout::FINDER;
                }
            }

            for( $k = 1; $k <= 5; ++$k ) {
                $this->iMatrix[$px + 1][$py + $k] = QRMatrixLayout::QUIET;
                $this->iMatrix[$px + 5][$py + $k]=QRMatrixLayout::QUIET;
                $this->iMatrix[$px + $k][$py + 1]=QRMatrixLayout::QUIET;
                $this->iMatrix[$px + $k][$py + 5]=QRMatrixLayout::QUIET;
            }
        }

        // Add quiet zone to finder patterns
        // TL
        for( $k = 0; $k < 8; ++$k ) {
            $px = 0;
            $py=0;
            $this->iMatrix[$py + 7][$px + $k]=QRMatrixLayout::QUIET;
            $this->iMatrix[$k][$px + 7]=QRMatrixLayout::QUIET;
        }

        // BL
        for( $k = 0; $k < 8; ++$k ) {
            $px = 0;
            $py=$n - 8;
            $this->iMatrix[$py + $k][$px + 7]=QRMatrixLayout::QUIET;
            $this->iMatrix[$py][$k]=QRMatrixLayout::QUIET;
        }

        // TR
        for( $k = 0; $k < 8; ++$k ) {
            $px = $n - 8;
            $py=0;
            $this->iMatrix[$k][$px]=QRMatrixLayout::QUIET;
            $this->iMatrix[$py + 7][$px + $k]=QRMatrixLayout::QUIET;
        }

        // ----------------------------------------------------
        // Add timing patterns
        // ----------------------------------------------------
        $i=6;
        $cnt=1;

        for( $j = 8; $j < $n - 8; ++$j ) {
            if ( $cnt % 2 == 1 )
            $v=QRMatrixLayout::TIMING;
            else
            $v=QRMatrixLayout::QUIET;

            $this->iMatrix[$i][$j]=$v;
            $this->iMatrix[$j][$i]=$v;
            ++$cnt;
        }

        // ----------------------------------------------------
        // Add alignment box patterns
        // ----------------------------------------------------
        $coord=$this->QRCap->getAlignmentPositions($aVersion);
        $nalign=count($coord);

        for( $i = 0; $i < $nalign; ++$i ) {
            $x = $coord[$i][0] - 2;
            $y=$coord[$i][1] - 2;

            for( $j = 0; $j < 5; ++$j ) {
                for( $k = 0; $k < 5; ++$k ) {
                    $this->iMatrix[$x + $j][$y + $k] = QRMatrixLayout::ALIGNMENT;
                }
            }

            for( $k = 1; $k <= 3; ++$k ) {
                $this->iMatrix[$x + 1][$y + $k] = QRMatrixLayout::QUIET;
                $this->iMatrix[$x + 3][$y + $k]=QRMatrixLayout::QUIET;
                $this->iMatrix[$x + $k][$y + 1]=QRMatrixLayout::QUIET;
                $this->iMatrix[$x + $k][$y + 3]=QRMatrixLayout::QUIET;
            }
        }

        // ----------------------------------------------------
        // Add placeholder for Format information
        // ----------------------------------------------------
        // TL
        for( $k = 0; $k < 9; ++$k ) {
            // Position 6 is occupied with timing pattern
            if ( $k != 6 ) {
                $this->iMatrix[8][$k]=QRMatrixLayout::FORMAT;
                $this->iMatrix[$k][8]=QRMatrixLayout::FORMAT;
            }
        }

        for( $k = 0; $k < 8; ++$k ) {
            // BL
            $this->iMatrix[$n - 8 + $k][8] = QRMatrixLayout::FORMAT;
            // TR
            $this->iMatrix[8][$n - 8 + $k]=QRMatrixLayout::FORMAT;
        }

        // ----------------------------------------------------
        // Add placeholder for Version information
        // ----------------------------------------------------
        if ( $aVersion >= 7 ) {
            for( $k = 0; $k < 6; ++$k ) {
                $this->iMatrix[$k][$n - 11] = QRMatrixLayout::VERSION;
                $this->iMatrix[$k][$n - 10]=QRMatrixLayout::VERSION;
                $this->iMatrix[$k][$n - 9]=QRMatrixLayout::VERSION;

                $this->iMatrix[$n - 11][$k]=QRMatrixLayout::VERSION;
                $this->iMatrix[$n - 10][$k]=QRMatrixLayout::VERSION;
                $this->iMatrix[$n - 9][$k]=QRMatrixLayout::VERSION;
            }
        }
    }

    function SetDebugLevel($aLevel) {
        $this->iDbgLevel=$aLevel;
        $mask=QRMask::getInstance();
        $mask->SetDebugLevel($aLevel);
    }

    function _dbgInfo($aLevel, $aString) {
        if ( $aLevel <= $this->iDbgLevel ) {
            $this->iDbgInfo .= $aString;
        }
    }

    function __toString() {
        if ( $this->iDbgLevel < 2 ) return '';

        $n=$this->QRCap->getDimension($this->iVersion);

        $errStr=array ( 'L', 'M', 'Q', 'H' );

        $s=
            "============================================\nFinal Matrix layout:\nVersion: {$this->iVersion}-{$errStr[$this->iErrLevel]}, Size: $n*$n, Mask: {$this->iMaskIdx}\n============================================\n";

        for( $i = 0; $i < $n; ++$i ) {
            for( $j = 0; $j < $n; ++$j ) {
                $s .= $this->iMatrix[$i][$j] == QRMatrixLayout::ZERO ? '-' : 'X'; // $this->iMatrix[$i][$j] ;
            }

            $s .= "\n";
        }

        $s .= "\n" . $this->iDbgInfo . "\n";

        if ( $this->iDbgLevel > 2 ) {
            $mask=QRMask::getInstance();
            $s .= "====================== MASK DEBUG INFORMATION ===========================\n";
            $s .= $mask;
        }

        return $s;
    }

    // Take the given bit stream and position it in the Matrix according
    // to the rules for QR Data matrix bit positioning
    function placeBitStream($aBitStream) {
        // Check so that we have expected nuymber of bits in the stream
        $totcodewords=$this->QRCap->getTotalCodewords($this->iVersion);
        $totbits=$totcodewords * 8;
        $n=count($aBitStream);

        if ( $n != $totbits ) {
            //throw new QRException(
            //          "Internal error: Was expecting $totbits bits in version {$this->iVersion} to be placed in matrix but got $n bits",
            //          -1);
            throw new QRExceptionL(1200,$totbits,$this->iVersion,$n);
        }

        $size=$this->QRCap->getDimension($this->iVersion);
        $nbrpad=$this->QRCap->getRemainderBits($this->iVersion);

        $this->_dbgInfo(2, "Adding pad bits: $nbrpad bits needed\n");

        if ( $nbrpad > 0 ) {
            $pad=array_fill(0, $nbrpad, 0);
            $aBitStream=array_merge($aBitStream, $pad);
        }

        $bIdx=0;
        $n=count($aBitStream);

        $x=$y = $size - 1;
        $safety=177 * 177;
        $cnt=0;
        $direction=0; // 0=Up, 1=Down;

        while( $bIdx < $n && $cnt <= $safety ) {
            if ( $x < 0 || $y >= $size + 1 ) {
                // throw new QRException("Internal error: Trying to position bit outside the matrix x=$x, y=$y, size=$size, bIdx=$bIdx, direction=$direction\n");
                throw new QRExceptionL(1201,$x,$y,$size,$bIdx);
            }

            // Special case to avoid the left vertical timing pattern
            if ( $x == 6 )
            --$x;

            if ( $direction == 0 ) {
                // Direction up
                if ( $y < 0 ) {
                    // We have reached the top and we need to change direction
                    // and move one double module ot the left
                    $direction=1;
                    $x -= 2;
                    $y=0;
                }
                else {
                    $val_left=$this->iMatrix[$y][$x - 1];
                    $val_right=$this->iMatrix[$y][$x];

                    if ( $val_left !== QRMatrixLayout::UNINIT && $val_right !== QRMatrixLayout::UNINIT ) {
                        // Both positions are taken and we move up on row
                        $y -= 1;
                    }
                    elseif( $val_right !== QRMatrixLayout::UNINIT ) {
                        // The right part of the module is occupied so we put the next bit
                        // in the left part of the double module and continue up
                        $this->iMatrix[$y][$x - 1]=$aBitStream[$bIdx++];
                        $y -= 1;
                    }
                    elseif( $val_left !== QRMatrixLayout::UNINIT ) {
                        // This should never be possible!
                        throw new QREXception("Internal error: Bit placement");

                        // The left part of the module is occupied so we put the next bit
                        // in the right part of the double module and continue up
                        $this->iMatrix[$y][$x]=$aBitStream[$bIdx++];
                        $y -= 1;
                    }
                    else {
                        if ( $val_left != QRMatrixLayout::UNINIT || $val_right != QRMatrixLayout::UNINIT ) {
                            // throw new QRException("Internal error: Trying to put data in initialized bit.");
                            throw new QRExceptionL(1202);
                        }

                        $this->iMatrix[$y][$x]=$aBitStream[$bIdx++];

                        if ( $bIdx < $n )
                        $this->iMatrix[$y][$x - 1]=$aBitStream[$bIdx++];

                        $y -= 1;
                    }
                }
            }
            else {
                // Direction down
                if ( $y >= $size ) {
                    // We have reached the bottom and we need to change direction
                    // and move one double module to the left
                    $y=$size - 1;
                    $x -= 2;
                    $direction=0;
                }
                else {
                    $val_left=$this->iMatrix[$y][$x - 1];
                    $val_right=$this->iMatrix[$y][$x];

                    if ( $val_left !== QRMatrixLayout::UNINIT && $val_right !== QRMatrixLayout::UNINIT ) {
                        // Both positions are taken and we move down on row
                        $y += 1;
                    }
                    elseif( $val_right !== QRMatrixLayout::UNINIT ) {
                        // The right part of the module is occupied so we put the next bit
                        // in the left part of the double module and continue up
                        $this->iMatrix[$y][$x - 1]='1'; //$aBitStream[$bIdx++];
                        $bIdx++;
                        $y += 1;
                    }
                    elseif( $val_left !== QRMatrixLayout::UNINIT ) {
                        // This should never be possible!
                        throw new QREXception("Internal error: Bit placement");

                        // The right part of the module is occupied so we put the next bit
                        // in the left part of the double module and continue up
                        $this->iMatrix[$y][$x]='1'; //$aBitStream[$bIdx++];
                        $bIdx++;
                        $y += 1;
                    }
                    else {
                        if ( $val_left != QRMatrixLayout::UNINIT || $val_right != QRMatrixLayout::UNINIT ) {
                            //throw new QRException("Internal error: Trying to put data in initialized bit.");
                            throw new QRExceptionL(1202);
                        }

                        $this->iMatrix[$y][$x]=$aBitStream[$bIdx++];

                        if ( $bIdx < $n )
                        $this->iMatrix[$y][$x - 1]=$aBitStream[$bIdx++];

                        $y += 1;
                    }
                }
            }

            ++$cnt;
        }
    }

    function selectApplyMask() {
        $mask=QRMask::getInstance();

        // For special purposes (mainly verification) we can override the automatic
        // selection of a mask pattern and force the mask to to be a selected one by
        // storing that index in iForcedMaskIdx instance variable

        if ( $this->iForcedMaskIdx >= 0 ) {
            $maskidx=$this->iForcedMaskIdx;
            $this->_dbgInfo(1, "  Selected FORCED Mask $maskidx \n");
        }
        else {
            
            $maskidx=-1;

            // Try all 8 masks on the matrix in turn and select the one which
            // gives the lowest penalty score when the mask is evaluted
            $score=array();

            $minscore=100000; // Dummy inital value

            $this->_dbgInfo(2, "Evaluating different layout matrix masks \n");
            $this->_dbgInfo(2, "------------------------------------------\n");

            for( $i = 0; $i < 8; ++$i ) {
                // We must use a temp variable since applyMaskAndEval is call by reference
                // and will destroy the parameter
                $tmp = $this->iMatrix;
                $mask->applyMask($tmp, $i);
                $this->addFormatInfo($tmp, $i);

                $score=$mask->evaluate($tmp, $i);
                $this->_dbgInfo(2, "Evaluated mask nr $i score=$score \n");

                if ( $score < $minscore ) {
                    $minscore=$score;
                    $maskidx=$i;
                }
            }

            $this->_dbgInfo(2, "SELECTED Mask $maskidx, score = $minscore \n");
        }

        $mask->applyMask($this->iMatrix, $maskidx);
        $this->addFormatInfo($this->iMatrix, $maskidx);
        $this->iMaskIdx=$maskidx;
        return $maskidx;
    }

    // Add the bit pattern for the mask information (format information)
    function addFormatInfo(&$aLayout, $aMaskIdx) {
        if ( $aMaskIdx < 0 ) {
            // throw new QRException("Internal error: Mask number for format bits is invalid. (maskidx={$aMaskIdx})");
            throw new QRExceptionL(1203);
        }

        $bits=array();
        $errbits=array ( QRCapacity::ErrL => 1, QRCapacity::ErrM => 0, QRCapacity::ErrQ => 3, QRCapacity::ErrH => 2 );
        $bits=array();

        Utils::Word2Bits($errbits[$this->iErrLevel], $bits, 2);

        $this->_dbgInfo(4, "Adding format information bits for mask index=$aMaskIdx\n");
        $this->_dbgInfo(4, "    Bits for error correction level: " . implode($bits) . "\n");

        $bits2=array();

        Utils::Word2Bits($aMaskIdx, $bits2, 3);
        $this->_dbgInfo(4, "    Bits for mask index: " . implode($bits2) . "\n");

        $bits=array_merge($bits, $bits2);

        // Now use this as index to the pre-computed BCH (15,5) code
        $idx=bindec(implode($bits));
        $format=$this->QRCap->getFormatBits($idx);

        $this->_dbgInfo(4, "    Format bits including BCH (15,5): " . $format . "\n");

        // The 15 bit error corrected Format Information shall then be XORed with the
        // Mask Pattern 101010000010010, in order to ensure that no combination of
        // Error Correction Level and Mask Pattern will result in an all-zero data string.
        $fmask='101010000010010';
        $format=bindec($fmask) ^ bindec($format);

        $bits=array();

        Utils::Word2Bits($format, $bits, 15);

        $this->_dbgInfo(4, "    Format bits after XOR masking: " . implode($bits) . "\n");

        // Now position the format bits in the matrix (see figure 19 in ISO-specs for explanation)

        // Around upper left finder pattern
        $idx=14;

        for( $i = 0; $i <= 8; ++$i ) {
            if ( $i != 6 ) {
                $aLayout[$i][8]=$bits[$idx];
                $aLayout[8][$i]=$bits[(14 - $idx)];
                --$idx;
            }
        }

        // Around upper right and lower left finder pattern
        $idx=14;
        $n=$this->QRCap->getDimension($this->iVersion);

        for( $i = 0; $i <= 7; ++$i ) {
            $aLayout[8][$n - $i - 1] = $bits[$idx];

            if ( $i < 7 )
            $aLayout[$n - $i - 1][8]=$bits[(14 - $idx)];
            else
            $aLayout[$n - $i - 1][8]=QRMatrixLayout::ONE;

            --$idx;
        }
    }

    function addVersionInfo() {
        if ( $this->iVersion >= 7 ) {
            $bitstring=QRCapacity::getInstance()->getVersionBits($this->iVersion);
            $n=$this->QRCap->getDimension($this->iVersion);

            $this->_dbgInfo(4, "Adding version = {$this->iVersion} bits: " . $bitstring . "\n");

            $idx=0;

            for( $c = 0; $c < 6; ++$c ) {
                for( $r = $n - 11; $r < $n - 8; ++$r ) {
                    // Position first set (Bottom-Left)
                    $this->iMatrix[$r][$c] = substr($bitstring, $idx, 1);
                    // Position second set (Upper right)
                    $this->iMatrix[$c][$r]=substr($bitstring, $idx++, 1);
                }
            }
        }
    }

    // Convert the layout to only contain ONE and ZEROs, i.e. we loose
    // the information on what bit is what
    function flatten() {
        $n=$this->QRCap->getDimension($this->iVersion);

        for( $i = 0; $i < $n; ++$i ) {
            for( $j = 0; $j < $n; ++$j ) {
                $v = $this->iMatrix[$i][$j];

                if ( $v === QRMatrixLayout::UNINIT ) {
                    //throw new QRException("Internal error: Found an uninitilized bit [$v] at ($i,$j) when finalizing matrix\n");
                    throw new QRExceptionL(1204);
                }

                if ( $v != QRMatrixLayout::ZERO && $v != QRMatrixLayout::QUIET ) {
                    $this->iMatrix[$i][$j] = QRMatrixLayout::ONE;
                }
                else {
                    $this->iMatrix[$i][$j] = QRMatrixLayout::ZERO;
                }
            }
        }
    }
}
?>
