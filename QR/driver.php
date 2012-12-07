<?php
/*=======================================================================
// File:          DRIVER.PHP
// Description:   Driver for QR Prototype
// Created:       2008-07-04
// Ver:           $Id: driver.php 1068 2008-09-06 21:45:35Z ljp $
//
// Copyright (c) 2008 Asial Corporation. All rights reserved.
//========================================================================
*/


require_once('qrencoder.inc.php');
/*
try {
throw new QRExceptionL(1202,'first arg');
} catch (QRExceptionL $e) {
    echo 'Exception:'.$e->GetMessage()."\n\n";
} catch (QRException $e) {
    echo 'QR Exception:'.$e->GetMessage()."\n\n";
}
die();
*/

// Unit test driver for QR barcode creation
class Driver {
    private $datafile='',$data='';
    
    function __construct($aStartMsg = null) {
        // Main entry point for prototype driver
        //date_default_timezone_set("Europe/Stockholm");
    }
    
    function isCmdLine() {
        $s=php_sapi_name();
        return substr($s, 0, 3) == 'cli'; 
    }

    function fmtInfo($aS) {

        if ( !$this->isCmdLine() ) {
            return '<pre>' . $aS . '<pre>';
        }
        else return $aS;
    }

    function Tst13Pattern() {

        $mask = QRMask::getInstance();
        $mask->_setDbgLevel(2);

        $n=11;
        $m=array();
        for($i=0; $i < $n; ++$i) {
            $m[$i] = array_fill(0,$n,QRMatrixLayout::ZERO);
        }

        $pattern = array(QRMatrixLayout::ONE,
        QRMatrixLayout::ZERO,
        QRMatrixLayout::ONE,
        QRMatrixLayout::ONE,
        QRMatrixLayout::ONE,
        QRMatrixLayout::ZERO,
        QRMatrixLayout::ZERO,
        QRMatrixLayout::ONE);

        for($j=0; $j < count($pattern); ++$j) {
            $m[$j+2][1] = $pattern[$j];
        }

        $val = $mask->_eval11311Pattern($m, $n, false)  ;

        echo $this->fmtInfo("Result: \n".$mask."\n");
    }

    // Create the precomputed table for reminders for the BCH(15,5) code
    function CreateBCH155Table() {
        
        // Generator for BCH (15,5)
        $G=array
            ( 1, 0, 1,  0,  0,  1,  1,  0, 1, 1, 1 );

        // Generator for BCH (18,6)
        //$G = array(1,1,1,1,1,0,0,1,0,0,1,0,1);
        
        $p=array();

        echo sprintf("%9s%13s%18s%28s\n","Format bits","Error bits","Combined","After XOR masking");
        echo str_repeat('=',9+13+18+28+2);
        echo "\n";
        for( $val = 0; $val <= 31; ++$val ) {
            Utils::Word2Bits($val, $p, 5);
            $nG=count($G);

            // Multiply
            $pad=array_fill(0, $nG - 1, 0);
            $p=array_merge($p, $pad);
            $tmp=$p;

            $n=count($tmp);
            $k=0;

            // First non zero bit
            while( $k < $n && $tmp[$k] == 0 ) {
                ++$k;
            }

            if( $k < $n ) {
            // Now do long division
            do {
                for( $i = 0; $i < $nG; ++$i ) {
                    $tmp[$i + $k] = $tmp[$i + $k] ^ $G[$i];
                }

                while( $k < $n && $tmp[$k] == 0 ) {
                    ++$k;
                }
            } while ( $k + $nG - 1 < $n );
        }
        
            $combined = sprintf("%05s",decbin($val)) . substr(implode($tmp),5);
            
            $fmask = '101010000010010';
            $final = bindec($fmask) ^ bindec($combined);
        
            
            // $tmp now has the remainder bits. Output it in a table
            echo sprintf("%05s", decbin($val)) . "          " . 
                         /*implode($tmp) . " " . */
                         $combined . "         " . 
                         sprintf("%015s",decbin($final)) . 
                         sprintf(" (%04X)",$final) .
                         "\n" ;
        }
    }
    
    function RunTextBackend() {          
        $e=new QREncoder($this->version, $this->errlevel);
        
        $b=BarcodeBackendFactory::Create($e, BACKEND_ASCII);
        $b->SetModuleWidth(1);
        if( $this->datafile != '')
            $b->StrokeFromFile($this->datafile,'',$this->debuglevel);
        else
            $b->Stroke($this->data,'',$this->debuglevel);
    }

    function RunGraphicBackend() {          
        $e=new QREncoder($this->version, $this->errlevel);
        
        $b=BarcodeBackendFactory::Create($e, BACKEND_IMAGE);
        $b->SetModuleWidth(6);
        if( $this->datafile != '')
            $b->StrokeFromFile($this->datafile,'',$this->debuglevel,'qrlog.txt');
        else
            $b->Stroke($this->data,'',$this->debuglevel,'qrlog.txt');
    }

    function Run($aForceText=false) {
        $this->version = -1; ;
        $this->errlevel = -1;// QRCapacity::ErrM;
        $this->debuglevel = 2;

        /*
        $this->data = array(
            array(Encoder::MODE_NUMERIC, '01234567')
            );
            
        $this->data=array(
            array(QREncoder::MODE_NUMERIC, '01234567'),
           /* array(QREncoder::MODE_ALPHANUM,'AC-42'),
            array(QREncoder::MODE_BYTE,'AC-42') 
        );
        */
        
        // Data to be encoded
        /*
        $this->data = array(
            array(QREncoder::MODE_ALPHANUM,'01234567'),
            array(QREncoder::MODE_NUMERIC,'89012345')
        );        
        */
        //$this->data = 'ABCDEFGH0123456789012';//''01234567';//ABC01234567890123456789ABC0123';
        //$this->data = 'AC-42';
        //$this->datafile = "input.txt"; 
        //$this->data = 'XYZAQWERTY';
        //$this->data = '01234567ABC1234567890123';
        //$this->data = "01234567765785786578657865sadgasdgfasdgfajhdf71653535jahgd";
        
        $this->data = "01234567";
        
	/*
        $this->data = array(
            array(QREncoder::MODE_ALPHANUM,'01'),
        );
        */

        try {
            if( $this->isCmdLine() || $aForceText ) {
                $this->RunTextBackend();
            }
            else {
                $this->RunGraphicBackend();
            }
        } catch( QRException $e ) {
            echo $this->fmtInfo("Exception: " . $e->GetMessage() ); //. "\n Full error:\n" . $e ."\n" ;
        }
    }
}



$driver=new Driver();
$driver->Run();

//$driver->CreateBCH155Table();
//$driver->Tst13Pattern();

?>
