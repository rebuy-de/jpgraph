<?php
require_once ('jpgraph.php');
require_once ('jpgraph_canvas.php');

/*=======================================================================
 // File:        JPGRAPH_BARCODE.PHP
 // Description: JpGraph Barcode Extension
 // Created:     2003-01-01
 // Ver:         $Id: jpgraph_barcode.php 1789 2009-09-03 13:47:56Z ljp $
 //
 // License:     This code is released under JpGraph Professional License
 //              http://jpgraph.net/pro/
 //
 // Copyright (c) Asial Corporation. All rights reserved.
 //
 //=======================================================================
 */


//-----------------------------------------------------------------------
//  Currently supported barcode schemas
//  - UPC-A
//  - UPC-E
//  - EAN-128
//  - EAN-13
//  - EAN-8
//  - CODE 11 (USD-8)
//  - CODE 39
//  - CODE 39 Extended
//  - CODE 128
//  - CODABAR
//  - Industry 2 of 5
//  - Interleaved 2 of 5
//  - BOOKLAND (ISBN)
//-----------------------------------------------------------------------


// What encodings we support
define("ENCODING_EAN128",1);
define("ENCODING_EAN13",2);
define("ENCODING_EAN8",3);
define("ENCODING_UPCA",4);
define("ENCODING_UPCE",5);
define("ENCODING_CODE39",6);
define("ENCODING_CODE93",7);
define("ENCODING_CODE128",8);
define("ENCODING_POSTNET",9);
define("ENCODING_BOOKLAND",10);
define("ENCODING_CODE25",11);
define("ENCODING_CODEI25",12);
define("ENCODING_CODABAR",13);
define("ENCODING_CODE11",14);

// Backends
define("BACKEND_IMAGE",'IMAGE');
define("BACKEND_PS",'PS');

// Demo version or not
define("ADD_DEMOTXT",false);

//----------------------------------------------------------------
// BarcodeFactory
// Create the appropriate encoder instance
//----------------------------------------------------------------
class BarcodeFactory {
    static function Create($aEncoder) {
        // Name of the different encoding classes
        $names = array(ENCODING_EAN128,"EAN128",
        ENCODING_EAN13,"EAN13",
        ENCODING_EAN8,"EAN8",
        ENCODING_UPCA,"UPCA",
        ENCODING_UPCE,"UPCE",
        ENCODING_CODE39,"CODE39",
        ENCODING_CODE93,"CODE93",
        ENCODING_CODE128,"CODE128",
        ENCODING_POSTNET,"POSTNET",
        ENCODING_BOOKLAND,"BOOKLAND",
        ENCODING_CODE25,"CODE25",
        ENCODING_CODEI25,"CODEI25",
        ENCODING_CODABAR,"CODABAR",
        ENCODING_CODE11,"CODE11"
        );
        // Check if encoder exists
        $pos = array_search($aEncoder, $names);
        if( $pos===false ) {
            JpGraphError::RaiseL(1001,$aEncoder);
            //('Unknown encoder specification: '.$aEncoder);
        }
        $name = 'BarcodeEncode_'.$names[$pos+1];
        $obj = new $name();
        return $obj;
    }
}

//----------------------------------------------------------------
// BarcodeEncode
// Base class for every encoder
//----------------------------------------------------------------
class BarcodeEncode {
    public $iName = 'UNDEFINED';
    public $iUseChecksum=false;
    protected $iUseTilde=false;

    function __construct() {
    }

    function Enc($aData) {
        if( $this->iUseTilde ) {
            $aData = $this->TildeProcess($aData);
        }
        if( !$this->Validate($aData,false) ) {
            JpGraphError::RaiseL(1002,$aData,$this->GetName());
            //('Data validation failed. Can\'t encode ['.$aData.'] using encoding "'.$this->GetName().'"');
        }
    }

    function SetTilde($aFlg=true) {
        $this->iUseTilde = $aFlg;
    }

    function GetName() {
        return $this->iName;
    }

    function AddChecksum($aFlg=true) {
        $this->iUseChecksum = $aFlg;
    }

    function DoValidate($aData,$aErrMsg=true) {
        $r = $this->Validate($aData);
        if( $aErrMsg && !$r ) {
            JpGraphError::RaiseL(1002,$aData,$this->GetName());
            //'Invalid data ['.$aData.'] for encoding:'.$this->GetName());
            exit(1);
        }
        return  $r;
    }

    /*-------------------------------------------------------------------------------------
     ** Preprocess the data to be encoded for easier textual specifications.
     **
     ** ~X , X in [@,Z] Used to specify the first 26 ASCII values. ~@ == 0 , ~A == 1 , ...
     **
     ** ~dNNN : Character value as 3 digits.
     **------------------------------------------------------------------------------------
     */
    function TildeProcess($aStr) {

        // Replace ~@ == 0, ~A == 1, ~B == 2 , .., ~Z == 26
        $r = str_replace('~@',chr(0),$aStr);
        for($i=0; $i < 26; ++$i ) {
            $r = str_replace('~'.chr($i+65),chr($i+1),$r);
        }

        // Replace ~dNNN with the character chr(NNN)
        $offset=0;
        while( ($n = strpos($r,'~d',$offset)) !== false ) {
            // Get the 3 digits that follow
            $sv = substr($r,$n+2,3);
            if( strlen($sv) < 3 || !ctype_digit($sv))
            return false;

            $v = intval($sv);
            $r = str_replace('~d'.$sv,chr($v),$r);
            $offset = $n+1;
        }

        return $r;
    }

    // Helper function to calculate a checksum character
    function CalcChecksum($aData,$aOdd,$aOddMult,$aEven,$aEvenMult,$aMod) {
        $no = count($aOdd);
        $ne = count($aEven);
        $sum = 0;
        for( $i=0; $i < $no; ++$i ) {
            $c = ($aData[$aOdd[$i]]-'0');
            $sum += $c;
        }
        $sum *= $aOddMult;
        $sum2 = 0;
        for( $i=0; $i < $ne; ++$i ) {
            $sum2 += ($aData[$aEven[$i]]-'0');
        }
        $sum2 *= $aEvenMult;
        $sum += $sum2;
        $cn = $sum % $aMod;
        if( $cn==0 )
        return 0;
        else
        return 10-$cn;
    }
}



//----------------------------------------------------------------
// BarcodeEncode_EAN13
//
//----------------------------------------------------------------
class BarcodeEncode_EAN13 extends BarcodeEncode {

    private $iParity = array('00000','001011','001101','001110','010011',
    '011001','011100','010101','010110','011010');

    private $iSymbols = array( array('3211','2221','2122','1411','1132',  /* Odd */
     '1231','1114','1312','1213','3112'),
    array('1123','1222','2212','1141','2311',  /* Even */
     '1321','4111','2131','3121','2113'));
    private $iGuards = array('111','11111','111');


    function __construct() {
        parent::__construct();
        $this->iName = 'EAN-13';
    }

    function Enc($aData) {
        return $this->_Enc($aData,false);
    }

    function EncUPCA($aData) {
        return $this->_Enc($aData,true);
    }

    function _Enc($aData,$aUPCA=false) {
        // Check data and get checksum
        parent::Enc($aData);
        $cn = $this->GetChecksum($aData);
        $aData .= $cn;

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = $aData;
        $e->iLeftData = substr($aData,0,1); // Number system first digit

        $e->iBar = array();

        // Left guard
        $e->iBar[] = array(0,1,1,$this->iGuards[0]);

        // Parity is determined by number system
        // unless it is UPCA in which case the number system
        // is implicitly set to 0
        if( !$aUPCA )
        $p = $this->iParity[substr($aData,0,1)+0];
        else
        $p = $this->iParity[0];

        $e->iInfo = "Parity=$p";

        // We use this method to encode UPC A as well since
        // these are identical coding just different placement
        // of the human readable symbols

        if( $aUPCA ) {
            $e->iRightData = substr($aData,11,1); // Checksum
            // Encode left hand symbols
            for($i=0; $i < 6; ++$i) {
                $pi = substr($p,$i,1)+0;
                $c = substr($aData,$i,1);
                if( $i > 0 ) {
                    $e->iBar[] = array($c,0,0,$this->iSymbols[$pi][$c-'0']);
                }
                else
                $e->iBar[] = array($c,0,1,$this->iSymbols[$pi][$c-'0']);
            }

            // Middle guard
            $e->iBar[] = array(0,0,1,$this->iGuards[1]);

            // Encode right hand symbols
            for($i=0; $i < 6 ; ++$i) {
                $c = substr($aData,$i+6,1);
                if( $i < 5 )
                $e->iBar[] = array($c,1,0,$this->iSymbols[0][$c-'0']);
                else
                $e->iBar[] = array($c,1,1,$this->iSymbols[0][$c-'0']);
            }
        }
        else {
            // Encode left hand symbols
            for($i=0; $i < 6; ++$i) {
                $pi = substr($p,$i,1)+0;
                $c = substr($aData,$i+1,1);
                $e->iBar[] = array($c,0,0,$this->iSymbols[$pi][$c-'0']);
            }

            // Middle guard
            $e->iBar[] = array(0,0,1,$this->iGuards[1]);

            // Encode right hand symbols
            for($i=0; $i < 6 ; ++$i) {
                $c = substr($aData,$i+7,1);
                $e->iBar[] = array($c,1,0,$this->iSymbols[0][$c-'0']);
            }
        }

        // Right guard
        $e->iBar[] = array(0,1,1,$this->iGuards[2]);

        return $e;
    }

    function Validate($aData) {
        // Rule: 12 digits
        $n = strlen($aData);
        if( $n > 12 || $n < 12 )
        return false;
        return ctype_digit($aData);
    }

    function GetChecksum($aData) {
        $n = $this->CalcChecksum($aData,array(1,3,5,7,9,11),3,array(0,2,4,6,8,10),1,10);
        return $n;
    }
}


//----------------------------------------------------------------
// BarcodeEncode_EAN8
//
//----------------------------------------------------------------
class BarcodeEncode_EAN8 extends BarcodeEncode_EAN13 {
    function __construct() {
        parent::__construct();
        $this->iName = 'EAN-8';
    }

    function Enc($aData) {
        // Check data and get checksum
        if( !$this->Validate($aData,false) ) {
            JpGraphError::RaiseL(1002,$aData,$this->GetName());
            //('Data validation dailed. Can\'t encode ['.$aData.'] using encoding "'.$this->GetName().'"');
        }

        $cn = $this->GetChecksum($aData);
        $aData .= $cn;

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = $aData;
        //$e->iLeftData = substr($aData,0,1); // Number system
        //$e->iRightData = substr($aData,11,1); // Checksum

        // Left guard
        $e->iBar[0] = array(0,1,1,$this->iGuards[0]);

        for( $i=0; $i<4; ++$i ) {
            $c = substr($aData,$i,1);
            $e->iBar[$i+1] = array($c,0,0,$this->iSymbols[0][$c-'0']);
        }

        // Middle guard
        $e->iBar[5] = array(0,0,1,$this->iGuards[1]);

        for( $i=0; $i<4; ++$i ) {
            $c = substr($aData,$i+4,1);
            $e->iBar[$i+6] = array($c,1,0,$this->iSymbols[0][$c-'0']);
        }

        // Right guard
        $e->iBar[10] = array(0,1,1,$this->iGuards[0]);

        return $e;

    }

    function Validate($aData) {
        return strlen($aData) <= 7 && ctype_digit($aData);
    }

    function GetChecksum($aData) {
        $n = $this->CalcChecksum($aData,array(0,2,4,6),3,array(1,3,5),1,10);
        return $n;
    }
}



//----------------------------------------------------------------
// BarcodeEncode_UPCA
//
//----------------------------------------------------------------
class BarcodeEncode_UPCA extends BarcodeEncode_EAN13 {
    function __construct() {
        parent::__construct();
        $this->iName = 'EAN-UPCA';
    }

    function Enc($aData) {
        return parent::EncUPCA($aData);
    }

    // Same as EAN-13 but for UPC-A we only allow number system '0'
    // and only up to 11 digits
    function Validate($aData) {
        return strlen($aData) <= 11 && parent::Validate("0".$aData);
    }

    function GetChecksum($aData) {
        $n = $this->CalcChecksum($aData,array(0,2,4,6,8,10),3,array(1,3,5,7,9),1,10);
        return $n;
    }

}

//----------------------------------------------------------------
// BarcodeEncode_UPCE
//
//----------------------------------------------------------------
class BarcodeEncode_UPCE extends BarcodeEncode {

    // Choose symbol version depending on checksum
    // (The checksum is encoded in the symbols even/odd pattern)
    // The 0,1 are used as indexes in the iSymbols array below
    // to choose even or odd encoding for a symbol
    private $iParity = array( '000111','001011','001101','001110','010011',
     '011001','011100','010101','010110','011010');

    private $iSymbols = array(
    array('1123','1222','2212','1141','2311',
       '1321','4111','2131','3121','2113'), /* Even parity, starts with space */
    array('3211','2221','2122','1411','1132',
       '1231','1114','1312','1213','3112')  /* Odd parity, starts with bar */
    );

    private $iGuards = array('111','111111');

    // Validate will determine what type of UPC-E number this is.
    private $iType=-1;

    // For each type we have to know the order of which to take the
    // original number when we encode it
    private $iOrder = array(array(1,2,8,9,10,3),array(1,2,3,9,10),
    array(1,2,3,4,10),array(1,2,3,4,5,10));

    function __construct() {
        parent::__construct();
        $this->iName = 'UPC-E';
    }

    function Enc($aData) {
        parent::Enc($aData);
        $cn = $this->GetChecksum($aData);
        $aData .= $cn;

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = $aData;
        $e->iLeftData = substr($aData,0,1); // Number system
        $e->iRightData = substr($aData,11,1);

        // Get parity pattern
        $ns = substr($aData,0,1)+0;
        $p = $this->iParity[$cn+0];
        $e->iInfo = " Parity=$p, Type=$this->iType";
        $e->iBar[0] = array(0,1,1,$this->iGuards[0]);
        $n = count($this->iOrder[$this->iType]);
        for( $i=0; $i < $n; ++$i ) {
            $c = substr($aData,$this->iOrder[$this->iType][$i],1);
            $pi = $ns==0 ? substr($p,$i,1)+0 : 1 - substr($p,$i,1);
            $e->iBar[$i+1] = array($c,0,0,$this->iSymbols[$pi][$c+0]);
        }
        switch( $this->iType ) {
            case 0:
            case 3:
                break;
            case 1:
                // Last symbol is always '3' for type 1
                $pi = $ns==0 ? substr($p,5,1)+0 : 1 - substr($p,5,1);
                $e->iBar[6] = array('3',0,0,$this->iSymbols[$pi][3]);
                break;
            case 2:
                // Last symbol is always '4' for type 2
                $pi = $ns==0 ? substr($p,5,1)+0 : 1 - substr($p,5,1);
                $e->iBar[6] = array('4',0,0,$this->iSymbols[$pi][4]);
                break;
            default:
                JpgraphError::RaiseL(1004,$this->iType);
                //('Internal barcode error. Unknown UPC-E encoding type:'.$this->iType);
        }

        $e->iBar[7] = array(0,0,1,$this->iGuards[1]);

        return $e;
    }

    function Validate($aData) {
        // Check that it fullfills the UPC-E requirements
        if( strlen($aData)!=11 )
        return false;

        // Number system code must be 0 or 1
        $ns = substr($aData,0,1);
        if( $ns != '0' && $ns != '1' )
        return false;

        // Get manufacture part of the data
        $mnr = substr($aData,1,5);
        $mnr3 = substr($mnr,2,3);
        $mnr2 = substr($mnr3,1,2);
        // Get item number part
        $inr = substr($aData,6,5);
        if( $mnr3=='000' || $mnr3=='100' || $mnr3=='200' ) {
            // Allowed 1000 items with number 00000 to 00999
            if( $inr > 999 ) return false;
            $this->iType=0;
        }
        elseif( substr($mnr3,1,2)=='00' && substr($mnr3,0,1)>='3' && substr($mnr3,0,1)<='9' ) {
            // Allowed 100 items with number 00000 to 00099
            if( $inr > 99 ) return false;
            $this->iType=1;
        }
        elseif( substr($mnr2,1,1)=='0' && substr($mnr2,0,1)>='1' && substr($mnr2,0,1)<='9' ) {
            // Allowed 10 items with number 00000 to 00009
            if( $inr > 9 ) return false;
            $this->iType=2;

        }
        else {
            // Allowed 5 items with number 00005 to 00009
            if( $inr > 9 || $inr < 5 ) return false;
            $this->iType=3;
        }
        return true;
    }

    function GetChecksum($aData) {
        $n = $this->CalcChecksum($aData,
        array(0,2,4,6,8,10),3,
        array(1,3,5,7,9),1,10);
        return $n;
    }
}

//----------------------------------------------------------------
// BarcodeEncode_CODE39
// Notes: Height should be 15% of width or at least .25 inch
// Quiet space 10 narrow width before and after.
//----------------------------------------------------------------
class BarcodeEncode_CODE39 extends BarcodeEncode {

    // Basic symbol encoding table.
    private $iSymbolPos = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
    // Run length encoded version of symbols
    private $iSymbol = array(
 '111221211', '211211112', '112211112', '212211111',
 '111221112', '211221111', '112221111', '111211212',
 '211211211', '112211211', '211112112', '112112112',
 '212112111', '111122112', '211122111', '112122111',
 '111112212', '211112211', '112112211', '111122211',
 '211111122', '112111122', '212111121', '111121122',
 '211121121', '112121121', '111111222', '211111221',
 '112111221', '111121221', '221111112', '122111112',
 '222111111', '121121112', '221121111', '122121111',
 '121111212', '221111211', '122111211', '121212111',
 '121211121', '121112121', '111212121' );

    private $iGuard = '121121211';

    private $iExtEscape =
 '%$$$$$$$$$$$$$$$$$$$$$$$$$$%%%%% ////////////  /          /%%%%%%                          %%%%%%++++++++++++++++++++++++++%%%%%';
    private $iExtSymbol = 'UABCDEFGHIJKLMNOPQRSTUVWXYZABCDE ABCDEFGHIJKL-.O0123456789ZFGHIJVABCDEFGHIJKLMNOPQRSTUVWXYZKLMNOWABCDEFGHIJKLMNOPQRSTUVWXYZPQRST';
    private $iUseExtended = false;

    function __construct() {
        parent::__construct();
        $this->iName = 'CODE 39';
    }

    function UseExtended($f=true) {
        $this->iUseExtended = $f;
    }

    function Enc($aData) {
        parent::Enc($aData);
        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = '*'.$aData;
        if( $this->iUseChecksum )
        $e->iData .= $this->iSymbolPos[$this->GetChecksum($aData)];
        $e->iData .= '*';
        $e->iInterCharModuleSpace = true;
        $e->iStrokeDataBelow=true;

        $e->iBar[0] = array(0,1,1,$this->iGuard);

        $n = strlen($aData);
        $bpos=1;
        for( $i=0; $i < $n; ++$i ) {
            // If we have a checksum just take the value as index
            // to the code table if we are at the last position
            $c = substr($aData,$i,1);
            $co = ord($c);
            if( $this->iUseExtended ) {
                $esc = substr($this->iExtEscape,$co,1);
                $sym =  substr($this->iExtSymbol,$co,1);
                if( $esc != ' ' ) {
                    $p = strpos($this->iSymbolPos,$esc);
                    $e->iBar[$bpos] = array($esc,1,1,$this->iSymbol[$p]);
                    $bpos++;
                }
                $p = strpos($this->iSymbolPos,$sym);
                $e->iBar[$bpos] = array($sym,1,1,$this->iSymbol[$p]);
            }
            else {
                $p = strpos($this->iSymbolPos,$c);
                $e->iBar[$bpos] = array($c,1,1,$this->iSymbol[$p]);
            }
            $bpos++;
        }
        if( $this->iUseChecksum ) {
            $cs = $this->GetChecksum($aData);
            $e->iBar[$bpos] = array(0,1,1,$this->iSymbol[$cs]);
            $bpos++;
        }
        $e->iBar[$bpos] = array(0,1,1,$this->iGuard);
        return $e;
    }

    function Validate($aData) {
        // Check that all chars in $aData are available
        $n=strlen($aData);
        if( $this->iUseExtended ) {
            for( $i=0; $i < $n; ++$i ) {
                if( substr($aData,$i,1) > 127 )
                return false;
            }
        }
        else {
            for( $i=0; $i < $n; ++$i ) {
                if( strpos($this->iSymbolPos,substr($aData,$i,1))===false )
                return false;
            }
        }
        return true;
    }

    function GetChecksum($aData) {
        $n=strlen($aData);
        $sum=0;
        for( $i=0; $i <= $n; ++$i )
        $sum += ord(substr($aData,$i,1));
        $checksum = $sum % 43;
        return $checksum;
    }
}


//----------------------------------------------------------------
// BarcodeEncode_BOOKLAND
//
//----------------------------------------------------------------
class BarcodeEncode_BOOKLAND extends BarcodeEncode_EAN13 {
    private $iBooklandNumberSystem = '978';
    function __construct() {
        parent::__construct();
        $this->iName = 'BOOKLAND (ISBN)';
    }

    function Enc($aData) {
        if( substr($aData,0,3) != '978' )
        $aData = $this->iBooklandNumberSystem.$aData;
        return parent::Enc($aData);
    }
}


//----------------------------------------------------------------
// BarcodeEncode_CODE128
//
//----------------------------------------------------------------
// Character sets
define('CODE128_A',0);  // Alphabet A
define('CODE128_B',1);  // Alphabet B
define('CODE128_C',2);  // Alphabet C

// The special characters FUNC1,2,3,4 have no
// standard ASCII representation
// It is up to the implementation to choose ASCII position
// in the high 128. (We really need FUNC1 since that is used
// to indicate UCC/EAN 128 coding
define('EA_FUNC1',128);
define('EA_FUNC2',129);
define('EA_FUNC3',130);
define('EA_FUNC4',131);

// Position for special symbols in the symbols array
define('STARTA',103);
define('STARTB',104);
define('STARTC',105);
define('SHIFT',98);
define('CODEA',101);
define('CODEB',100);
define('CODEC',99);
define('ENDGUARD',106);
define('FUNC1',102);
define('FUNC2',97);
define('FUNC3',96);

class BarcodeEncode_CODE128 extends BarcodeEncode {

    // Default start if both A and B char sets are possible
    private $iDefaultStart = CODE128_B;

    // Indexed by CODE128_A, *_B and *_C
    private $iStart = array (STARTA,STARTB,STARTC);
    private $iCharsets  = array (CODEA,CODEB,CODEC );

    private $iCurrCharset = -1 ; // Current charset
    private $iSymbols = array(
 "212222", "222122", "222221", "121223", "121322",
 "131222", "122213", "122312", "132212", "221213",
 "221312", "231212", "112232", "122132", "122231",
 "113222", "123122", "123221", "223211", "221132",
 "221231", "213212", "223112", "312131", "311222",
 "321122", "321221", "312212", "322112", "322211",
 "212123", "212321", "232121", "111323", "131123",
 "131321", "112313", "132113", "132311", "211313",
 "231113", "231311", "112133", "112331", "132131",
 "113123", "113321", "133121", "313121", "211331",
 "231131", "213113", "213311", "213131", "311123",
 "311321", "331121", "312113", "312311", "332111",
 "314111", "221411", "431111", "111224", "111422",
 "121124", "121421", "141122", "141221", "112214",
 "112412", "122114", "122411", "142112", "142211",
 "241211", "221114", "413111", "241112", "134111",
 "111242", "121142", "121241", "114212", "124112",
 "124211", "411212", "421112", "421211", "212141",
 "214121", "412121", "111143", "111341", "131141",
 "114113", "114311", "411113", "411311", "113141",
 "114131", "311141", "411131", "211412", "211214", "211232",
 "2331112" ); // End guard

    function __construct() {
        parent::__construct();
        $this->iName = 'CODE 128';
    }

    function Validate($aData) {
        // We can accept all characters with ASCII <= 127
        // And our extended data 128-131
        $n = strlen($aData);
        $i = 0;
        while( $i < $n ) {
            $c = ord(substr($aData,$i,1));
            if( $c >= 132 )
            return false;
            ++$i;
        }
        return true;
    }

    // Encode a number in charset C
    function EncodeSymbolC($aSym1,$aSym2) {
        $val = 10*$aSym1+$aSym2;
        if( $val > 99 )
        JpGraphError::RaiseL(1005,$aSym1,$aSym2);
        //("Internal error. Can't encode $aSym $aSym2 in Code-128 charset C");
        else
        return $val;
    }

    // Return encoding position in charsets A & B
    function EncodeSymbolAB($aSym) {

        switch( $aSym ) {
            case EA_FUNC1: return FUNC1;
            case EA_FUNC2: return FUNC2;
            case EA_FUNC3: return FUNC3;
            case EA_FUNC4:
                if( $this->iCurrCharset == CODE128_A )
                return CODEA;
                else
                return CODEB;
        }

        // Digits and Capitals (Same for both A and B)
        if( $aSym >= 32 && $aSym <= 95 )
        return $aSym-32;

        // Control characters (ASCII < 32 ) is folded
        // to base 64 in charset A
        if( $aSym < 32 ) {
            if( $this->iCurrCharset != CODE128_A ) {
                JpGraphError::RaiseL(1006);
                //('Internal encoding error for CODE 128. Trying to encode control chractare in CHARSET != A');
            }
            return $aSym + 64;
        }

        // DEL (as Code B)
        if( $aSym == 127 ) {
            if( $this->iCurrCharset != CODE128_B ) {
                JpGraphError::RaiseL(1007);
                //('Internal encoding error for CODE 128. Trying to encode DEL in CHARSET != B');
            }
            return 95;
        }

        // Small letters (as Code B)
        if( $aSym >= 96 && $aSym <= 126 ) {
            if( $this->iCurrCharset != CODE128_B ) {
                JpGraphError::RaiseL(1008);
                //('Internal encoding error for CODE 128. Trying to encode small letters in CHARSET != B');
            }
            return $aSym - 32;
        }

        JpGraphError::RaiseL(1003,$aSym);
        //("Internal encoding error. Trying to encode $aSym is not possible in Code 128");

    }

    // Find out what character set is required (A or B)
    function CharSetRequired($aString) {
        $n = strlen($aString);
        for( $i=0; $i < $n; ++$i ) {
            $c = ord(substr($aString,$i,1));
            if( $c < 32 ) // Control char needs charset A
            return CODE128_A;
            if( $c >= 96 && $c <= 127 ) // Small letters needs charset B
            return CODE128_B;
        }
        return -1;  // Either one will do
    }

    // Assuming we are in A or B decide if we need to switch to C
    function SwitchToC($aData,&$aPos,&$aSyms) {
        $maxlen = strlen($aData) ;
        // If there are more than 4 digits switch to C
        for( $i=0; $i+$aPos <= $maxlen && ctype_digit(substr($aData,$i+$aPos,1)); ++$i )
        ;
        if( $i >= 4 ) {
            if( $i & 1 ) { // Odd, first encode first digit {
                $aSyms[] = $this->EncodeSymbolAB(ord(substr($aData,$aPos,1)));
                $aPos++;
            }
            $aSyms[] = $this->iCharsets[CODE128_C];
            return true;
        }
        return false;
    }

    function Enc($aData) {
        if( $this->iUseTilde ) {
            // Some additional Tilde processing for CODE-128
            $aData = str_replace('~1',chr(EA_FUNC1),$aData);
            $aData = str_replace('~2',chr(EA_FUNC2),$aData);
            $aData = str_replace('~3',chr(EA_FUNC3),$aData);
        }
        parent::Enc($aData);
        $n = strlen($aData);
        $syms = array();
        // Choose starting code
        // Special case for EAN-128
        if( $aData[0] == chr(EA_FUNC1) ) {
            $this->iCurrCharset = CODE128_C;
            $syms[] = STARTC;
            $i=1;
            $syms[] = FUNC1;
        }
        else {
            if( $n==2 && ctype_digit(substr($aData,0,2)) )
            $this->iCurrCharset = CODE128_C;
            elseif( $n>=4 && ctype_digit(substr($aData,0,4)) ) {
                $this->iCurrCharset = CODE128_C;
            }
            else {
                $this->iCurrCharset = $this->CharSetRequired($aData);
                if( $this->iCurrCharset == -1 )
                $this->iCurrCharset = $this->iDefaultStart;
            }
            $syms[] = $this->iStart[$this->iCurrCharset];
            $i=0;
        }
        while( $i < $n ) {
            switch( $this->iCurrCharset ) {
                case CODE128_A:
                    if( $this->SwitchToC($aData,$i,$syms) ) {
                        $this->iCurrCharset = CODE128_C;
                    }
                    else if( $this->CharSetRequired(substr($aData,$i,1))==CODE128_B ) {
                        if( $this->CharSetRequired(substr($aData,$i+1))==CODE128_B ) {
                            $this->iCurrCharset = CODE128_B;
                            $syms[] = $this->iCharsets[$this->iCurrCharset];
                        }
                        else {
                            $syms[] = SHIFT;
                            $syms[] = $this->EncodeSymbolAB(ord(substr($aData,$i,1)));
                            ++$i;
                        }
                    }
                    else {
                        $syms[] = $this->EncodeSymbolAB(ord(substr($aData,$i,1)));
                        ++$i;
                    }
                    break;
                case CODE128_B:
                    if( $this->SwitchToC($aData,$i,$syms) ) {
                        $this->iCurrCharset = CODE128_C;
                    }
                    elseif( $this->CharSetRequired(substr($aData,$i,1))==CODE128_A ) {
                        if( $this->CharSetRequired(substr($aData,$i+1))==CODE128_A ) {
                            $this->iCurrCharset = CODE128_A;
                            $syms[] = $this->iCharsets[$this->iCurrCharset];
                        }
                        else {
                            $syms[] = SHIFT;
                            $syms[] = $this->EncodeSymbolAB(ord(substr($aData,$i,1)));
                            ++$i;
                        }
                    }
                    else {
                        $syms[] = $this->EncodeSymbolAB(ord(substr($aData,$i,1)));
                        ++$i;
                    }
                    break;
                case CODE128_C:
                    if( substr($aData,$i,1) == chr(EA_FUNC1) ) {
                        $syms[] = FUNC1;
                        $i++;
                    }
                    elseif( substr($aData,$i,1) == chr(EA_FUNC2) ) {
                        $syms[] = FUNC2;
                        $i++;
                    }
                    elseif( substr($aData,$i,1) == chr(EA_FUNC3) ) {
                        $syms[] = FUNC3;
                        $i++;
                    }
                    elseif( $i < $n-1 && ctype_digit(substr($aData,$i,2)) ) {
                        $syms[] = (substr($aData,$i,1)-'0')*10 + (substr($aData,$i+1,1)-'0');
                        $i += 2;
                    }
                    else {
                        $tmp = $this->CharSetRequired(substr($aData,$i));
                        $this->iCurrCharset = $tmp==-1? $this->iDefaultStart : $tmp;
                        $syms[] = $this->iCharsets[$this->iCurrCharset];
                    }
                    break;
            }
        }

        // End guard
        $syms[] = ENDGUARD;

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        // We don't print function characters
        $e->iData = str_replace(chr(EA_FUNC1),'',$aData);
        $e->iData = str_replace(chr(EA_FUNC2),'',$e->iData);
        $e->iData = str_replace(chr(EA_FUNC3),'',$e->iData);
        $e->iStrokeDataBelow=true;

        $n = count($syms);
        $checksum = $syms[0];
        for( $i=0; $i < $n-1; ++$i ) {
            $e->iBar[$i] = array($syms[$i],1,1,$this->iSymbols[$syms[$i]]);
            $checksum += $syms[$i]*$i;
        }
        $checksum %= 103;
        $e->iBar[$n-1] = array($checksum,1,1,$this->iSymbols[$checksum]);
        $e->iBar[$n] = array($syms[$n-1],1,1,$this->iSymbols[$syms[$n-1]]);
        return $e;
    }
}

//----------------------------------------------------------------
// BarcodeEncode_EAN128
//
//----------------------------------------------------------------
class BarcodeEncode_EAN128 extends BarcodeEncode_CODE128 {
    function __construct() {
        parent::__construct();
        $this->iName = 'EAN-128';
        $this->iUseTilde = true;
    }

    function Enc($aData) {
        // EAN128 must start with a FUNC1 so in case this hasnt been specified
        // we add this as a prefix for the data
        if( ord(substr($aData,0,1)) != EA_FUNC1 ) {
            $aData = chr(EA_FUNC1).$aData;
        }
        return parent::Enc($aData);
    }

    // Find out maximum allowed data length as specified
    // by the application identifier.
    function GetDataLength($aData) {
        // Return positive indicates just digits
        // Return negative indicates alphanum allowed
        // Return length including application identifier length

        $c1 = substr($aData,0,1);
        $c2 = substr($aData,1,1);
        $c3 = substr($aData,2,1);

        if( $c1 == '0' ) {
            if( $c2 == '0' ) return 18+2;
            if( $c2 == '1' || $c2 == '2') return 14+2;
        }

        if( $c1 == '1' ) {
            if( $c2 == '0' ) return -(20+2);
            if( $c2 == '1' || $c2 == '3' || $c2 == '5' || $c2 == '7' )
            return 6+2;
        }

        if( $c1 == '2' ) {
            if( $c2 == '0' ) return 2+2;
            if( $c2 == '1' ) return -(20+2);
            if( $c2 == '2' ) return -(29+2);
            if( $c2 == '3' ) return -(29+3);
            if( $c3 == '0' && ($c2 == '4' || $c2 == '5') )
            return -(30+3);
        }

        if( $c1 == '3' && ($c2 >= '1' && $c2 <= '6') && ($c3 >= '0' && $c3 <= '9') )
        return 6+4;

        if( $c1 == '3' && $c2 == '7' )
        return 8+2;

        if( $c1 == '4' ) {
            if( $c2=='0' && $c3=='0' ) return 29+3;
            if( $c2=='1' && ($c3>='0' || $c3<='2') ) return 13+3;
            if( $c2=='2' && $c3=='0' ) return -(9+3);
            if( $c2=='2' && $c3=='1' ) return -(12+3);
        }

        if( $c1 == '8' && $c2 == '0' && $c3 == '0' ) {
            $c4 = substr($aData,3,1);
            if( $c4 == '1' ) return 14+4;
            if( $c4 == '2' ) return -(20+4);
            if( $c4 == '3' || $c4 == '4' ) return -(30+4);
            if( $c4 == '5' ) return 6+4;
            if( $c2 == '1' || $c2 == '0' ) {
                if( $c4 == '0' ) return 6+4;
                if( $c4 == '1' ) return 10+4;
                if( $c4 == '2' ) return 2+4;
            }
        }

        // Company internal. The standard only allows up to 30 digits but some
        // companies igire this an uses codes with longer sequence so we allow
        // up to 50 digits
        if( $c1 == '9' && ($c2 >= '0' && $c2 <= '9') )
        return -(50+2);

        return false;
    }

    function ValidateChunk($aChunk) {
        $maxlen = $this->GetDataLength($aChunk);
        if( $maxlen > 0 ) {
            // Make sure data is purely digits
            if( ctype_digit($aChunk) == false )
            return false;
        }
        if( strlen($aChunk) > abs($maxlen)+1 ) {
            return false;
        }
        return true;
    }

    function Validate($aData) {
        // First code must be CODE 128 FUNC1 to indicate EAN 128
        if( ord(substr($aData,0,1)) != EA_FUNC1 ) {
            $aData = chr(EA_FUNC1).$aData;
        }
        // Now find all concatenated codes (separated by a EA_FUNC1 character)
        $n=strlen($aData);$i=0;
        while($i< $n) {
            if( $aData[$i]==chr(EA_FUNC1) ) {
                // Find the end
                $end=strpos($aData,chr(EA_FUNC1),$i+1);
                if( $end === false ) {
                    return $this->ValidateChunk(substr($aData,$i+1));
                }
                else {
                    if( ! $this->ValidateChunk(substr($aData,$i+1,$end-1-$i)) )
                    return false;
                }
                $i = $end;
            }
        }

        return true;

    }
}


//----------------------------------------------------------------
// BarcodeEncode_CODE25 (a.k.a 2 of 5 )
//
//----------------------------------------------------------------
class BarcodeEncode_CODE25 extends BarcodeEncode {
    private $iSymbols = array(
 '11331','31113','13113','33111','11313', /* 0-4 */
 '31311','13311','11133','31131','13131'); /* 5-9 */
    private $iGuard = array(
    array('212111','21112'), /* Standard */
    array('1111','311') );  /* Interleaved */

    function __construct() {
        parent::__construct();
        $this->iName = 'INDUSTRIAL 2 OF 5';
    }

    function GetChecksum($aData) {
        $n=strlen($aData);
        $sum=0;
        for( $i=0; $i < $n; ++$i ) {
            $c = substr($aData,$n-1-$i,1);
            if( ($i & 1) == 0 )
            $sum += ($c+0)*3;
            else
            $sum += ($c+0);
        }
        $d = ($sum%10);
        if( $d > 0 )
        return 10-$d;
        else
        return 0;
    }

    function Enc($aData) {
        return $this->_Enc($aData,false);
    }

    function EncI($aData) {
        return $this->_Enc($aData,true);
    }

    function _Enc($aData,$aInterleave=false) {
        parent::Enc($aData);
        if( $this->iUseChecksum )
        $aData .= $this->GetChecksum($aData);
        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = $aData;
        $e->iInterCharModuleSpace = false;
        $e->iStrokeDataBelow=true;
        if( $this->iUseChecksum )
        $e->iInfo = "Checkdigit=".substr($aData,strlen($aData)-1,1);

        $guardtype = $aInterleave ? 1 : 0;

        $e->iBar[0] = array(0,1,1,$this->iGuard[$guardtype][0]);

        $n = strlen($aData);
        $bpos=1;
        $i=0;
        while( $i < $n ) {
            $c = substr($aData,$i,1);
            $s = $this->iSymbols[$c+0];
            $ms = '';
            if( $aInterleave ) {
                ++$i;
                $c2 = substr($aData,$i,1);
                $s2 = $this->iSymbols[$c2+0];
                for($j=0; $j < 5; ++$j) {
                    $ms .=  substr($s,$j,1).substr($s2,$j,1);
                }
            }
            else {
                for($j=0; $j < 5; ++$j) {
                    $ms .=  substr($s,$j,1).'1';
                }
            }
            $e->iBar[$bpos] = array($c,1,1,$ms);
            $bpos++;
            ++$i;
        }

        $e->iBar[$bpos] = array(0,1,1,$this->iGuard[$guardtype][1]);

        return $e;
    }

    function Validate($aData) {
        return ctype_digit($aData);
    }
}

//----------------------------------------------------------------
// BarcodeEncode_CODEI25 (a.k.a Interleaved 2 of 5 )
//
//----------------------------------------------------------------
class BarcodeEncode_CODEI25 extends BarcodeEncode_CODE25 {
    function __construct() {
        parent::__construct();
        $this->iName = 'INTERLEAVED 2 OF 5';
    }

    function Enc($aData) {
        return parent::EncI($aData);
    }

    function Validate($aData) {
        // Even number of digits
        return (((strlen($aData)&1)==0 && !$this->iUseChecksum) ||
        ((strlen($aData)&1)==1 && $this->iUseChecksum))
        && ctype_digit($aData);
    }
}

//----------------------------------------------------------------
// BarcodeEncode_CODABAR
//
//----------------------------------------------------------------
class BarcodeEncode_CODABAR extends BarcodeEncode {
    private $iSymbols = array(
 '1111122', '1111221', '1112112', '2211111',
 '1121121', '2111121', '1211112', '1211211',
 '1221111', '2112111', '1112211', '1122111',
 '2111212', '2121112', '2121211', '1122222',
 '1122121', '1212112', '1112122', '1112221',
    );
    private $iStartStop = array('A','B','C','D');
    private $iSymbolPos = '0123456789-$:/.+ABCD';

    function __construct() {
        parent::__construct();
        $this->iName = 'CODABAR';
    }

    function Enc($aData) {
        parent::Enc($aData);

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iInterCharModuleSpace = true;
        $e->iStrokeDataBelow=true;

        // Add start/stop char if they are not present
        // (The validation has made sure there are either
        // none or both)
        $c=substr($aData,0,1);
        if( in_array($c,$this->iStartStop) == false )
        $aData = 'A'.$aData.'B';

        $e->iData = $aData;

        $n = strlen($aData);
        $bpos=0; $i=0;
        while( $i < $n ) {
            $c = substr($aData,$i,1);
            $p = strpos($this->iSymbolPos,$c);
            $e->iBar[$bpos] = array($c,1,1,$this->iSymbols[$p]);
            $bpos++;
            ++$i;
        }
        return $e;
    }


    function Validate($aData) {
        // Check that all chars in $aData are available
        $n=strlen($aData);
        $sf = 0;
        for( $i=0; $i < $n; ++$i ) {
            $c = substr($aData,$i,1);
            if( strpos($this->iSymbolPos,$c) === false )
            return false;
            // Only allow start/stop digits at beginning or end
            if( in_array($c,$this->iStartStop) ) {
                $sf++;
                if( $i>0 && $i<$n-1 )
                return false;
            }
        }
        if( $sf > 0 && $sf != 2 )
        return false;
        return true;
    }
}


//----------------------------------------------------------------
// BarcodeEncode_CODE11
//
//----------------------------------------------------------------
class BarcodeEncode_CODE11 extends BarcodeEncode {
    private $iSymbols = array(
 '11112','21112','12112','22111','11212',
 '21211','12211','11122','21121','21111',
 '11211' );

    private $iSymbolPos = '0123456789-';
    private $iGuard = '11221';

    function __construct() {
        parent::__construct();
        $this->iName = 'CODE 11';
    }

    function GetChecksum($aData) {
        // First determine the 'C' checksum
        $sum=0;
        $n=strlen($aData);
        $weight=1;
        for($i=0; $i < $n; ++$i) {
            $c=substr($aData,$n-1-$i,1);
            if( $c=='-' ) $c=10;
            $sum += $weight*$c;
            $weight++;
            if( $weight > 10 ) $weight=1;
        }
        $chk = substr($this->iSymbolPos,($sum % 11),1);

        // if( $n >= 9 ) {
        $aData .= $chk;
        // Add 'K' checksum
        $sum=0;
        ++$n;
        $weight=1;
        for($i=0; $i < $n; ++$i) {
            $c=substr($aData,$n-1-$i,1);
            if( $c=='-' ) $c=10;
            $sum += $weight*$c;
            $weight++;
            if( $weight > 9 ) $weight=1;
        }
        $chk .= substr($this->iSymbolPos,($sum % 11),1);
        // }

        return $chk;
    }


    function Enc($aData) {
        parent::Enc($aData);
        if( $this->iUseChecksum )
        $aData .= $this->GetChecksum($aData);

        // Now encode the data
        $e = new BarcodePrintSpec();
        $e->iEncoding = $this->GetName();
        $e->iData = $aData;
        $e->iInterCharModuleSpace = true;
        $e->iStrokeDataBelow=true;

        $e->iBar[0] = array(0,1,1,$this->iGuard);

        $n = strlen($aData);
        $bpos=1; $i=0;
        while( $i < $n ) {
            $c = substr($aData,$i,1);
            $p = strpos($this->iSymbolPos,$c);
            $e->iBar[$bpos] = array($c,1,1,$this->iSymbols[$p]);
            $bpos++;
            ++$i;
        }
        $e->iBar[$bpos] = array(0,1,1,$this->iGuard);
        return $e;
    }


    function Validate($aData) {
        // Check that all chars in $aData are available
        $n=strlen($aData);
        for( $i=0; $i < $n; ++$i ) {
            if( strpos($this->iSymbolPos,substr($aData,$i,1))===false )
            return false;
        }
        return true;
    }
}



//----------------------------------------------------------------
// BarcodeEncode_CODE93
//
//----------------------------------------------------------------
class BarcodeEncode_CODE93 extends BarcodeEncode {
    function __construct() {
        JpGraphError::RaiseL(1009);//('Encoding using CODE 93 is not yet supported.');
        parent::__construct();
        $this->iName = 'CODE-93';
    }

    function Enc($aData) {
        parent::Enc($aData);
    }

    function Validate($aData) {
        return false;
    }
}

//----------------------------------------------------------------
// BarcodeEncode_POSTNET
//
//----------------------------------------------------------------
class BarcodeEncode_POSTNET extends BarcodeEncode {
    function __construct() {
        JpGraphError::RaiseL(1010);
        //'Encoding using POSTNET is not yet supported.');
        parent::__construct();
        $this->iName = 'POSTNET';
    }

    function Enc($aData) {
        parent::Enc($aData);
    }

    function Validate($aData) {
        return false;
    }
}


//--------------------------------------------------------------
// BarcodePrintSpec
// All encodings gets translated to this uniform format which
// is captured by this class. This information is then sent to
// the backend which is responsible for the actual generation of
// the image.
//--------------------------------------------------------------
class BarcodePrintSpec {

    // Length of long bars as fraction of whole barcode height
    public $iLongBarFraction=0.95;
    // Width (in pixels) of each module
    public $iModuleWidth=1;
    // Encoding used
    public $iEncoding;
    // Original data
    public $iData;
    // String to print to left of barcode (small font)
    public $iLeftData;
    // String to print to the right of barcode (small font)
    public $iRightData;
    // Left margin (number of modules) before left guard
    public $iLeftMargin=15;
    // Right margin (number of modules) after right guard
    public $iRightMargin=15;
    // Bar specification as an array
    // array(array(ENCODED_CHAR,PARITY,LENGTH,ENCODING),...)
    // ENCODED_CHAR = ASCII representation of encoded char
    //                For special chracters like left and right
    //                guard this is coded as 0
    // PARITY = 0-Start with space, 1-Start with black
    // HIDECHAR = 1-Don't print data underneath, 0-Make bar short and print data char
    // ENCODING = Module width encoding, e.g. 2311 (UPC-A)
    //
    public $iBar;
    // Small and large font size
    public $iFontSizeLarge=12, $iFontSizeSmall=10;
    // Some code need an extra inter character module space
    // which isn't encoded in the symbols
    public $iInterCharModuleSpace=false;
    // Stroke data over or under bar centered
    public $iStrokeDataAbove=false, $iStrokeDataBelow=false;
    // Custom information string that can be used to display
    // debug information for a specific encoding
    public $iInfo;

    function __construct() {
        $this->iBar = array();
    }
}

//--------------------------------------------------------------
// BackendFactory
//
//--------------------------------------------------------------
class BackendFactory {
    static function Create($aBackend,$aEncoder,$aReport=false) {
        $backends = array('IMAGE','PS');
        if( array_search($aBackend,$backends) === false ) {
            if( $aReport)
            	JpGraphError::RaiseL(1011,$aBackend);
            	//('Non supported barcode backend for type '.$aBackend);
            else
            	return false;
        }
        $b = 'OutputBackend_'.$aBackend;
        return new $b($aEncoder);
    }
}

class OutputBackend {
    protected $iModuleWidth=1;
    protected $iUseChecksum=0;
    protected $iNoHumanText=false;
    protected $iDataBelowMargin = 6;
    protected $iFontFam=FF_FONT2,$iFontStyle=FS_NORMAL,$iFontSize=10;
    protected $iSmallFontFam=FF_FONT1,$iSmallFontStyle=FS_NORMAL,$iSmallFontSize=8;
    protected $iColor='black',$iBkgColor='white';
    protected $iVertical=false;
    protected $iShowFrame=false;
    protected $iDebugBackground = false;
    protected $iHeight=70;
    protected $iScale=1;
    protected $iEncoder=null;
    protected $iAdjLeftMargin=15, $iAdjRightMargin=15;
    protected $iBottomMargin = 10;
    protected $iTopMargin = 3;
    protected $iFrameColor = 'black';
    protected $iHumanTxt = '';

    function AdjustSpec(&$aSpec) {
        $aSpec->iModuleWidth = $this->iModuleWidth;
        if( $this->iNoHumanText ) {
            $aSpec->iStrokeDataBelow=false;
            $aSpec->iLeftData = '';
            $aSpec->iRightData = '';
        }
        $aSpec->iLeftMargin = $this->iAdjLeftMargin;
        $aSpec->iRightMargin = $this->iAdjRightMargin;
    }

    function SetMargins($aLeft,$aRight,$aTop=3,$aBottom=10) {
        $this->iAdjLeftMargin = $aLeft;
        $this->iAdjRightMargin = $aRight;
        $this->iTopMargin = $aTop;
        $this->iBottomMargin = $aBottom;
    }

    function SetVertical($aFlg=true) {
        $this->iVertical=$aFlg;
    }


    function SetHumanText($aTxt) {
        // The human readable text string could be other from the actual
        // encoded data. This is usefull to include spaces and paranthesis
        // to make the string more easily to read but should not be encoded.
        $this->iHumanTxt = $aTxt;
    }

    function SetScale($aScale) {
        $this->iScale = $aScale;
    }

    function SetModuleWidth($aWidth) {
        $this->iModuleWidth = $aWidth;
    }

    function SetHeight($aHeight) {
        $this->iHeight = $aHeight;
    }

    function HideText($aFlg=true) {
        $this->iNoHumanText = $aFlg;
    }

    function NoText($aFlg=true) {
        $this->iNoHumanText = $aFlg;
    }

    function ShowFrame($aFlg=true) {
        $this->iShowFrame=$aFlg;
    }

    function SetFrameColor($aColor) {
        $this->iFrameColor = $aColor;
    }

    function AddChecksum($aFlag=true) {
        $this->iUseChecksum = $aFlag;
    }

    function SetFont($aFontFam,$aFontStyle,$aFontSize) {
        $this->iFontFam   = $aFontFam ;
        $this->iFontStyle = $aFontStyle;
        $this->iFontSize  = $aFontSize;
    }

    function SetSmallFont($aFontFam,$aFontStyle,$aFontSize) {
        $this->iSmallFontFam   = $aFontFam ;
        $this->iSmallFontStyle = $aFontStyle;
        $this->iSmallFontSize  = $aFontSize;
    }

    function SetColor($aColor,$aBkgColor) {
        $this->iColor = $aColor;
        $this->iBkgColor = $aBkgColor;
    }
}


//--------------------------------------------------------------
// OutputBackend_IMAGE
//
//--------------------------------------------------------------
class OutputBackend_IMAGE extends OutputBackend {
    private $iImgFormat='png';
    function __construct($aEncoder) {
        $this->iEncoder = $aEncoder;
    }

    function SetImgFormat($aFormat) {
        $this->iImgFormat=$aFormat;
    }

    function SetModuleWidth($aWidth) {
        $this->iModuleWidth = $aWidth;
    }

    function Rotate($src_img, $degrees = 90) {
        if( !function_exists('imagerotate') )
        return $src_img;
        $degrees %= 360;
        if ($degrees == 0) {
            $dst_img = $src_img;
        } elseif ($degrees == 180) {
            $dst_img = imagerotate($src_img, $degrees, 0);
        } else {
            $width = imagesx($src_img);
            $height = imagesy($src_img);
            if ($width > $height) {
                $size = $width;
            } else {
                $size = $height;
            }
            $dst_img = imagecreatetruecolor($size, $size);
            imagecopy($dst_img, $src_img, 0, 0, 0, 0, $width, $height);
            $dst_img = imagerotate($dst_img, $degrees, 0);
            $src_img = $dst_img;
            $dst_img = imagecreatetruecolor($height, $width);
            if ((($degrees == 90) && ($width > $height)) || (($degrees == 270) && ($width < $height))) {
                imagecopy($dst_img, $src_img, 0, 0, 0, 0, $size, $size);
            }
            if ((($degrees == 270) && ($width > $height)) || (($degrees == 90) && ($width < $height))) {
                imagecopy($dst_img, $src_img, 0, 0, $size - $height, $size - $width, $size, $size);
            }
        }
        return $dst_img;
    }

    function Stroke($aData,$aFile='',$aShowDetails=false,$aShowEncodingDetails=false) {
        $textmargin=5;

        $this->iEncoder->AddChecksum($this->iUseChecksum);
        $spec = $this->iEncoder->Enc($aData);
        $this->AdjustSpec($spec);

        // Set the deafult font when no font has been specified
        if( $this->iFontFam == -1 ) {

            if( $this->iModuleWidth > 1 ) {
                $this->iFontFam = FF_FONT2;
                $this->iFontStyle = FS_BOLD;
            }
            else {
                $this->iFontFam = FF_FONT1;
                $this->iFontStyle = FS_BOLD;
            }
        }

        $s = '';
        $n = count($spec->iBar);

        $g = new CanvasGraph(0,0); // Dummy graph context
        $g->img->SetImgFormat($this->iImgFormat);
        if( $aShowDetails ) {
            $s = $spec->iEncoding."\n";
            $s .= 'Data: '.$spec->iData."\n";
            if( $spec->iInfo != '' )
            $s .= 'Info: '.$spec->iInfo."\n";
        }
        $w = $spec->iModuleWidth;


        // Calculate total width
        $totwidth=$spec->iLeftMargin*$w;
        $n = count($spec->iBar);
        for( $i=0; $i < $n; ++$i ) {
            $b = $spec->iBar[$i];
            $bn = strlen($b[3]);
            for( $j=0; $j < $bn; ++$j ) {
                $wb = substr($b[3],$j,1)*$w;
                $totwidth += $wb;
            }
        }
        if( $spec->iInterCharModuleSpace )
        $totwidth += ($n-2)*$w;
        $totwidth += $spec->iRightMargin*$w+1;

        $height = $this->iHeight;

        if( $aShowDetails ) {
            $g->img->SetFont(FF_FONT2);
            $height += $g->img->GetTextHeight($s);
        }

        $g->img->SetFont($this->iFontFam, $this->iFontStyle, $this->iFontSize);
        $th = $g->img->GetTextHeight($spec->iData);
        if( $spec->iStrokeDataBelow ) {
            $height += $th + $this->iDataBelowMargin;
        }

        // Standard dictates that height must be at least 15% of width
        if( $height < round(0.15*($totwidth-$spec->iRightMargin*$w-$spec->iLeftMargin*$w)) ) {
            $height = round(0.15*$totwidth);
        }

        $g->img->SetFont(FF_FONT2);
        $tw = 2*$textmargin + $g->img->GetTextWidth($s);
        $width = $totwidth;
        if( $width < $tw )
        $width = $tw;

        if( $aShowEncodingDetails ) {
            $g->img->SetFont(FF_FONT2);
            $height += $n*$g->img->GetTextHeight('0');
            // Make the width enough for debug info
            $width = max(300,$totwidth);
        }

        $g = new CanvasGraph($width,$height);
        $g->img->SetImgFormat($this->iImgFormat);
        $g->SetMarginColor('white');
        if( $this->iShowFrame ) {
            $g->frame_color = $this->iFrameColor;
            $g->InitFrame();
        }
        $g->img->SetColor('black');
        $x = $w*$spec->iLeftMargin;
        $ystart = $this->iTopMargin;
        $yend = $height-$this->iBottomMargin-1;
        if( $aShowDetails )
        $ystart += $g->img->GetTextHeight($s);

        if( $aShowEncodingDetails ) {
            $g->img->SetFont(FF_FONT2);
            $ystart += $n*$g->img->GetTextHeight('0');
        }

        if( $spec->iStrokeDataBelow ) {
            $yend -= ($th + $this->iDataBelowMargin);
        }

        $inunder=false;
        $under_s = '';
        $under_x = 0;
        for( $i=0; $i < $n; ++$i ) {
            $b = $spec->iBar[$i];
            if( $aShowEncodingDetails )
            $s .= sprintf("%02d",$i)." : $b[0], $b[1], $b[2], $b[3]\n";
            $bn = strlen($b[3]);

            if( $b[2] == 0 && !$this->iNoHumanText ) {
                if( !$inunder ) {
                    $inunder = true;
                    $under_x = $x;
                    $under_s = $b[0];
                }
                else
                $under_s .= $b[0];
            }
            else {
                if( $inunder ) {
                    $inunder = false;
                    if( $under_s != '' ) {
                        $t = new Text($under_s,($under_x+$x-1)/2,$yend-$th/1.3);
                        $t->SetFont($this->iFontFam,$this->iFontStyle,$this->iFontSize);
                        $t->Align('center','top');
                        $t->Stroke($g->img);
                    }
                }
            }

            $startx=$x;
            for( $j=0; $j < $bn; ++$j ) {
                $wb = substr($b[3],$j,1)*$w;
                if( $j % 2 == $b[1] ) {
                    $g->img->SetColor($this->iBkgColor);
                }
                else {
                    $g->img->SetColor($this->iColor);
                }
                if( $b[2] == 1 || $this->iNoHumanText ) {
                    // Long bar
                    $g->img->FilledRectangle($x,$ystart,$x+$wb-1,$yend);
                }
                else {
                    // Short bar add code below
                    $g->img->FilledRectangle($x,$ystart,$x+$wb-1,$yend-$th);
                }
                $x += $wb;
            }
            if( $this->iDebugBackground ) {
                $g->SetAlphaBlending();
                if( ($i & 1) == 0 )
                $g->img->SetColor('lightblue@0.5');
                else
                $g->img->SetColor('yellow@0.5');
                $g->img->FilledRectangle($startx,$ystart-2,$x,$yend);
            }
            if( $spec->iInterCharModuleSpace )
            $x += $w;
        }

        $g->img->SetColor($this->iColor);

        // Left data
        if( !($spec->iLeftData === '') ) {
            $g->img->SetTextAlign('right','top');
            $g->img->SetFont($this->iSmallFontFam,$this->iSmallFontStyle,
            $this->iSmallFontSize);
            $g->img->StrokeText(($w*$spec->iLeftMargin)-3,$yend-$th,$spec->iLeftData);
        }

        // Right data
        if( !($spec->iRightData === '') ) {
            $g->img->SetTextAlign('left','top');
            $g->img->SetFont($this->iSmallFontFam,$this->iSmallFontStyle,
            $this->iSmallFontSize);
            $g->img->StrokeText($x+3,$yend-$th,$spec->iRightData);
        }

        if( $spec->iStrokeDataBelow ) {
            // Center data underneath
            $y = $yend+$this->iDataBelowMargin;
            $bw = $totwidth - $spec->iLeftMargin*$w - $spec->iRightMargin*$w;
            $x = $spec->iLeftMargin*$w + floor($bw/2);
            $g->img->SetTextAlign('center','top');
            $g->img->SetFont($this->iFontFam, $this->iFontStyle, $this->iFontSize);
            if( $this->iHumanTxt !== '' )
            $g->img->StrokeText($x,$y,$this->iHumanTxt);
            else
            $g->img->StrokeText($x,$y,$spec->iData);

        }

        if( $aShowDetails ) {
            $g->img->SetColor('navy');
            $g->img->SetTextAlign('left','top');
            $g->img->SetFont(FF_FONT2);
            $g->img->StrokeText($textmargin,$this->iTopMargin,$s);
        }


        if( ADD_DEMOTXT===true ) {

            // ===========
            // Add ** DEMO ** GUARD TEXT
            // ============

            $t = new Text("<<DEMO>>",$totwidth/2,$ystart);
            if( $this->iModuleWidth > 1 ) {
                if( $this->iModuleWidth > 4 ) {
                    $t->SetFont(FF_ARIAL,FS_BOLD,32);
                    $step = 140;
                    $yadj = 50;
                }
                else {
                    $t->SetFont(FF_ARIAL,FS_BOLD,24);
                    $step = 110;
                    $yadj = 40;
                }
            }
            else {
                $t->SetFont(FF_ARIAL,FS_BOLD,18);
                $step = 80;
                $yadj = 30;
            }
            $t->SetColor('red@0.4');
            $t->Align('center','center');
            $t->SetAngle(-25);
            $n = ceil($totwidth/$step);
            for( $i=0; $i < $n; ++$i ) {
                $t->SetPos(-30+$i*$step,($yend-$ystart)/2-$yadj);
                $t->Stroke($g->img);
            }

            // ================

        }


        if( $this->iVertical )
        $g->img->img = $this->Rotate($g->img->img,90);

        if( $this->iScale != 1 ) {
            $nwidth = round($width*$this->iScale);
            $nheight = round($height*$this->iScale);
            if( $this->iVertical ) {
                $tmp = $height; $height = $width; $width=$tmp;
                $tmp = $nheight; $nheight = $nwidth; $nwidth=$tmp;
            }
            $img = @imagecreatetruecolor($nwidth, $nheight);
            if( $img ) {
                imagealphablending($img,true);
                imagecopyresampled($img,$g->img->img,0,0,0,0,$nwidth,$nheight,$width,$height);
                $g->img->CreateImgCanvas($nwidth,$nheight);
                $g->img->img = $img;
            }
            else
            return false;
        }
        return $g->Stroke($aFile);
    }
}

//--------------------------------------------------------------
// OutputBackend_PS
//
//--------------------------------------------------------------
class OutputBackend_PS extends OutputBackend {
    private $iEPS=false;
    private $ixoffset=0, $iyoffset=10;

    function __construct($aEncoder) {
        $this->iEncoder = $aEncoder;
        $this->iFontSize = 12;
        $this->iSmallFontSize = 10;
        $this->iModuleWidth = 1.1;
        $this->iBottomMargin=6;
    }

    function SetEPS($aFlg=true) {
        $this->iEPS = $aFlg;
    }

    function Stroke($aData,$aFile='',$aShowDetails=false,$aShowEncodingDetails=false) {

        if( $this->iModuleWidth < 0.9 ) {
            $this->iFontSize=9;
            $this->iSmallFontSize=9;
        }

        $this->iEncoder->AddChecksum($this->iUseChecksum);
        $spec = $this->iEncoder->Enc($aData);
        $this->AdjustSpec($spec);

        $s = '';
        $n = count($spec->iBar);
        $w = $this->iModuleWidth;

        // Calculate total width
        $totwidth=$spec->iLeftMargin*$w;
        $n = count($spec->iBar);
        for( $i=0; $i < $n; ++$i ) {
            $b = $spec->iBar[$i];
            $bn = strlen($b[3]);
            for( $j=0; $j < $bn; ++$j ) {
                $wb = substr($b[3],$j,1)*$w;
                $totwidth += $wb;
            }
        }
        if( $spec->iInterCharModuleSpace )
        $totwidth += ($n-2)*$w;
        $totwidth += $spec->iRightMargin*$w;
        $height = $this->iHeight;

        // Hight of bars must be at least 20% of width
        //if( $height < round(0.2*$totwidth) ) {
        //    $height = round(0.2*$totwidth);
        //}

        // Start X-value
        $startx = $w*$spec->iLeftMargin + $this->ixoffset;
        $ystart = $height + $this->iyoffset;

        if( $spec->iStrokeDataBelow ) {
            $ystart += $this->iFontSize;
            $height += 3;
        }
        elseif( $this->iNoHumanText ) $height += 3;

        $inunder=false;
        $under_s = '';
        $psbar="";
        $pst='';
        $x = $startx;
        $details = "%%Symbology specific information:\n%%".$spec->iInfo."\n";
        $details .= "\n%%Encoding for individual charcters in choosen symbology\n";
        $details .= "%% # : Char, Length type, Start with bar/space, encoding\n";

        for( $i=0; $i < $n; ++$i ) {
            $b = $spec->iBar[$i];
            $bn = strlen($b[3]);

            if( $aShowEncodingDetails )
            $details .= "%% ".sprintf("%02d",$i)." : $b[0], $b[1], $b[2], $b[3]\n";

            if( $b[2] == 0 && !$this->iNoHumanText ) {
                if( !$inunder ) {
                    $inunder = true;
                    $under_x = $x;
                    $under_s = $b[0];
                }
                else
                $under_s .= $b[0];
            }
            else {
                if( $inunder ) {
                    $inunder = false;
                    if( $under_s != '' ) {
                        $pst .= '[('.$under_s.') '.(($under_x+$x)/2).' ]';
                    }
                }
            }

            for( $j=0; $j < $bn; ++$j ) {
                $wb = substr($b[3],$j,1)*$w;
                if( $j % 2 != $b[1] ) {
                    $x += $wb/2;
                    if( $b[2] == 1 || $this->iNoHumanText ) {
                        $psbar .= " [".round($height-$this->iFontSize/2)." $x $wb] ";
                    }
                    else {
                        $psbar .= " [".($height-$this->iFontSize)." $x $wb] ";
                    }
                    $x += $wb/2 ;
                }
                else
                $x += $wb;
            }
            if( $spec->iInterCharModuleSpace )
            $x += $w;
            $psbar .= "\n";
        }

        $pstsmall = "";
        if( $spec->iLeftData != "" )
        $pstsmall .= "[($spec->iLeftData) ".($startx-2)."]";
        if( $spec->iRightData != "" )
        $pstsmall .= "[($spec->iRightData) ".($x+5)."]";

        if( $spec->iStrokeDataBelow ) {
            $barwidth = $totwidth - $spec->iLeftMargin*$w - $spec->iRightMargin*$w;
            $x = $spec->iLeftMargin*$w + floor($barwidth/2);
            $pst .= "[($spec->iData) $x ]";
        }


        $ps =
        ($this->iEPS ? "%!PS-Adobe-3.0 EPSF-3.0\n" : "%!PS-Adobe-3.0\n" ) .
     "%%Title: Barcode \"$spec->iData\", encoding: \"$spec->iEncoding\"\n".
     "%%Creator: JpGraph Barcode http://jpgraph.net/\n".
     "%%CreationDate: ".date("D j M H:i:s Y",time())."\n";

        if( $this->iEPS ) {
            if( $this->iVertical )
            $ps .= "%%BoundingBox: 0 0 $ystart $totwidth \n";
            else
            $ps .= "%%BoundingBox: 0 0 $totwidth $ystart\n";
        }
        else
        $ps .= "%%DocumentPaperSizes: A4\n";

        $ps .=
     "%%EndComments\n".
     "%%BeginProlog\n".
     "%%EndProlog\n\n".
     "%%Page: 1 1\n\n".
     "%%Data: \"$spec->iData\"\n".
     "%%Symbology: \"$spec->iEncoding\"\n".
     "%%Module width: $this->iModuleWidth pt\n\n";

        if( $aShowEncodingDetails )
        $ps .= $details."\n";

        if( $this->iScale != 1 ) {
            $ps .=
  "%%Scale barcode\n".
  "$this->iScale $this->iScale scale\n\n";
        }

        if( $this->iVertical ) {
            $ps .=
                "%%Rotate barcode 90 degrees\n".
            ($ystart+1)." 0 translate\n90 rotate\n\n";
        }

        $ps .=
     "%%Font definition for normal and small fonts\n".
     "/f {/Helvetica findfont $this->iFontSize scalefont setfont} def\n".
     "/fs {/Helvetica findfont $this->iSmallFontSize scalefont setfont} def\n\n".
     "%%Data for bars. Only black bars are defined. \n".
     "%%The figures are: [height xpos width]\n";

        $stroke_bars = "{{} forall setlinewidth $ystart moveto -1 mul 0 exch rlineto stroke} forall";

        $ps .= "[ \n".$psbar." ] ".$stroke_bars;

        $center_text = "{ {} forall 1 index stringwidth pop 2 div sub 1 $this->iyoffset add moveto show} forall\n";
        $right_text = "{ {} forall 1 index stringwidth pop sub 1 $this->iyoffset add moveto show} forall\n";
        $left_text = "{ {} forall 1 $this->iyoffset add moveto show} forall\n";

        if( !$this->iNoHumanText ) {
            $ps .= "\n\n%%Readable text\nf\n[".$pst."]\n".$center_text;
            if( $pstsmall != "" )
            $ps .= "fs\n[".$pstsmall."]\n".$right_text;
        }
        $ps .= "\n\n%%End of barcode for \"$spec->iData\"\n";

        if( !$this->iEPS ) {
            $ps .= "\nshowpage\n";
        }
        $ps .= "\n%%Trailer\n";
        if( $aFile != '' ) {
            $fp = @fopen($aFile,'w');
            if( $fp ) {
                fwrite($fp,$ps);
                fclose($fp);
            }
            else
             	return false;
        }
        else {
        	return $ps;
        }
    }
}


// EOF

?>
