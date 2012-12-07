<?php
/*=======================================================================
 // File:        QREXCEPTION.INC.PHP
 // Description: QR Exception handling and localized error messages
 // Created:     2006-09-06
 // Ver:         $Id: qrexception.inc.php 1495 2009-07-06 03:59:20Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

DEFINE('DEFAULT_ERRSTR_LOCALE','EN');

global $__jpg_qr_errstr_locale;
$__jpg_qr_errstr_locale = DEFAULT_ERRSTR_LOCALE;

class QRErrorStr_EN  {

    static protected $iErrStr = array(
    /* Backend errors */
    1000 => 'Tilde processing is not yet supported for QR Barcodes.',
    1001 => 'Inverting the bit pattern is not supported for QR Barcodes.',
    1002 => 'Cannot read data from file %s',
    1003 => 'Cannot open file %s',
    1004 => 'Cannot write QR barcode to file %s',
    1005 => 'Unsupported image format selected. Check your GD installation',
    1006 => 'Cannot set the selected barcode colors. Check your GD installation and spelling of color name',
    1007 => '<table border="1"><tr><td style="color:darkred; font-size:1.2em;"><b>QR Error:</b>
HTTP headers have already been sent.<br>Caused by output from file <b>%s</b> at line <b>%d</b>.</td></tr><tr><td><b>Explanation:</b><br>HTTP headers have already been sent back to the browser indicating the data as text before the library got a chance to send it\'s image HTTP header to this browser. This makes it impossible for the QR library to send back image data to the browser (since that would be interpretated as text by the browser and show up as junk text).<p>Most likely you have some text in your script before the call to <i>Backend::Stroke()</i>. If this texts gets sent back to the browser the browser will assume that all data is plain text. Look for any text (even spaces and newlines) that might have been sent back to the browser. <p>For example it is a common mistake to leave a blank line before the opening "<b>&lt;?php</b>".</td></tr></table>',
    1008 => 'Could not create the barcode image with image format=%s. Check your GD/PHP installation.',
    1009 => 'Cannot open log file %s for writing.',
    1010 => 'Cannot write log info to log file %s.',
    1011 => 'Could not write the QR Code to file. Check the filesystem permissions.',

    /* Mask error */
    1100 => 'Internal error: Illegal mask pattern selected',
    1101 => 'Internal error: Trying to apply masking to functional pattern.',
    1102 => 'Internal error: applyMaskAndEval(): Found uninitialized module in matrix when applying mask pattern.',

    /* Layout error  */
    1200 => 'Internal error: Was expecting %d bits in version %d to be placed in matrix but got %d bits',
    1201 => 'Internal error: Trying to position bit outside the matrix x=%d, y=%d, size=%d, bIdx=%d',
    1202 => 'Internal error: Trying to put data in initialized bit.',
    1203 => 'Internal error: Mask number for format bits is invalid. (maskidx=%d)',
    1204 => 'Internal error: Found an uninitialized bit [val=%d] at (%d,%d) when flattening matrix',

    /* Capacity error */
    1300 => 'Internal error: QRCapacity::getFormatBits() Was expecting a format in range [0,31] got %d',
    1301 => 'Internal error: QRCapacity::getVersionBits() Was expecting a version in range [7,40] got %d',
    1302 => 'Internal error: QRCapacity::_chkVerErr() Was expecting version in range [1,40] and error level in range [0,3] got (%d,%d)',
    1303 => 'Internal error: QRCapacity::getAlignmentPositions() Expected %d patterns but found %d patterns (len=%d).',
    1304 => 'Internal error: QRCapacity::%s Was expecting a version in range [1,40] got %d',

    /* Encoder errors */
    1400 => 'QR Version must be specified as a value in the range [1,40] got %d',
    1401 => 'Input data to barcode can not be empty.',
    1402 => 'Automatic encodation mode was specified but input data looks like specification for manual encodation.',
    1403 => 'Was expecting an array of arrays as input data for manual encoding.',
    1404 => 'Each input data array element must consist of two entries. Element $i has of $nn entries',
    1405 => 'Each input data array element must consist of two entries with first entry being the encodation constant and the second element the data string. Element %d is incorrect in this respect.',
    1406 => 'Was expecting either a string or an array as input data',
    1407 => 'Manual encodation mode was specified but input data looks like specification for automatic encodation.',
    1408 => 'Input data too large to fit into one QR Symbol',
    1409 => 'The selected symbol version %d is too small to fit the specified data and selected error correction level.',
    1410 => 'Trying to read past the last available codeword in block split.',
    1411 => 'Internal error: Expected 1 or 2 as the number of block structures.',
    1412 => 'Internal error: Too many codewords for chosen symbol version. (negative number of pad codewords).',
    1413 => 'Internal error: splitInBytes: Expected an even number of 8-bit blocks.',
    1414 => 'Internal error: getCountBits() illegal version number (=%d).',
    1415 => 'Manually specified encodation schema MODE_NUMERIC has no data that can be encoded using this schema.',
    1416 => 'Manually specified encodation schema MODE_ALPHANUM has no data that can be encoded using this schema.',
    1417 => 'Manually specified encodation schema MODE_BYTE has no data that can be encoded using this schema.',
    1418 => 'Unsupported encodation schema specified (%d)',
    1419 => 'Found character in data stream that cannot be encoded with the selected manual encodation mode.',
    1420 => 'Encodation using KANJI mode not yet supported.',
    1421 => 'Internal error: Unsupported encodation mode doAuto().',
    1422 => 'Found unknown characters in the data stream that can\'t be encoded with any available encodation mode.',
    1423 => 'Kanji character set not yet supported.',
    1424 => 'Internal error: DataStorage:: Unsupported character mode (%d) DataStorage::Remaining()',
    1425 => 'Internal error: DataStorage:: Trying to extract slice of len=%d (with type=%d) when there are only %d elements left',
    1426 => 'Internal error: DataStorage:: Trying to read past input data length.',
    1427 => 'Expected either DIGIT, ALNUM or BYTE but found ASCII code=%d',
    1428 => 'Internal error: DataStorage::Peek() Trying to peek past input data length.',
    );


    static function Get($aErrNo, $aArg1='',$aArg2='',$aArg3='',$aArg4='',$aArg5='') {
        if( !in_array(abs($aErrNo),array_keys(self::$iErrStr)) ) {
            throw new QRException("Internal error: Localized Error number ($aErrNo) does not exist.");
        }
        return sprintf(self::$iErrStr[$aErrNo],$aArg1,$aArg2,$aArg3,$aArg4,$aArg5);
    }

}

class QRException extends Exception {
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0) {
        // make sure everything is assigned properly
        parent::__construct($message, $code);
    }
    // custom string representation of object
    public function _toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message} at " . basename($this->getFile()) . ":" . $this->getLine() . "\n" . $this->getTraceAsString() . "\n";
    }
}

// Setup the default handler
global $__jpgdm_OldHandler;
$__jpgdm_OldHandler = set_exception_handler(array('QRExceptionL','defaultHandler'));

class QRExceptionL extends QRException {
    public function __construct($aMsgId, $aArg1='',$aArg2='',$aArg3='',$aArg4='',$aArg5='') {
        global $__jpg_qr_errstr_locale;
        $msg = call_user_func(array('QRErrorStr_'.$__jpg_qr_errstr_locale,'Get'),$aMsgId,$aArg1,$aArg2,$aArg3,$aArg4,$aArg5);
        parent::__construct($msg, $aMsgId);
    }
   static public function defaultHandler(Exception $e) {
        global $__jpg_OldHandler;
        if( $e instanceof QRException ) {
            $errobj = new QRErrObjectImg();
        	$errobj->Raise($e->getMessage());
        }
        else {
            // Restore old handler
            if( $__jpgdm_OldHandler !== NULL ) {
                set_exception_handler($__jpgdm_OldHandler);
            }
            throw $exception;
        }
    }

}


require_once(dirname(__FILE__).'/../gd_image.inc.php');
require_once(dirname(__FILE__).'/../jpgraph_text.inc.php');
//==============================================================
// An image based error handler
//==============================================================
class QRErrObjectImg  {

	private $iTitle = 'QR Code error';
	private $iDest = null;

    function __construct() {
        // Empty. Reserved for future use
    }

    function Raise($aMsg,$aHalt=true) {
        $img_iconerror =
     'iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAMAAAC7IEhfAAAAaV'.
     'BMVEX//////2Xy8mLl5V/Z2VvMzFi/v1WyslKlpU+ZmUyMjEh/'.
     'f0VyckJlZT9YWDxMTDjAwMDy8sLl5bnY2K/MzKW/v5yyspKlpY'.
     'iYmH+MjHY/PzV/f2xycmJlZVlZWU9MTEXY2Ms/PzwyMjLFTjea'.
     'AAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACx'.
     'IAAAsSAdLdfvwAAAAHdElNRQfTBgISOCqusfs5AAABLUlEQVR4'.
     '2tWV3XKCMBBGWfkranCIVClKLd/7P2Q3QsgCxjDTq+6FE2cPH+'.
     'xJ0Ogn2lQbsT+Wrs+buAZAV4W5T6Bs0YXBBwpKgEuIu+JERAX6'.
     'wM2rHjmDdEITmsQEEmWADgZm6rAjhXsoMGY9B/NZBwJzBvn+e3'.
     'wHntCAJdGu9SviwIwoZVDxPB9+Rc0TSEbQr0j3SA1gwdSn6Db0'.
     '6Tm1KfV6yzWGQO7zdpvyKLKBDmRFjzeB3LYgK7r6A/noDAfjtS'.
     'IXaIzbJSv6WgUebTMV4EoRB8a2mQiQjgtF91HdKDKZ1gtFtQjk'.
     'YcWaR5OKOhkYt+ZsTFdJRfPAApOpQYJTNHvCRSJR6SJngQadfc'.
     'vd69OLMddVOPCGVnmrFD8bVYd3JXfxXPtLR/+mtv59/ALWiiMx'.
     'qL72fwAAAABJRU5ErkJggg==' ;

        if( function_exists('imagetypes') ) {
            $supported = imagetypes();
        } else {
            $supported = 0;
        }

        if( !function_exists('imagecreatefromstring') ) {
            $supported = 0;
        }

        if( ob_get_length() || headers_sent() || !($supported & IMG_PNG) ) {
            // Special case for headers already sent or that the installation doesn't support
            // the PNG format (which the error icon is encoded in).
            // Dont return an image since it can't be displayed
            echo $this->iTitle.' '.$aMsg.'\n';
            return;
        }

        $aMsg = wordwrap($aMsg,55);
        $lines = substr_count($aMsg,'\n');

        // Create the error icon GD
        $erricon = imagecreatefromstring(base64_decode($img_iconerror));

        // Create an image that contains the error text.
        $w=400;
        $h=100 + 15*max(0,$lines-3);

        $img = new Image($w,$h);

        // Drop shadow
        $img->SetColor('gray');
        $img->FilledRectangle(5,5,$w-1,$h-1,10);
        $img->SetColor('gray:0.7');
        $img->FilledRectangle(5,5,$w-3,$h-3,10);

        // Window background
        $img->SetColor(array(188,209,193));
        $img->FilledRectangle(1,1,$w-5,$h-5);
        $img->CopyCanvasH($img->img,$erricon,5,30,0,0,40,40);

        // Window border
        $img->SetColor('black');
        $img->Rectangle(1,1,$w-5,$h-5);
        $img->Rectangle(0,0,$w-4,$h-4);

        // Window top row
        $img->SetColor('darkred');
        for($y=3; $y < 18; $y += 2 )
        $img->Line(1,$y,$w-6,$y);

        // 'White shadow'
        $img->SetColor('white');

        // Left window edge
        $img->Line(2,2,2,$h-5);
        $img->Line(2,2,$w-6,2);

        // 'Gray button shadow'
        $img->SetColor('darkgray');

        // Gray window shadow
        $img->Line(2,$h-6,$w-5,$h-6);
        $img->Line(3,$h-7,$w-5,$h-7);

        // Window title
        $m = floor($w/2-5);
        $l = 100;
        $img->SetColor('lightgray:1.3');
        $img->FilledRectangle($m-$l,2,$m+$l,16);

        // Stroke text
        $img->SetColor('darkred:0.9');
        $img->SetFont(FF_FONT2,FS_BOLD);
        $img->StrokeText($m-70,15,$this->iTitle);
        $img->SetColor('black');
        $img->SetFont(FF_FONT1,FS_NORMAL);
        $txt = new Text($aMsg,52,25);
        $txt->Align('left','top');
        $txt->Stroke($img);
        if ($this->iDest) {
            $img->Stream($this->iDest);
        } else {
            $img->Headers();
            $img->Stream();
        }
    }
}

?>
