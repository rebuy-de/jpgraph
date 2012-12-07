<?php
/*=======================================================================
 // File:          BACKEND.INC.PHP
 // Description:   All various output backends for QR barcodes available
 // Created:       2008-08-01
 // Ver:           $Id: backend.inc.php 1504 2009-07-06 13:34:57Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

DEFINE('BACKEND_ASCII', 0);
DEFINE('BACKEND_IMAGE', 1);
DEFINE('BACKEND_PS', 2);
DEFINE('BACKEND_EPS', 3);

//--------------------------------------------------------------------------------
//       Class: QRCodeBackend
// Description: Parent class for all common functionality for barcode backends
//--------------------------------------------------------------------------------
class QRCodeBackend {
    protected $iEncoder = NULL;
    protected $iModWidth = 2;
    protected $iInv = false;
    protected $iQuietZone = 0;
    protected $iError = 0;
    protected $iQRInfo = array(); // Holds some infromation the QR code just generated

    function __construct($aBarcodeEncoder) {
        $this->iEncoder = $aBarcodeEncoder;
    }

    function Stroke(&$aData, $aFileName = '', $aDebug = false, $aDebugFile = 'qrlog.txt') {

        if( $aDebug !== FALSE ) {
            $this->iEncoder->SetDebugLevel($aDebug);
        }

        // If data is an array we assume it is supposed to be manual encodation.
        // (A more thorough data check is made in the encodation class)
        $manual = is_array($aData) ;

        // Return the print specificiation (QRLayout)
        return $this->iEncoder->Enc($aData, $manual);

    }

    function GetQRInfo() {
    	return $this->iQRInfo;
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

    function SetModuleWidth($aW) {
        $this->iModWidth = $aW;
    }

    function SetQuietZone($aW) {
        $this->iQuietZone = $aW;
    }

    function SetTilde($aFlg = true) {
        //throw new QRException('Tilde processing is not yet supported for QR Barcodes.',-1);
        throw new QRExceptionL(1000);
        //$this->iEncoder->SetTilde($aFlg);
    }

    function SetInvert($aFlg = true) {
        //throw new QRException("Inverting the bit pattern is not supported for QR Barcodes.",-1);
        throw new QRExceptionL(1001);
    }

    function GetError() {
        return $this->iError;
    }

    function StrokeFromFile($aFromFileName,$aFileName='',$aDebug=FALSE) {
        $data = @file_get_contents($aFromFileName);
        if( $data === FALSE ) {
            //throw new QRException("Cannot read data from file $aFromFileName");
            throw new QRExceptionL(1002,$aFromFileName);
        }
        $this->Stroke($data,$aFileName,$aDebug);
    }
}

//--------------------------------------------------------------------------------
//       Class: QRCodeBackend_PS
// Description: Backend to generate postscript (or EPS) representation of the barcode
//--------------------------------------------------------------------------------

class QRCodeBackend_PS extends QRCodeBackend {

    private $iEPS = false;

    function __construct($aBarcodeEncoder) {
        parent::__construct($aBarcodeEncoder);
    }

    function SetEPS($aFlg=true) {
        $this->iEPS = $aFlg;
    }

    function Stroke(&$aData, $aFileName = '', $aDebug = false, $aDebugFile = 'qrlog.txt') {

        $pspec = parent::Stroke($aData, $aFileName, $aDebug, $aDebugFile);
        $w = $this->iModWidth;
        $n = $pspec->iSize[0]; // width/height of matrix

        $ystart = 4*$w + $n*$w;
        $xstart = 4*$w ;

        $totwidth  = $n*$w+8*$w ;
        $totheight = $n*$w+8*$w ;

        $psbar = "%Data: $aData\n";
        $psbar .= "%Each line represents one row and the x-position for black modules: [xpos]\n";

        if( is_array($aData)) {
            $data = " (manual encodation schemas) \n";
            $m = count($aData);
            for($i=0; $i < $m; $i++) {
                $data .= "%% (" . $aData[$i][0] . " : " . $aData[$i][1] . ")\n" ;
            }
            $aData = $data;
        }


        $y = $ystart;
        $psbar .= "\n";
        $psbar .= ($w+0.05)." setlinewidth\n";
        for( $r=0; $r < $n ; ++$r, $y -= $w ) {
            $psbar .= '[';
            $x = $xstart;
            for( $i=0; $i < $n; ++$i, $x += $w ) {
                if( $pspec->iMatrix[$r][$i] == 1) {
                    $psbar .= "[$x]";
                }
            }
            $psbar .= "] {{} forall $y moveto 0 -".($w+0.05)." rlineto stroke} forall\n";
        }
        $psbar .= "\n";
        $y += 4*$w;

        $psbar .= "%End of QR Barcode \n\n";

        if( !$this->iEPS )
        $psbar .= "showpage \n\n";

        $psbar .= "%%Trailer\n\n";

        $errStr = array('L', 'M', 'Q', 'H');
        $ps = ($this->iEPS ? "%!PS-Adobe EPSF-3.0\n" : "%!PS-Adobe-3.0\n" ) .
                "%%Title: QR Barcode ".$pspec->iVersion."-".$errStr[$pspec->iErrLevel].", mask=".$pspec->iMaskIdx."\n".
                "%%Creator: JpGraph Barcode http://jpgraph.net/\n".
                "%%CreationDate: ".date("D j M H:i:s Y",time())."\n";

        if( $this->iEPS ) {
            $ps .= "%%BoundingBox: 0 0 $totwidth $totheight\n";
        }
        else {
            $ps .= "%%DocumentPaperSizes: A4\n";
        }

        $ps .=
            "%%EndComments\n".
            "%%BeginProlog\n".
            "%%EndProlog\n";

        if( !$this->iEPS )
        $ps .=  "%%Page: 1 1\n";

        $ps .=  "\n%Module width: $this->iModWidth pt\n\n";

        /*
         if( $this->iScale != 1 ) {
         $ps .=
         "%%Scale barcode\n".
         "$this->iScale $this->iScale scale\n\n";
         }
         */

        $ps = $ps.$psbar;

		$errStr=array ( 'L', 'M', 'Q', 'H' );
        $this->iQRInfo = array($pspec->iVersion,$errStr[$pspec->iErrLevel],$pspec->iMaskIdx);

        if( $aFileName !== '' ) {
            $fp = @fopen($aFileName,'wt');
            if( $fp === FALSE ) {
                // throw new QRException("Cannot open file $aFileName.");
                throw new QRExceptionL(1003,$aFileName);
            }
            if( fwrite($fp,$ps) === FALSE ) {
                //throw new QRException("Cannot write barcode to file $aFileName.");
                throw new QRExceptionL(1004,$aFileName);
            }
            return fclose($fp);
        }
        else {
            return $ps;
        }
    }
}

require_once('rgb_colors.inc.php');

//--------------------------------------------------------------------------------
//       Class: QRCodeBackend_IMAGE
// Description: Backend to generate image representation of the barcode
//--------------------------------------------------------------------------------
class QRCodeBackend_IMAGE extends QRCodeBackend {
    private $iColor = array ( array ( 0, 0, 0 ), array ( 255, 255, 255 ), array ( 255, 255, 255 ) );

    private $iRGB = null;
    private $iImgFormat = 'png', $iQualityJPEG = 75;

    function __construct($aBarcodeEncoder) {
        parent::__construct($aBarcodeEncoder);
        $this->iRGB=new BarcodeRGB();
    }

    function SetSize($aShapeIdx) {
        $this->iEncoder->SetSize($aShapeIdx);
    }

    function SetColor($aOne, $aZero, $aBackground = array ( 255, 255, 255 )) {
        $this->iColor[0]=$aOne;
        $this->iColor[1]=$aZero;
        $this->iColor[2]=$aBackground;
    }

    // Specify image format. Note depending on your installation
    // of PHP not all formats may be supported.
    function SetImgFormat($aFormat, $aQuality = 75) {
        $this->iQualityJPEG=$aQuality;
        $this->iImgFormat=$aFormat;
    }

    function PrepareImgFormat() {
        $format=strtolower($this->iImgFormat);

        if ( $format == 'jpg' ) {
            $format = 'jpeg';
        }

        $tst=true;
        $supported=imagetypes();

        if ( $format == "auto" ) {
            if ( $supported & IMG_PNG )
            $this->iImgFormat="png";
            elseif( $supported & IMG_JPG )
            $this->iImgFormat="jpeg";
            elseif( $supported & IMG_GIF )
            $this->iImgFormat="gif";
            elseif( $supported & IMG_WBMP )
            $this->iImgFormat="wbmp";
            else {
                //throw new QRException('Unsupported image format selected. Check your GD installation', -1);
                throw new QRExceptionL(1005);
            }
        }
        else {
            if ( $format == "jpeg" || $format == "png" || $format == "gif" ) {
                if ( $format == "jpeg" && !($supported & IMG_JPG) )
                $tst=false;
                elseif( $format == "png" && !($supported & IMG_PNG) )
                $tst=false;
                elseif( $format == "gif" && !($supported & IMG_GIF) )
                $tst=false;
                elseif( $format == "wbmp" && !($supported & IMG_WBMP) )
                $tst=false;
                else {
                    $this->iImgFormat = $format;
                }
            }
            else
            $tst=false;

            if ( !$tst ) {
                //throw new QRException('Unsupported image format selected. Check your GD installation', -1);
                throw new QRExceptionL(1005);
            }
        }

        return true;
    }

    function Stroke(&$aData, $aFileName = '', $aDebug = false, $aDebugFile = 'qrlog.txt') {

        // Check the chosen graphic format
        $this->PrepareImgFormat();

        $pspec = parent::Stroke($aData, $aFileName, $aDebug, $aDebugFile);
        $mat=$pspec->iMatrix;
        $m=$this->iModWidth;

        $this->iQuietZone = $pspec->iQuietZone;

        $h=$pspec->iSize[0] * $m + 2 * $m * $this->iQuietZone;
        $w=$pspec->iSize[1] * $m + 2 * $m * $this->iQuietZone;

        $img=@imagecreatetruecolor($w, $h);

        if ( !$img ) {
            $this->iError=-12;
            return false;
        }

        $canvas_color = $this->iRGB->Allocate($img, 'white');
        $one_color    = $this->iRGB->Allocate($img, $this->iColor[0]);
        $zero_color   = $this->iRGB->Allocate($img, $this->iColor[1]);
        $bkg_color    = $this->iRGB->Allocate($img, $this->iColor[2]);

        if ( $this->iInv && $pspec->iAllowColorInversion ) {
            // Swap one/zero colors
            $tmp=$one_color;
            $one_color=$zero_color;
            $zero_color=$tmp;
        }

        if ( $canvas_color === false || $one_color === false || $zero_color === false || $bkg_color === false ) {
            // throw new QRException('Cannot set the selected colors. Check your GD installation and spelling of color name', -1);
            throw new QRExceptionL(1006);
        }

        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $canvas_color);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bkg_color);

        $borderoffset=0;

        if ( $pspec->iDrawLeftBottomBorder ) {
            // Left alignment line
            imagefilledrectangle($img, $m * $this->iQuietZone, $m * $this->iQuietZone, $m * $this->iQuietZone + $m - 1,
            $h - $m * $this->iQuietZone - 1, $one_color);

            // Bottom alignment line
            imagefilledrectangle($img, $m * $this->iQuietZone, $h - $m * $this->iQuietZone - $m,
            $w - $m * $this->iQuietZone - 1, $h - $m * $this->iQuietZone - 1, $one_color);

            $borderoffset=1;
        }

        for( $i = 0; $i < $pspec->iSize[0] - $borderoffset; ++$i ) {
            for( $j = $borderoffset; $j < $pspec->iSize[1]; ++$j ) {
                $bit = $mat[$i][$j] == 1 ? $one_color : $zero_color;

                if ( $m == 1 ) {
                    imagesetpixel($img, $j + $m * $this->iQuietZone, $i + $m * $this->iQuietZone, $bit);
                }
                else {
                    imagefilledrectangle($img, $j * $m + $m * $this->iQuietZone,
                    $i * $m + $m * $this->iQuietZone, $j * $m + $m - 1 + $m * $this->iQuietZone,
                    $i * $m + $m - 1 + $m * $this->iQuietZone, $bit);
                }
            }
        }

        if ( $pspec->iDrawLeftBottomBorder ) {
            // Left alignment line
            imagefilledrectangle($img, $this->iQuietZone, $this->iQuietZone, $this->iQuietZone + $m - 1,
            $h - $this->iQuietZone - 1, $one_color);

            // Bottom alignment line
            imagefilledrectangle($img, $this->iQuietZone, $h - $this->iQuietZone - $m, $w - $this->iQuietZone - 1,
            $h - $this->iQuietZone - 1, $one_color);
        }

        if ( headers_sent($file, $lineno) ) {
            // Headers already sent special error
            throw new QRExceptionL(1007,$file,$lineno);
        }

        if ( $aFileName == '' ) {
            $s=php_sapi_name();

            if ( substr($s, 0, 3) != 'cli' ) {
                header("Content-type: image/$this->iImgFormat");
            }

            switch( $this->iImgFormat ) {
                case 'png':
                    $res=@imagepng($img);
                    break;

                case 'jpeg':
                    $res=@imagejpeg($img, NULL, $this->iQualityJPEG);
                    break;

                case 'gif':
                    $res=@imagegif($img);
                    break;

                case 'wbmp':
                    $res=@imagewbmp($img);
                    break;
            }
        	if( $res === FALSE ) {
            	throw new QRExceptionL(1008,$this->iImgFormat);
	            //throw new QRException("Could not create the barcode image. Check your GD/PHP installation.");
    	    }
        }
        else {
            switch( $this->iImgFormat ) {
                case 'png':
                    $res=@imagepng($img, $aFileName);
                    break;

                case 'jpeg':
                    $res=@imagejpeg($img, $aFileName, $this->iQualityJPEG);
                    break;

                case 'gif':
                    $res=@imagegif($img, $aFileName);
                    break;

                case 'wbmp':
                    $res=@imagewbmp($img, $aFileName);
                    break;
            }
        	if( $res === FALSE ) {
            	throw new QRExceptionL(1011,$this->iImgFormat);
            	// 1011 => 'Could not write the barcode to file. Check the filesystem permission.',
    	    }
        }

        // If debugging is enabled store encoding info in the specified log file
        if( $aDebug !== FALSE && $aDebugFile !== '' ) {
            $s = "QR Barcode Log created: " . date('r')."\n";
            $s .= "Debug level = $aDebug \n";
            $s .= "SAPI: " . php_sapi_name() ."\n\n";
            $s .= $this->iEncoder;
            $s .= $pspec;

            $fp = @fopen($aDebugFile,'wt');
            if( $fp === FALSE ) {
                //throw new QRException("Cannot open log file for writing $aDebugFile.");
                throw new QRExceptionL(1009,$aDebugFile);
            }
            if( @fwrite($fp,$s) === FALSE ) {
                //throw new QRException("Cannot write log info to log file $aDebugFile.");
                throw new QRExceptionL(1010,$aDebugFile);
            }
            fclose($fp);
        }

		$errStr=array ( 'L', 'M', 'Q', 'H' );
        $this->iQRInfo = array($pspec->iVersion,$errStr[$pspec->iErrLevel],$pspec->iMaskIdx);

        return 'true';
    }
}

//--------------------------------------------------------------------------------
//       Class: QRCodeBackend_ASCII
// Description: Backend to generate ASCII representation of the barcode
//--------------------------------------------------------------------------------
class QRCodeBackend_ASCII extends QRCodeBackend {
    function __construct(&$aBarcodeEncoder) {
        parent::__construct($aBarcodeEncoder);
    }

    function GetMatrixString($mat, $inv = false, $width = 1, $aOne = 'X', $aZero = '-') {
        if ( $width > 1 ) {
            $m=count($mat);
            $n=count($mat[0]);

            $newmat=array();

            for( $i = 0; $i < $m; ++$i )
            for( $j = 0; $j < $n; ++$j )
            for( $k = 0; $k < $width; ++$k )
            for( $l = 0; $l < $width; ++$l )
            $newmat[$i * $width + $k][$j * $width + $l]=$mat[$i][$j];

            $mat=$newmat;
        }

        $m=count($mat);
        $n=count($mat[0]);
        $s = '';
        for( $i = 0; $i < $m; ++$i ) {
            for( $j = 0; $j < $n; ++$j ) {
                if ( !$inv ) {
                    $s .= $mat[$i][$j] ? $aOne : $aZero;
                }
                else {
                    $s .= !$mat[$i][$j] ? $aOne : $aZero;
                }
            }
            $s .=  "\n";
        }
        $s .=  "\n";
        return $this->fmtInfo($s);
    }

    function Stroke(&$aData, $aFileName='', $aDebug = FALSE, $aDebugFile = '') {

        $pspec = parent::Stroke($aData, $aFileName, $aDebug, $aDebugFile);

        // If debugging is enabled store encoding info in the specified log file
        if( $aDebug !== FALSE ) {
            $s = str_repeat('=',80)."\n";
            $s .= "QR Barcode Log created: " . date('r')."\n";
            $s .= str_repeat('=',80)."\n";
            $s .= "Debug level = $aDebug \n";
            $s .= "SAPI: " . php_sapi_name() ."\n\n";
            $s .= $this->iEncoder;
            $s .= $pspec . "\n";
            $s .= str_repeat('=',80)."\nEnd of QR Barcode log file.\n".str_repeat('=',80)."\n\n";

            if( $aDebugFile != '' ) {
                $fp = @fopen($aDebugFile,'wt');
                if( $fp === FALSE ) {
                    // throw new QRException("Cannot open log file for writing $aDebugFile.");
                    throw new QRExceptionL(1009,$aDebugFile);
                }
                if( @fwrite($fp,$s) === FALSE ) {
                    // throw new QRException("Cannot write log info to log file $aDebugFile.");
                    throw new QRExceptionL(1010,$aDebugFile);
                }
                fclose($fp);
            }
            else {
                echo $this->fmtInfo($s);
            }
        }

        $s = $this->GetMatrixString($pspec->iMatrix, $this->iInv, $this->iModWidth, 'X', '-') . "\n";

        if( $aFileName !== '' ) {
            $fp = @fopen($aFileName,'wt');
            if( $fp === FALSE ) {
                // throw new QRException("Cannot open file $aFileName.");
                throw new QRExceptionL(1003,$aFileName);
            }
            if( fwrite($fp,$s) === FALSE ) {
                // throw new QRException("Cannot write barcode to file $aFileName.");
                throw new QRExceptionL(1004,$aFileName);
            }
            return fclose($fp);
        }
        else {
            return $s;
        }
    }
}

//--------------------------------------------------------------------------------
//       Class: QRCodeBackendFactory
// Description: Factory to return a suitable backend for generating barcode
//--------------------------------------------------------------------------------
class QRCodeBackendFactory {
    static function Create(&$aBarcodeEncoder, $aBackend = BACKEND_IMAGE) {
        switch( $aBackend ) {
            case BACKEND_ASCII:
                return new QRCodeBackend_ASCII($aBarcodeEncoder);
                break;

            case BACKEND_IMAGE:
                return new QRCodeBackend_Image($aBarcodeEncoder);
                break;

            case BACKEND_PS:
                return new QRCodeBackend_PS($aBarcodeEncoder);
                break;

            case BACKEND_EPS:
                $b = new QRCodeBackend_PS($aBarcodeEncoder);
                $b->SetEPS(true);
                return $b;
                break;

            default:
                return false;
        }
    }
}
?>
