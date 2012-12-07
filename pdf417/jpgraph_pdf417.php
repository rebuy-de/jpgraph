<?php
//=======================================================================
// File:        JPGRAPH_PDF417.PHP
// Description: Module to create PDF417 2-Dimensional Barcodes
// Created:     2004-02-25
// Ver:         $Id: jpgraph_pdf417.php 1674 2009-07-22 19:42:23Z ljp $
//
// License: This code is released under JpGraph Professional License
//
// Copyright (C) Asial Corporation. All rights reserved.
//========================================================================
require_once('jpgraph/jpgraph.php');
require_once('jpgraph/jpgraph_canvas.php');

// Latch and shift characters
define('LATCH_TO_ALPHA_FROM_PUNCT',29); // Only latch in punct mode
define('LATCH_TO_ALPHA',28);  // Only in mixed mode
define('LATCH_TO_LOWER',27);  // In alpha and mixed
define('LATCH_TO_MIXED',28);  // In alpha and lower
define('LATCH_TO_PUNCT',25);  // Only in mixed
define('SHIFT_TO_PUNCT',29);  // In alpha, lower and mixed
define('SHIFT_TO_ALPHA',27);  // Only in lower

// Latch symbol values
define('LATCH_TC',900);
define('LATCH_NC',902);
define('LATCH_BC_EVEN6',924);
define('LATCH_BC_ODD6',901);
define('PAD_SYMBOL',900);

// Limit for when there is enough digits to switch to numeric encoding
define('NC_MINDIGITS',13);

require_once "pdf417_clusters.inc.php";
require_once "pdf417_compressors.inc.php";
require_once "pdf417_backends.inc.php";
require_once "pdf417_error.inc.php";

// Check that bcmod() is available
if( ! function_exists('bcmod') ) {
    JpGraphError::RaiseL(26000);
    // 'PDF417: The PDF417 module requires that the PHP installation must support the
    // function bcmod(). This is normally enabled at compile time. See documentation
    // for more information.'
}

// Encode user data in PDF417
class PDF417Barcode {
    public $iOrgData=''; // To match the signature of the collector
    private $iSpec = NULL;
    private $iSymbols=array();
    private $iCnt=0,$iTotCnt=0;
    private $iRow=0;
    private $iNumRows,$iNumCols,$iErrLevel;
    private $iCompLatch = array(LATCH_TC, LATCH_NC, LATCH_BC_EVEN6, LATCH_BC_ODD6);
    private $iCompressors = NULL, $iRSCode = NULL;
    private $iTruncated=false;

    function __construct($aNumCols=10,$aErrLevel=2) {
        if( $aNumCols < 1 || $aNumCols > 30 ) {
            JpGraphError::RaiseL(26001);// "Columns must be >= 1 and <= 30\n" );
        }
        if( $aErrLevel < 0 || $aErrLevel > 8 ) {
            JpGraphError::RaiseL(26002);// "Error level must be >= 0 and <= 8\n" );
        }
        $this->iNumCols = $aNumCols;
        $this->iErrLevel = $aErrLevel;
        $this->iPDF417Patterns = new PDF417Patterns();
        $this->iRSCode = new ReedSolomon();
    }

    function SetColumns($aCols) {
        $this->iNumCols = $aNumCols;
    }

    function SetTruncated($aTrunc=true) {
        $this->iTruncated = $aTrunc;
    }

    function SetErrLevel($aErrLevel) {
        $this->iErrLevel = $aErrLevel;
    }

    function PrepData($aData) {
        // Find out how we should best encode data (by Text, Numeric or by Byte compression
    $row=0;
    $i = 0;
    $len  = strlen($aData);
    $data = array();
    while( $i < $len ) {
        $c=substr($aData,$i,1);
        // Scheck if we should latch to NC
        if( ctype_digit($c) ) {
            // Get the longest sequenze of digits
            $j=0;
            while( ctype_digit(substr($aData,$i+$j,1)) )
            ++$j;
            if( $j >= NC_MINDIGITS ){
                if( !empty($data[$row]) ) ++$row;
                $data[$row++]=array(USE_NC,substr($aData,$i,$j));
            }
            else {
                if( empty($data[$row])  )
                $data[$row] = array(USE_TC,substr($aData,$i,$j));
                else
                $data[$row][1] .= substr($aData,$i,$j);
            }
            $i += $j;
        }
        elseif( (ord($c) >=32 && ord($c) <= 126 ) || $c == "\n" || $c == "\t" || $c == "\r" ) {
            if( empty($data[$row])  )
            $data[$row] = array(USE_TC,$c);
            else
            $data[$row][1] .= $c;
            ++$i;
        }
        else {
            // Latch to Binary mode
            // Find the longest binary string of data
            // If there is only one binary data followed by at least 1 non-binary data
            // issue a shift to binary for just one byte
            $remlen = strlen(substr($aData,$i+1));
            $cc1 = $cc2 = $cc3 = '';
            if( $remlen > 0 ) {
                $cc1 = substr($aData,$i+1,1);
            }
            if( ((ord($cc1) >=32 && ord($cc1) <= 126 ) || $cc1 == "" || $cc1 == "\n" || $cc1 == "\t" || $cc1 == "\r" ) ) {
                if( empty($data[$row])  )
                $data[$row] = array(USE_TC,chr(SHIFT_TO_BC_MARKER).$c);
                else
                $data[$row][1] .= chr(SHIFT_TO_BC_MARKER).$c;
                ++$i;
            }
            else {
                $j=0;
                // Find the longest prefix (substr) which has purely binary data
                $cc = substr($aData,$i,1);
                $prelen = strlen(substr($aData,$i));
                while( $j < $prelen &&
                !( (ord($cc) >=32 && ord($cc) <= 126 ) || $cc == "\n" || $cc == "\t" || $cc == "\r") ) {
                    ++$j;
                    $cc = substr($aData,$i+$j,1);
                }
                // If there are just two binary data we treat this as a special case
                // and use two shifts. Otherwise do a full mode switch
                if( $j <= 2 ) {
                    $cc1 = substr($aData,$i,1);
                    $cc2 = substr($aData,$i+1,1);
                    $t  = chr(SHIFT_TO_BC_MARKER).$cc1.chr(SHIFT_TO_BC_MARKER).$cc2;
                    if( empty($data[$row])  )
                    $data[$row] = array(USE_TC,$t);
                    else
                    $data[$row][1] .= $t;
                }
                else {
                    $latch = $j % 6 == 0 ? USE_BC_E6 : USE_BC_O6;
                    if( ! empty($data[$row])  ) {
                        ++$row;
                    }
                    $data[$row++] = array($latch,substr($aData,$i,$j));
                }
                $i += $j;
            }
        }
    }
    return $data;
    }

    function StartRow() {
        $this->iCnt = 0;
        $this->iSymbols[$this->iRow][$this->iCnt++] =
        array('START',NULL,NULL,$this->iPDF417Patterns->GetStartPattern());
        $this->iSymbols[$this->iRow][$this->iCnt++] = array('LEFT',NULL,NULL,'?');
        if( $this->iRow == 0 ) {
            // Reserve one position for data count which we don't yet know
            $this->iSymbols[$this->iRow][$this->iCnt++] = array('CNT',NULL,NULL,'?');
            $this->iTotCnt++; // We count the counter as one data symbol
        }
    }

    function EndRow() {
        if( $this->iTruncated ) {
            $this->iSymbols[$this->iRow][$this->iCnt++] = array('TSTOP',NULL,NULL,'1');
        } else {
            $this->iSymbols[$this->iRow][$this->iCnt++] = array('RIGHT',NULL,NULL,'?');
            $this->iSymbols[$this->iRow][$this->iCnt++] = array('STOP',NULL,NULL,$this->iPDF417Patterns->GetStopPattern());
        }
        $this->iRow++;
    }

    function AddSymbol($aVal,$aVal1='',$aVal2='') {
        if( empty($this->iSymbols[$this->iRow]) ) {
            $this->StartRow();
        }
        // Special case. Since we always start by default in TC
        // we do not issue that latch
        if( $this->iRow == 0 && $this->iCnt == 3 && $aVal == LATCH_TC )
        return;
        $this->iSymbols[$this->iRow][$this->iCnt++] =
        array($aVal,$aVal1,$aVal2,$this->iPDF417Patterns->GetPattern($this->iRow,$aVal));
        if( $this->iCnt-2 >= $this->iNumCols ) {
            $this->EndRow();
        }
        $this->iTotCnt++;
    }

    function AddPadEndRow() {
        $padPattern = $this->iPDF417Patterns->GetPattern($this->iRow,PAD_SYMBOL);
        while( $this->iCnt-2 < $this->iNumCols ) {
            $this->iSymbols[$this->iRow][$this->iCnt++] = array('PAD',NULL,NULL,$padPattern);
            $this->iTotCnt++;
        }
        $this->EndRow();
    }

    function AddPadSymbols($aN) {
        for( $i=0; $i < $aN; ++$i )
        $this->AddSymbol(PAD_SYMBOL,'PAD');
    }

    function Enc($aDataSpec) {

        $this->iSymbols=array();
        $this->iCnt=-1;
        $this->iTotCnt=0;
        $this->iRow=0;

        if( $this->iCompressors === NULL ) {
            $bc = new ByteCompressor($this);
            $this->iCompressors = array(new TextCompressor($this),
            new NumericCompressor($this), $bc, $bc);
        }
        // First determine if the user has specified a compaction schema or
        // if we should try to determine a adequate one
        if( is_string($aDataSpec) ) {
            $aDataSpec = $this->PrepData($aDataSpec);
            $nrows = count($aDataSpec);
        }
        elseif( is_array($aDataSpec) )
        $nrows = count($aDataSpec);
        else {
            JpGraphError::RaiseL(26003);//'Illegal format for input data to encode with PDF417');
        }

        // Now go through all the input data rows and encode them one by one
        // One input row might become several code rows
        for($i=0; $i < $nrows; ++$i ) {
            $this->AddSymbol($this->iCompLatch[$aDataSpec[$i][0]]);
            $this->iCompressors[$aDataSpec[$i][0]]->Encode($aDataSpec[$i][1]);
        }

        // Now finish up and add the error correcting Reed-Solomon codewords

        // Find out how many codewords the specified error level requries
        $numcw = $this->iRSCode->GetNumCodewords($this->iErrLevel);

        // Check that data symbols + number of codewords doesn't exceed
        // the maximum 925
        // Find out how many PAD symbols we need to add in order
        // to make an even number of rows including the error codewords
        $numrows = max(3,ceil(($this->iTotCnt+$numcw)/$this->iNumCols));
        $numpads = $numrows*$this->iNumCols-$numcw-$this->iTotCnt;

        if( $numcw + $this->iTotCnt > 925 || $numrows > 90) {
            JpGraphError::RaiseL(26004,$this->iErrLevel,$this->iNumCols);
            //"Can't encode given data with error level #$this->iErrLevel and #$this->iNumCols columns since it results in too many symbols or more than 90 rows.");

        }

        $this->AddPadSymbols($numpads);

        // Now when we now the total number of symbols back-patch the counter
        $this->iSymbols[0][2][0] = $this->iTotCnt;
        $this->iSymbols[0][2][1] = 'CNT';
        $this->iSymbols[0][2][3] = $this->iPDF417Patterns->GetPattern(0,$this->iTotCnt);

        // Collect the data symbols (excluding start,stop,left and right markers)
        // and send them to the Reed-Solomon encoder to get the codewords
        $data=array();$dcnt=0;
        for( $i=0; $i < $this->iRow; ++$i ) {
            for($j=2; $j < $this->iNumCols+2; ++$j) {
                $data[$dcnt++] = $this->iSymbols[$i][$j][0];
            }
        }

        // Check if we have a partial row at the end. The only time this will not
        // happen is if the column size equal the error code size since we will then
        // have increased the row counter when we added the last real data above.
        if( !empty($this->iSymbols[$this->iRow]) ) {
            $ncols = count($this->iSymbols[$this->iRow]);
            for($j=2; $j < $ncols; ++$j) {
                $data[$dcnt++] = $this->iSymbols[$this->iRow][$j][0];
            }
        }

        $cw = $this->iRSCode->GetCodewords($data,$this->iErrLevel);

        // Add all codewords to the symbol array
        for( $i=0; $i < $numcw; ++$i ) {
            $this->AddSymbol($cw[$i],"cw$i");
        }

        // Now finally setup all the left and right indicators now when we
        // know the final number of rows
        for($i=0; $i < $this->iRow; ++$i) {
            list( $left, $right ) =
            $this->iPDF417Patterns->GetRowInd($i,$numrows,$this->iNumCols,$this->iErrLevel);
            $this->iSymbols[$i][1] =
            array('LEFT',NULL,$left,$this->iPDF417Patterns->GetPattern($i,$left));
            if( !$this->iTruncated ) {
                $this->iSymbols[$i][$this->iNumCols+2] =
                array('RIGHT',NULL,$right,$this->iPDF417Patterns->GetPattern($i,$right));
            }
        }

        // Setup the print specification
        $spec = new BarcodePrintSpecPDF417();
        $spec->iBar = $this->iSymbols;
        $spec->iInfo = "ErrorLevel=".$this->iErrLevel.":NumRows=".$this->iNumRows.":NumCols=".$this->iNumCols;
        $spec->iEncoding='PDF417';
        $spec->iData=$data;
        $spec->iStrokeDataBelow=true;
        return $spec;
    }
}

// EOF

?>
