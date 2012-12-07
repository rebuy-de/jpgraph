<?php
/*=======================================================================
// File: 	DMEXCEPTION.INC.PHP
// Description:	Datamatrix Exception handling and localized error messages
// Created: 	2006-09-07
// Ver:		$Id: dmexception.inc.php 1508 2009-07-06 19:19:26Z ljp $
//
// Copyright (c) 2008 Asial Corporation. All rights reserved.
//========================================================================
*/

DEFINE('DEFAULT_DM_ERRSTR_LOCALE','EN');

global $__jpg_dm_errstr_locale;
$__jpg_dm_errstr_locale = DEFAULT_DM_ERRSTR_LOCALE;

class DMErrorStr_EN  {

    static protected $iErrStr = array(
	1 =>  'Data is too long to fit specified symbol size.',
	2 =>  'The BASE256 data is too long to fit available symbol size.',
	3 =>  'Data must have at least three characters for C40 encodation.',
	4 =>  'Data must have at least three characters for TEXT encodation.',
	5 =>  'Internal error: (-5) Trying to read source data past the end.',
	6 =>  'Internal error: (-6) Trying to look ahead in data past the end.',
	7 =>  'Internal error: (-7) Logic error in TEXT/C40 encodation (impossible branch).',
	8 =>  'The given data can not be encoded using X12 encodation.',
	9 => 'The "tilde" encoded data is not valid.',
	10 => 'Data must have at least three characters for X12 encodation.',
	11 => 'Specified data can not be encoded with datamatrix 000 140.',
	12 => 'Can not create image.',
	13 => 'Invalid color specification.',
	14 => 'Internal error: (-14) Index for 140 bit placement matrix out of bounds.',
	15 => 'This PHP installation does not support the chosen image encoding format.',
	16 => 'Internal error: (-16) Cannot instantiate ReedSolomon.',
	20 => 'The specification for shape of matrix is out of bounds (0,29).',
	21 => 'Cannot open the data file specifying bit placement for Datamatrix 200.',
	22 => 'Datafile for bit placement is corrupt, crc checks fails.',
	23 => 'Internal error: (-23) Output matrice is not big enough for mapping matrice.',
	24 => 'Internal error: (-24) Bit sequence to be placed is too short for the chosen output matrice.',
	25 => 'Internal error: (-25) Shape index out of bounds for bit placement.',
	26 => 'Cannot open the data file specifying bit placement for Datamatrix 140.',
	30 => 'The symbol size specified for ECC140 type Datamatrix is not valid.',
	31 => 'Data is too long to fit into any available matrice size for datamatrix 140.',
	32 => 'Internal error: (-32) Cannot instantiate MasterRandom.',
	33 => 'Internal error: (-33) Failed to randomize 140 bit stream.',
	34 => 'Cannot open file %s for writing.',
	35 => 'Cannot write to file %s .',
	99 => 'EDIFACT encodation not implemented.',
	100 => '<table border="1"><tr><td style="color:darkred; font-size:1.2em;"><b>Datamatrix Error:</b>
HTTP headers have already been sent.<br>Caused by output from file <b>%s</b> at line <b>%d</b>.</td></tr><tr><td><b>Explanation:</b><br>HTTP headers have already been sent back to the browser indicating the data as text before the library got a chance to send it\'s image HTTP header to this browser. This makes it impossible for the Datamatrix library to send back image data to the browser (since that would be interpretated as text by the browser and show up as junk text).<p>Most likely you have some text in your script before the call to <i>DatamatrixBackend::Stroke()</i>. If this texts gets sent back to the browser the browser will assume that all data is plain text. Look for any text (even spaces and newlines) that might have been sent back to the browser. <p>For example it is a common mistake to leave a blank line before the opening "<b>&lt;?php</b>".</td></tr></table>',
	);

    static function Get($aErrNo, $aArg1='',$aArg2='',$aArg3='',$aArg4='',$aArg5='') {
	$aErrNo = abs($aErrNo);
	if( !in_array($aErrNo,array_keys(self::$iErrStr)) ) {
            throw new DMException("Internal error: Localized Error number ($aErrNo) does not exist.");
        }
	return sprintf(self::$iErrStr[$aErrNo],$aArg1,$aArg2,$aArg3,$aArg4,$aArg5);
    }

}

class DMException extends Exception {
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
$__jpgdm_OldHandler = set_exception_handler(array('DMExceptionL','defaultHandler'));

class DMExceptionL extends DMException {
    public function __construct($aMsgId, $aArg1='',$aArg2='',$aArg3='',$aArg4='',$aArg5='') {
	global $__jpg_dm_errstr_locale;
	$msg = call_user_func(array('DMErrorStr_'.$__jpg_dm_errstr_locale,'Get'),abs($aMsgId),$aArg1,$aArg2,$aArg3,$aArg4,$aArg5);
        parent::__construct($msg, $aMsgId);
    }
   static public function defaultHandler(Exception $e) {
        global $__jpg_OldHandler;
        if( $e instanceof DMException ) {
            $errobj = new DMErrObjectImg();
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
class DMErrObjectImg  {

	private $iTitle = 'Datamatrix error';
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
