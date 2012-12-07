<?php
/*=======================================================================
 // File:          QRMASK.INC.PHP
 // Description:   Part of QR encodation. Handles the final masking of the
 //                matrix in order to achieve an even distribution of dark
 //                and white areas.
 // Created:       2008-07-29
 // Ver:           $Id: qrmask.inc.php 1106 2009-02-22 20:16:35Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

/*
 //--------------------------------------------------------------------------------------
 // Class QRMask = QR Code mask handling
 // Singleton
 // This class is responsible for calculating the penalty score for the final
 // bit placed matrix when we apply the different final XOR masks to achieve
 // as even split between dark and light areas as possible.
 // This is described in the standard in section 8.8
 //
 // For reliable QR Code reading, it is preferable for dark and light modules to be arranged
 // in a well-balanced manner in the symbol. The bit pattern 1011101 particularly found in the
 // Position Detection Pattern should be avoided in other areas of the symbol as much as possible.
 // To meet the above conditions, masking should be applied.
 //
 //  1. Masking is not applied to function patterns.
 //  2. Convert the given module pattern in the encoding region (excluding the Format Information and the Version
 //     Information) with multiple matrix patterns successively through the XOR operation. For the XOR operation, lay
 //     the module pattern over each of the masking matrix patterns in turn and reverse the modules (from light to dark
 //     or vice versa) which correspond to dark modules of the masking pattern.
 //  3. Then evaluate all the resulting converted patterns by charging penalties for undesirable features on each
 //     conversion result.
 //  4. Select the pattern with the lowest penalty points score.
 //
 // This class is used from the QRLayout class which owns the actual layout matrix and this class implements
 // the actual search algorithm.
 //-----------------------------------------------------------------------------------------
 */
class QRMask {
    private $iDbgInfo = '', $iDbgLevel = 0 ;
    private $iModuleCheckBlack = true;

    private static $iInstance;

    private function __construct() {
        // Empty
    }

    function SetDebugLevel($aLevel) {
        $this->iDbgLevel = $aLevel;
    }

    public static function getInstance() {
        if( !isset(self::$iInstance) ) {
            $c = __CLASS__;
            self::$iInstance = new $c();
        }
        return self::$iInstance;
    }

    function __toString() {
        $tmp = $this->iDbgInfo;
        $this->iDbgInfo = '';
        return $tmp;
    }

    function _dbgInfo($aLevel, $aString) {
        if ($aLevel <= $this->iDbgLevel) {
            $this->iDbgInfo .= $aString;
        }
    }

    /*
     After performing the masking operation with each Mask Pattern in turn, the results shall be evaluated by scoring
     penalty points for each occurrence of the following features. The higher the number of points, the less acceptable
     the result. In Table 24 below, the variables N1 to N4 represent weighted penalty scores for the undesirable features
     (N1=3, N2=3, N3=40, N4=10), i is the amount by which the number of adjacent modules of the same color exceeds 5
     and k is the rating of the deviation of the proportion of dark modules in the symbol from 50% in steps of 5%.
     Although the masking operation is only performed on the encoding region of the symbol excluding the Format
     Information, the area to be evaluated is the complete symbol.

     Feature                                      Evaluation condition                  Points
     ===============================================================================================================
     Adjacent modules in row/column in same color No. of modules = (5 + i)              N1 + i
     Block of modules in same color               Block size = m * n                    N2*(m - 1)*(n - 1)
     1:1:3:1:1 ratio (dark:light:dark:light:dark)                                       N3
     pattern in row/column
     Proportion of dark modules in entire symbol  50 +/- (5*k)% to 50 +/- (5*(k + 1))%  N4*k

     */
    // All methods prefixed by "_" is helper methods to the real evaluation algorithm

    // Print a textual representation of the layout matrix
    function _printmatrix(&$aLayout) {
        if( $this->iDbgLevel < 3 ) return;

        $n = count($aLayout);
        $this->_dbgInfo(3, "n=$n\n");

        $this->iDbgInfo .= ' ';
        for($j = 0; $j < $n; ++$j) {
            $this->_dbgInfo(3, $j%10 );
        }
        $this->_dbgInfo(2, "\n");
        for($i = 0; $i < $n; ++$i) {
            $this->_dbgInfo(3, $i%10 );
            for($j = 0; $j < $n; ++$j) {
                $this->_dbgInfo(3, $this->_isColor($aLayout[$i][$j]) ? 'X' : '-');
            }
            $this->_dbgInfo(3, "\n");
        }
        $this->_dbgInfo(3, "\n");
    }

    // We use this method to find out if a module is "dark" or "light". We need
    // this since the internal representation is much richer in order to distinguish
    // between all different function patterns and for pattern evaluation we are only
    // interested if a module is considered "dark" or "light"
    function _getModule(&$aLayout, $aRow, $aCol, $aFlip = false) {
        if ($aFlip) {
            return $aLayout[$aCol][$aRow];
        } else {
            return $aLayout[$aRow][$aCol];
        }
    }

    // We use this method to find out if a module is "dark" or "light". We need
    // this since the internal representation is much richer in order to distinguish
    // between all different function patterns and for pattern evaluation we are only
    // interested if a module is considered "dark" or "light"
    function _isColor($aVal) {
        if ( $this->iModuleCheckBlack ) {
            return $aVal != QRMatrixLayout::ZERO && $aVal != QRMatrixLayout::QUIET ;
        } else {
            return $aVal == QRMatrixLayout::ZERO || $aVal == QRMatrixLayout::QUIET;
        }
    }

    // Return TRUE if the module at the specified row and column is set or not
    // (i.e. if it is "dark")
    function _isRCSet(&$aLayout, $aRow, $aCol, $aFlip = false) {
         
        // Since this routine is called so many times we unwrap the following call
        // with its inline version to avoid two function calls
        // return $this->_isColor($this->_getModule($aLayout, $aRow, $aCol, $aFlip));

        if ($aFlip) {
            $val = $aLayout[$aCol][$aRow];
        } else {
            $val = $aLayout[$aRow][$aCol];
        }

        if ( $this->iModuleCheckBlack ) {
            return $val != QRMatrixLayout::ZERO && $val != QRMatrixLayout::QUIET ;
        } else {
            return $val == QRMatrixLayout::ZERO || $val == QRMatrixLayout::QUIET ;
        }
    }

    // Evaluate the score for lines of the same color
    function _evalLines(&$aLayout, $n, $aFlip = false) {
        $this->_dbgInfo(2, "\nLINE SEARCH\n");
        // ----------------------------------------------------------------------------
        // Find out how many hor/vert sequences with more than 5 dark modules
        $hy = 0;
        $inseq = false;
        $cnt = 0;
        $vx = $vy = 0;
        $score = 0;
        $np = 5 ; // The length of the pattern we are looking for

        for($x = 0; $x < $n; ++$x) {
            for($y = 0; $y < $n; ++$y) {
                if ($this->_isRCSet($aLayout, $y, $x, $aFlip)) {
                    ++$cnt;
                    if (!$inseq) {
                        $vx = $x;
                        $vy = $y;
                    }
                    $inseq = true;
                } else {
                    if ($inseq) {
                        $inseq = false;
                        if ($cnt >= $np) {
                            $score += (3 + $cnt-$np);
                            if ($aFlip) {
                                $this->_dbgInfo(3, " Horizontal stretch at ($vx,$vy) len=$cnt (Acc score=$score)\n");
                            } else {
                                $this->_dbgInfo(3, " Vertical stretch at ($vy,$vx) len=$cnt (Acc score=$score)\n");
                            }

                        }
                        $cnt = 0;
                    }
                }
            } // for

            // Now we need to reset the line search when we reach the end of the
            // matrix and wrap around to next col/row
            if ($inseq) {
                $inseq = false;
                if ($cnt >= $np) {
                    $score += (3 + $cnt-$np);

                    if ($aFlip) {
                        $this->_dbgInfo(3, " Horizontal stretch at ($vx,$vy) len=$cnt (Acc score=$score)\n");
                    } else {
                        $this->_dbgInfo(3, " Vertical stretch at ($vy,$vx) len=$cnt (Acc score=$score)\n");
                    }

                }
                $cnt = 0;
            }
        } // for

        return $score;
    }

    // 11311 Finder Pattern Search
    function _eval11311Pattern(&$aLayout, $n, $aFlip = false) {
        // ----------------------------------------------------------------------------
        // Find 1:1:3:1:1 patterns (dark:light:dark:light:dark)

        // We will search for the pattern in an augmented version of the original matrix
        // since the quiet period outside the matrix is not included. We need to take these
        // areas into account in order to  find  patterns on the edge. By extending the matrix
        // the logic gets a *lot* simpler than adding a lot of special cases for when the
        // pattern is found on the edges of the matrix.
        $augLayout = array();
        for($i=0; $i < $n+2; ++$i) {
            $augLayout[$i] = array_fill(0,$n+2,QRMatrixLayout::QUIET);
            if( $i > 0 && $i <= $n )
            array_splice($augLayout[$i],1,$n,$aLayout[$i-1]);
        }
        $n += 2;

        $pattern = array(false,true, false, true, true, true, false, true, false);
        $score = 0;            // Each time we found the pattern we charge 40 points penalty
        $np = count($pattern); // number of pattern bits

        $this->_dbgInfo(3, "\nPATTERN SEARCH (1:1:3:1:1)\n");
        $vx = $vy = 0;

        for($x = 0; $x < $n; ++$x) {

            // Reset when we reach the edge
            $y = 0;
            $idx = 0;
            $inseq = false;

            while ($y < $n) {
                if (! ($this->_isRCSet($augLayout, $y, $x, $aFlip) ^ $pattern[$idx])) {
                    // Pattern is matching
                    if ($inseq) {
                        if ($idx < $np-1) {
                            ++$idx;
                        } else {
                            $idx = 0;
                            $inseq = false;
                            $score += 40;
                            if ($aFlip) {
                                $this->_dbgInfo(3, "  Horizontal Pattern at ($vx,$vy) (Acc score=$score)\n");
                            } else {
                                $this->_dbgInfo(3, "  Vertical Pattern at ($vy,$vx) (Acc score=$score)\n");
                            }
                        }
                    } else {
                        $inseq = true;
                        $vx = $x;
                        $vy = $y;
                        ++$idx;
                    }
                    ++$y;
                } else {
                    if ($inseq) {
                        $inseq = false;
                        $idx = 0;
                        // Reset to the position after we found the tentative pattern
                        $y = $vy + 1;
                    } else {
                        ++$y;
                    }
                }
                if( !$inseq && $y > $n-$np ) {
                    // It's no point searching for a pattern that starts more than $n-$np steps down
                    // since the full pattern would never fit anyway
                    $y = $n;
                }
            } /* while */

        }

        return $score;
    }

    // Evaluate proportion of black modules
    function _evalProp(&$aLayout, $n) {
        // ----------------------------------------------------------------------------
        // Find proportion of black vs white modules
        $this->_dbgInfo(3, "\nEvaluating Proportion as ");

        $cnt = 0; // Color count
        for($y = 0; $y < $n; ++$y) {
            for($x = 0; $x < $n; ++$x) {
                $cnt += $this->_isColor($aLayout[$y][$x]) ? 1 : 0;
            }
        }
        $p = round($cnt * 100 / ($n * $n));
        $score = 10*floor(abs($p-50) / 5);
        $this->_dbgInfo(3, "  p=$p% color => score=$score\n");

        return $score;
    }

    // Search for blocks of size m*n
    // The method used here is a gready one pass where we start the search
    // for potential patterns row by row starting from the top. Since it is only
    // a single pass algorithm it is not guranteed to find the largest possible
    // penalty score for a certain matrix.
    function _evalBlock($aLayout, $n, $aFlip = false) {
        $this->_dbgInfo(3, "\nBLOCK SEARCH\n");
        $score = 0 ;
        for($y = 0; $y < $n; ++$y) {
            for($x = 0; $x < $n; $x += $width) {
                $width = 1;
                if ($this->_isRCSet($aLayout, $y, $x, $aFlip)) {
                    // Find out how long (horizontally) this black stretch is
                    $xx = $x + 1;
                    $yy = $y;
                    while ($xx < $n && $this->_isRCSet($aLayout, $yy, $xx, $aFlip)) {
                        ++$width;
                        ++$xx;
                    }

                    if ($width > 1) {
                        $this->_dbgInfo(4, "  Found block stretch at ($y,$x) of len=$width\n");
                        // The length of the top stretch is now $k modules
                        // Now loop through all the rows below and find the maximum number
                        // of rows that can be part of a block.
                        $height = 1; // We have already found one row
                        ++$yy; // Start looking at next row
                        $xx = $x; // ... in the leftmost column
                        $currwidth=1000; // Dummy value
                        while ($yy < $n && $currwidth>1 && $this->_isRCSet($aLayout, $yy, $xx, $aFlip)) {
                            ++$height;
                            $currwidth = 0;
                            while ($xx < $n && $this->_isRCSet($aLayout, $yy, $xx, $aFlip)) {
                                ++$currwidth;
                                ++$xx;
                            }
                            $this->_dbgInfo(4, "     Found block stretch at ($yy,$x) of len=$currwidth, height=$height\n");
                            // The following condition will prioritize finding wide blocks
                            // in favor of tall blocks. If we have found some long wide blocks
                            // already width at least 2 in height and the current line is shorter
                            // than the previous already identified block we end
                            // the search by setting the row coordinate to maximum so it will
                            // stop in the next test in the while loop
                            if ($currwidth < $width && $height > 2) {
                                $yy = $n; // Set yy to max to quiet the loop
                                --$height;
                            } else {
                                if( $currwidth == 1 ) {
                                    --$height;
                                    $yy = $n;
                                }
                                else {
                                    $width = min($width, $currwidth);
                                    ++$yy;
                                    $xx = $x;
                                }
                            }
                        }

                        if ($height > 1) {
                            // We have now found a rectangle height x $width sized
                            $score += 3*($height-1) * ($width-1);
                            $this->_dbgInfo(3, "  Found block at ($y,$x) of size ($height x $width) (Acc score:$score)\n");
                            // Flip the bits that are part of this block so that they are not
                            // counted again when we continue the search
                            $val = $this->iModuleCheckBlack ? QRMatrixLayout::ZERO : QRMatrixLayout::ONE;
                            for($ii = 0; $ii < $height; ++$ii) {
                                for($jj = 0; $jj < $width; ++$jj) {
                                    $aLayout[$ii + $y][$jj + $x] = $val;
                                }
                            }
                        } else {
                            // Reset the width to 1 so that we only advance one step
                            // in the first row of this tenative block to try the next
                            // column instead. For example if width is >> 1 and the next line
                            // has a different color in the first col on the next row.
                            $width = 1;
                        }
                    } // if width > 1

                }
            }
        }
        return $score;
    }

    // Evaluate the given layout matrix for the different search patternss
    function evaluate(&$aLayout,$maskIdx=-1) {
        // Calculate the weighting score for this particular matrix layout.
        // This is done when we try the different XOR masks in the final stage
        // of symbol construction in order to decide which mask gives the best
        // balance of white anc black areas
        $n = count($aLayout);
        $score = 0;

        $this->_dbgInfo(3, "\n Evaluating Mask index = $maskIdx\n");
        $this->iModuleCheckBlack = true;
        $this->_printmatrix($aLayout);

        $this->_dbgInfo(3, "\n-- Evaluating BLACK MODULES --\n");

        $score += $this->_evalLines($aLayout, $n, false);
        $score += $this->_evalLines($aLayout, $n, true);
        $score += $this->_eval11311Pattern($aLayout, $n, false);
        $score += $this->_eval11311Pattern($aLayout, $n, true);
        $score += $this->_evalProp($aLayout, $n);
        $score += $this->_evalBlock($aLayout, $n, false);

        $this->_dbgInfo(3, "\n-- Evaluating WHITE MODULES --\n");

        $this->iModuleCheckBlack = false;
        $score += $this->_evalLines($aLayout, $n, false);
        $score += $this->_evalLines($aLayout, $n, true);
        $score += $this->_evalBlock($aLayout, $n, false);

        return $score;
    }

    //---------------------------------------------------------------------------------------------
    // Implementation of Table 23 in ISO specs, i=row, j=column
    //
    //    Mask Pattern        Reference Condition
    //    ==========================================
    //    000                 (i+j) mod 2 = 0
    //    001                 i mod 2 = 0
    //    010                 j mod 3 = 0
    //    011                 (i+j) mod 3=0
    //    100                 ((i div 2) + (j div 3)) mod 2 = 0
    //    101                 (i*j) mod 2 + (i*j) mod 3 = 0
    //    110                 ((i*j) mod 2 + (i*j) mod 3) mod 2 = 0
    //    111                 ((i*j) mod 3 + (i+j) mod 2) mod 2 = 0
    //
    //---------------------------------------------------------------------------------------------
    function _mask($aMask, $i, $j, $aVal) {
        switch ($aMask) {
            case 0:
                $mask = ($i + $j) % 2 == 0 ;
                break;

            case 1:
                $mask = ($i % 2) == 0 ;
                break;

            case 2:
                $mask = ($j % 3) == 0 ;
                break;

            case 3:
                $mask = (($i + $j) % 3) == 0 ;
                break;

            case 4:
                $mask = (floor($i / 2) + floor($j / 3)) % 2 == 0 ;
                break;

            case 5:
                $mask = ($i * $j) % 2 + ($i * $j) % 3 == 0 ;
                break;

            case 6:
                $mask = (($i * $j) % 2 + ($i * $j) % 3) % 2 == 0 ;
                break;

            case 7:
                $mask = (($i * $j) % 3 + ($i + $j) % 2) % 2 == 0 ;
                break;

            default:
                //throw new QRException("Internal error: Illegal mask pattern selected");
                throw new QRExceptionL(1100);
                break;
        }
        if ($mask) {
            if( $aVal == QRMatrixLayout::ONE || $aVal == QRMatrixLayout::ZERO ) {
                return $aVal == QRMatrixLayout::ONE ? QRMatrixLayout::ZERO : QRMatrixLayout::ONE;
            }
            else {
                //throw new QRException('Internal error: Trying to apply masking to functional pattern.');
                throw new QRExceptionL(1101);
            }
        } else
        return $aVal;
    }

    // Apply mask with index specified by $aMask to the layout matrix referenced by
    // $alayout
    function applyMask(&$aLayout, $aMask) {
        $n = count($aLayout);
        for($i = 0; $i < $n; ++$i) {
            for($j = 0; $j < $n; ++$j) {
                // The mask shall only be applied to data part, i.e. only ZERO ot ONE
                // modules and not to , for example, align patterns
                $v = $aLayout[$i][$j];
                if ($v == QRMatrixLayout::ZERO || $v == QRMatrixLayout::ONE) {
                    $aLayout[$i][$j] = $this->_mask($aMask, $i, $j, $v) ;
                     
                } elseif ($aLayout[$i][$j] == QRMatrixLayout::UNINIT) {
                    //throw new QRException("Internal error: applyMaskAndEval(): Found uninitialized module in matrix when applying mask pattern.");
                    throw new QRExceptionL(1102);
                }
            }
        }
    }


}

?>
