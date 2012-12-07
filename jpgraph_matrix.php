<?php
//=======================================================================
// File:        JPGRAPH_MATRIX.PHP
// Description: Matrix plots. A way to visualize matrix data by mapping
//              data values in the plot to a colormap
//
// Created:     2009-07-12
// Ver:         $Id: jpgraph_matrix.php 1932 2010-04-17 10:24:29Z ljp $
//
// Copyright (c) Asial Corporation. All rights reserved.
//========================================================================
require_once('jpgraph_colormap.inc.php');
require_once('jpgraph_glayout_vh.inc.php');
require_once('jpgraph_meshinterpolate.inc.php');

// Internal debug flag
DEFINE('__DEBUG',false);

/**
 * Class MatrixGraph
 */
class MatrixGraph extends Graph {
	public $plots=array();

	/**
	 * @param $width
	 * @param $height
	 * @param $cachedName
	 * @param $timeout
	 * @param $inline
	 * @return unknown_type
	 */
	function __construct($width=300,$height=200,$cachedName="",$timeout=0,$inline=1) {
		parent::__construct($width,$height,$cachedName,$timeout,$inline);
		$this->SetColor('white');
		$this->title->SetFont(FF_VERDANA,FS_NORMAL,12);
		$this->title->SetMargin(8);
		$this->subtitle->SetFont(FF_VERDANA,FS_NORMAL,10);
		$this->subtitle->SetMargin(0);
		$this->subsubtitle->SetFont(FF_VERDANA,FS_NORMAL,8);
		$this->subsubtitle->SetMargin(0);
		$this->SetColor('lightgray:1.8');
	}


	function StrokeTexts() {
		if( $this->texts != null ) {
			$n = count($this->texts);
			for($i=0; $i < $n; ++$i ) {
				// Since Windrose graphs doesn't have any linear scale the position of
				// each icon has to be given as absolute coordinates
				$this->texts[$i]->Stroke($this->img);
			}
		}
	}

	function StrokeIcons() {
		if( $this->iIcons != null ) {
			$n = count($this->iIcons);
			for( $i=0; $i < $n; ++$i ) {
				// Since Windrose graphs doesn't have any linear scale the position of
				// each icon has to be given as absolute coordinates
				$this->iIcons[$i]->_Stroke($this->img);
			}
		}
	}

	function Add($aObj) {
		if( is_array($aObj) && count($aObj) > 0 ) {
			$cl = $aObj[0];
		}
		else {
			$cl = $aObj;
		}
		if( $cl instanceof Text ) {
			$this->AddText($aObj);
		}
        elseif( class_exists('IconPlot',false) && ($cl instanceof IconPlot) ) $this->AddIcon($aObj);
		elseif( ($cl instanceof MatrixPlot) || ($cl instanceof LayoutRect) || ($cl instanceof LayoutHor)) {
			if( is_array($aObj) ) {
				$this->plots = array_merge($this->plots,$aObj);
			}
			else
				$this->plots[] = $aObj;
		}
		else {
			JpgraphError::RaiseL(29206);
		}
	}

	function AddText($aTxt,$aToY2=false) {
		parent::AddText($aTxt);
	}

	function SetColor($c) {
		$this->SetMarginColor($c);
	}

    function GetCSIMareas() {
        $csim = '';

        if( !$this->iHasStroked ) {
            $this->Stroke(_CSIM_SPECIALFILE);
        }

        $csim = $this->title->GetCSIMAreas();
        $csim .= $this->subtitle->GetCSIMAreas();
        $csim .= $this->subsubtitle->GetCSIMAreas();
        $csim .= $this->legend->GetCSIMAreas();

		$n = count($this->plots);
		for( $i=0; $i < $n ; ++$i ) {
			$csim .= $this->plots[$i]->GetCSIMAreas();
		}

        return $csim;
    }

	function Stroke($aStrokeFileName="") {

		// If the filename is the predefined value = '_csim_special_'
		// we assume that the call to stroke only needs to do enough
		// to correctly generate the CSIM maps.
		// We use this variable to skip things we don't strictly need
		// to do to generate the image map to improve performance
		// as best we can. Therefore you will see a lot of tests !$_csim in the
		// code below.
		$_csim = ($aStrokeFileName===_CSIM_SPECIALFILE);

		// We need to know if we have stroked the plot in the
		// GetCSIMareas. Otherwise the CSIM hasn't been generated
		// and in the case of GetCSIM called before stroke to generate
		// CSIM without storing an image to disk GetCSIM must call Stroke.
		$this->iHasStroked = true;

        if( ! $_csim ) {
            if( $this->background_image != "" || $this->background_cflag != "" ) {
                $this->StrokeFrameBackground();
            }
            else {
                $this->StrokeFrame();
            }
            $this->StrokeBackgroundGrad();
        }
		// n holds number of plots
		$n = count($this->plots);
		for($i=0; $i < $n ; ++$i) {
			$this->plots[$i]->Stroke($this);
		}

		if( __DEBUG ) {
			$this->img->SetColor('red');
			$x = round($this->img->width/2);
			$y = round($this->img->height/2);
			$this->img->Line($x,0,$x,$this->img->height-1);
			$this->img->Line(0,$y,$this->img->width-1,$y);
		}

		$this->footer->Stroke($this->img);
		$this->StrokeIcons();
		$this->StrokeTexts();
		$this->StrokeTitles();

        if( !$_csim ) {

            // If the filename is given as the special "__handle"
            // then the image handler is returned and the image is NOT
            // streamed back
            if( $aStrokeFileName == _IMG_HANDLER ) {
                return $this->img->img;
            }
            else {
            // Finally stream the generated picture
                $this->cache->PutAndStream($this->img,$this->cache_name,$this->inline,
                    $aStrokeFileName);
            }
        }
	}
} // Class

/**
 * Class MatrixLegend
 */
class MatrixLegend {
	private $icolormap = null;
	private $iFontFamily = FF_ARIAL, $iFontStyle=FS_NORMAL, $iFontSize=10, $iFontColor='black';
	private $ilayout=0; // To the right
	private $imodwidth=15,$imodheight=2;
	private $iwidth=15,$iheight=0.45;
	private $iMin=0,$iMax=0;
	private $iLabelFormatString = '%.1f';
	private $ilabelmargin=10;
	public $imargin=25;
	private $buckets=array(),$nbuckets=0;
	private $iboxcolor='black',$iboxweight=1,$iboxstyle='solid',$ibox=true;
	public $ishow=true;

	/**
	 * @param ColorMap $aColorMap
	 * @return unknown_type
	 */
	function __construct(ColorMap $aColorMap) {
		$this->icolormap = $aColorMap;
	}
	/**
	 * @param $aFlg
	 * @return unknown_type
	 */
	function Show($aFlg=true) {
		$this->ishow = $aFlg;
	}
	/**
	 * @param $aWidth
	 * @param $aBucketHeight
	 * @return unknown_type
	 */
	function SetModuleSize($aBucketWidth,$aBucketHeight=5) {
		$this->imodwidth = $aBucketWidth;
		$this->imodheight = $aBucketHeight;
		$this->iwidth = -1;
		$this->iheight  = -1;
	}
	/**
	 * @param $aWidth
	 * @param $aHeight
	 * @return unknown_type
	 */
	function SetSize($aWidth,$aHeight) {
		$this->iwidth = $aWidth;
		$this->iheight = $aHeight;
	}
	/**
	 * @param $aMarg
	 * @return unknown_type
	 */
	function SetMargin($aMarg) {
		$this->imargin = $aMarg;
	}
	/**
	 * @param $aMarg
	 * @return unknown_type
	 */
	function SetLabelMargin($aMarg) {
		$this->ilabelmargin = $aMarg;
	}
	/**
	 * @param $aLayout
	 * @return unknown_type
	 */
	function SetLayout($aLayout) {
		$this->ilayout = $aLayout;
	}
	/**
	 * @param $aFamily
	 * @param $aStyle
	 * @param $aSize
	 * @return unknown_type
	 */
	function SetFont($aFamily,$aStyle,$aSize) {
		$this->iFontFamily = $aFamily;
		$this->iFontStyle = $aStyle;
		$this->iFontSize = $aSize;
	}

    function SetColor($aFontColor) {
        $this->iFontColor = $aFontColor;
    }

	/**
	 * @param $aStr
	 * @return unknown_type
	 */
	function SetFormatString($aStr) {
		$this->iLabelFormatString = $aStr;
	}
	/**
	 * @param $aMin
	 * @param $aMax
	 * @return unknown_type
	 */
	function SetMinMax($aMin,$aMax) {
		$this->iMin = $aMin;
		$this->iMax = $aMax;
	}
	/**
	 * @param $aColor
	 * @param $aWeight
	 * @param $aStyle
	 * @param $aFlg
	 * @return unknown_type
	 */
	function SetBox($aColor='black',$aWeight=1,$aStyle='solid',$aFlg=true) {
		$this->iboxcolor = $aColor;
		$this->iboxweight = $aWeight;
		$this->iboxstyle = $aStyle;
		$this->ibox = $aFlg;
	}
	/**
	 * @param Image $aImg
	 * @return unknown_type
	 */
	function getWidth(Image $aImg) {
		if( !$this->ishow ) {
			return 0;
		}
		$this->InitSize($aImg);
		$t = new Text();
		$t->SetFont($this->iFontFamily,$this->iFontStyle,$this->iFontSize);
		if( $this->ilayout==0 || $this->ilayout==2 ) {
			$t->Set(sprintf($this->iLabelFormatString,$this->iMin));
			$minw = $t->GetWidth($aImg);
			$t->Set(sprintf($this->iLabelFormatString,$this->iMax));
			$maxw = $t->GetWidth($aImg);
			return $this->imodwidth+$this->ilabelmargin+max($minw,$maxw)+1;
		}
	}
	/**
	 * @param Image $aImg
	 * @return unknown_type
	 */
	function getLength(Image $aImg) {
		if( !$this->ishow ) {
			return 0;
		}
		$this->InitSize($aImg);
		return $this->nbuckets * $this->imodheight+1;
	}
	/**
	 * @return unknown_type
	 */
	function InitSize(Image $aImg) {
		$this->buckets = $this->icolormap->GetBuckets();
		$this->nbuckets = count($this->buckets);

		if( $this->iwidth > 0 && $this->iheight > 0 ) {
			if( $this->iwidth <=  1 ) {
				if( $this->ilayout==1 || $this->ilayout==3 ) {
					$this->iwidth = round($this->iwidth * $aImg->height);
				}
				elseif( $this->ilayout==0 || $this->ilayout==2 ) {
					$this->iwidth = round($this->iwidth * $aImg->width);
				}
			}
			if( $this->iheight <= 1 ) {
				if( $this->ilayout==1 || $this->ilayout==3 ) {
					$this->iheight = round($this->iheight * $aImg->width);
				}
				elseif( $this->ilayout==0 || $this->ilayout==2 ) {
					$this->iheight = round($this->iheight * $aImg->height);
				}
			}

			$this->imodwidth = $this->iwidth;
			$this->imodheight = round($this->iheight/$this->nbuckets);
		}
	}
	/**
	 * @param Image $aImg
	 * @param $xs
	 * @param $ys
	 * @return unknown_type
	 */
	function Stroke(Image $aImg,$xs,$ys) {
		if( !$this->ishow )
			return;
		$this->InitSize($aImg);

		$t = new Text();
		$t->SetFont($this->iFontFamily,$this->iFontStyle,$this->iFontSize);
        $t->SetColor($this->iFontColor);

		if( $this->ilayout==1 || $this->ilayout==3 ) {
			// Horizontal legend
			// Calculate left edge of colormap bar (xs is the center)
			$tmp = $this->imodheight;
			$this->imodheight = $this->imodwidth;
			$this->imodwidth = $tmp;

			$xs  = round($xs-($this->nbuckets * $this->imodwidth)/2);
			$x = $xs; $y = $ys;
			$dir = $this->ilayout==1 ? 1 : -1;
			for($i=0; $i < $this->nbuckets; ++$i, $x += $this->imodwidth){
				$aImg->SetColor($this->buckets[$i]);
				$aImg->FilledRectangle($x,$y,$x+$this->imodwidth,$y+$dir*$this->imodheight);
			}
			// Min value
			if( $this->ilayout==1 )
                $valign = 'top';
			else
                $valign = 'bottom';
			$t->Set(sprintf($this->iLabelFormatString,$this->iMin));
			$t->SetAlign('left',$valign);
			$t->Stroke($aImg,$xs, $ys+$dir*$this->imodheight+$dir*$this->ilabelmargin);

			// Max value
			$t->Set(sprintf($this->iLabelFormatString,$this->iMax));
			$t->SetAlign('right',$valign);
			$t->Stroke($aImg,$x,$ys+$dir*$this->imodheight+$dir*$this->ilabelmargin);
			if( $this->ibox ) {
				$aImg->SetColor($this->iboxcolor);
				$aImg->SetLineStyle($this->iboxstyle);
				$aImg->SetLineWeight($this->iboxweight);
				$aImg->Rectangle($xs,$ys,
				$x, $y+$dir*$this->imodheight);
			}
		}
		elseif( $this->ilayout==0 || $this->ilayout==2 ) {
			// Vertical legend
			// Calculate top of colormap bar (ys is the middle)
			$ys = round($ys-($this->nbuckets*$this->imodheight)/2);
			$x = $xs; $y = $ys;
			$dir = $this->ilayout==2 ? -1 : 1;
			for($i=0; $i < $this->nbuckets; ++$i, $y += $this->imodheight){
				$aImg->SetColor($this->buckets[$this->nbuckets-$i-1]);
				$aImg->FilledRectangle($x,$y,$x+$dir*$this->imodwidth,$y+$this->imodheight);
			}
			// Min value
			if( $this->ilayout==2 )
                $halign = 'right';
			else
                $halign = 'left';
			$t->Set(sprintf($this->iLabelFormatString,$this->iMax));
			$t->SetAlign($halign,'top');
			$t->Stroke($aImg,$xs+$dir*$this->imodwidth+$dir*$this->ilabelmargin,$ys);

			// Max value
			$t->Set(sprintf($this->iLabelFormatString,$this->iMin));
			$t->SetAlign($halign,'bottom');
			$t->Stroke($aImg,$xs+$dir*$this->imodwidth+$dir*$this->ilabelmargin,$y);
			if( $this->ibox ) {
				$aImg->SetColor($this->iboxcolor);
				$aImg->SetLineStyle($this->iboxstyle);
				$aImg->SetLineWeight($this->iboxweight);
				$aImg->Rectangle($xs,$ys,
				$x+$dir*$this->imodwidth,$y);
			}
		}
	}
}


class EdgeLabel {
    private $mplot=null;
    private $iFF=FF_ARIAL,$iFS=FS_NORMAL,$iFSize=8;
    private $iStartX=0,$iStartY=0;
    private $iSide='';
    private $iLabels=array();
    private $iMargin=4;
    private $iColor='black';
    private $iAngle=0;
    private $csimareas='',$csimtargets='',$csimwintargets='',$csimalts='';

    /**
     * Construct edge labels
     * @param <type> $aDefSide
     */
    function  __construct($aDefSide) {
        $this->iSide = $aDefSide;
    }

    /**
     * Set font for edge labels
     * @param int $aFF
     * @param int $aFS
     * @param int $aSize
     */
    function SetFont($aFF,$aFS,$aSize) {
        $this->iFF = $aFF;
        $this->iFS = $aFS;
        $this->iFSize = $aSize;
    }

    /**
     * Set font color
     * @param Color $aColor  Color specification
     */
    function SetFontColor($aColor) {
        $this->iColor = $aColor;
    }

    /**
     * Specify margin
     * @param int $aMargin
     */
    function SetMargin($aMargin) {
        $this->iMargin = $aMargin;
    }

    /**
     * Internal. Set start position for labels
     * @param int $aX
     * @param int $aY
     */
    function SetStartPos($aX,$aY) {
        $this->iStartX = $aX;
        $this->iStartY = $aY;
    }

    /**
     * Specify what sides the labels should be on
     * @param int $aSide
     */
    function SetSide($aSide) {
        $p = array('left','right','top','bottom');
        if( !in_array($aSide, $p) ) {
            JpGraphError::RaiseL(29208, $aSide);
        }
        $this->iSide=$aSide;
    }

    /**
     * Specify array of labels
     * @param array $aLabels
     */
    function Set($aLabels) {
        $this->iLabels = $aLabels;
    }

    /**
     * Specify angle of labels
     * @param float $aAngle
     */
    function SetAngle($aAngle) {
        $this->iAngle = $aAngle;
    }

   /**
     * Set CSIM Targets
     *
     * @param matrix $aTargets
     * @param matrix $aAlts
     * @param matrix $aWinTargets
     */
    function SetCSIMTargets($aTargets, $aAlts=array(), $aWinTargets=array() ) {
        $this->csimtargets=$aTargets;
        $this->csimwintargets=$aWinTargets;
        $this->csimalts=$aAlts;
    }

    /**
     * Return a string with CSIM map
     *
     * @return string polygon CSIM coordinates as a string
     */
    function GetCSIMAreas() {
        return $this->csimareas;
    }

    /**
     * Internal. Stroke labels
     * @param MatrixPlot $aMPlot
     * @param Image $aImg
     */
    function Stroke(MatrixPlot $aMPlot,Image $aImg) {

        $t = new Text();
        $t->SetColor($this->iColor);
        $t->SetFont($this->iFF,$this->iFS,$this->iFSize);
        $n = count($this->iLabels);
        $rows = count($aMPlot->iData);
        $cols = count($aMPlot->iData[0]);

        list($modwidth,$modheight) = $aMPlot->GetModSizes();
        $x = $this->iStartX;
        $y = $this->iStartY;

        if( !empty($this->csimtargets) || (is_array($this->csimtargets) && count($this->csimtargets)==0 ) ) {
            $csn = count($this->csimtargets);
            if( $csn != $n ) {
                JpGraphError::RaiseL(29210, $csn, $n); // CSIM Target for matrix labels must be the same length as the labels
            }
        }

        $csim='';
        switch( $this->iSide ) {
            case 'top':
            case 'bottom':
                $t->SetAngle($this->iAngle);
                $x += round($modwidth/2);
                if( $this->iSide == 'top') {
                    $y -= $this->iMargin;
                    $t->SetAlign('center', 'bottom');
                }
                else {
                    $y += $this->iMargin + $rows*$modheight ;
                    if( $this->iAngle > 0 && $this->iAngle < 90 ) {
                           $t->SetAlign('right', 'top');
                    }
                    else {
                        $t->SetAlign('center', 'top');
                    }
                }
                for( $i=0; $i < $n && $i < $cols; ++$i, $x += $modwidth ) {
                    $t->Set($this->iLabels[$i]);

                    if( !empty($this->csimtargets) ) {
                        if( !empty($this->csimalts[$i]) && ! empty($this->csimwintargets[$i])  ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],$this->csimalts[$i],$this->csimwintargets[$i]);
                        }
                        elseif( !empty($this->csimalts[$i]) ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],$this->csimalts[$i]);
                        }
                        elseif( !empty($this->csimwintargets[$i]) ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],'',$this->csimwintargets[$i]);
                        }
                        else {
                            $t->SetCSIMTarget($this->csimtargets[$i]);
                        }
                    }

                    $t->Stroke($aImg, $x, $y);
                    $csim .= $t->GetCSIMareas();
                }
                break;

            case 'left':
            case 'right':
                $t->SetAngle($this->iAngle);
                $y += round($modheight/2);
                if( $this->iSide == 'left') {
                    $x -= $this->iMargin+1;
                    $t->SetAlign('right', 'center');
                }
                else {
                    $x += $this->iMargin + $cols*$modwidth;
                    $t->SetAlign('left', 'center');
                }
                for( $i=0; $i < $n && $i < $rows; ++$i, $y += $modheight ) {
                    $t->Set($this->iLabels[$i]);

                    if( !empty($this->csimtargets) ) {
                        if( !empty($this->csimalts[$i]) && ! empty($this->csimwintargets[$i])  ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],$this->csimalts[$i],$this->csimwintargets[$i]);
                        }
                        elseif( !empty($this->csimalts[$i]) ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],$this->csimalts[$i]);
                        }
                        elseif( !empty($this->csimwintargets[$i]) ) {
                            $t->SetCSIMTarget($this->csimtargets[$i],'',$this->csimwintargets[$i]);
                        }
                        else {
                            $t->SetCSIMTarget($this->csimtargets[$i]);
                        }
                    }
                    $t->Stroke($aImg, $x, $y);
                    $csim .= $t->GetCSIMareas();
                }
                break;
        }
        $this->csimareas = $csim;
    }
}
/**
 * Class MatrixPlot
 */
class MatrixPlot {
    private $initSize = false;
	private $icx=0.45,$icy=0.5; // Center of the plot
	private $iBackgroundColor = 'white';
	public $iData = null;
	public $iModWidth=10, $iModHeight=12;
	private $iModType=0; // Rectangle as default
	public $colormap = null;
	public $legend = null;
	private $iboxcolor='darkgray',$iboxweight=1,$iboxstyle='solid',$ibox=true;
	private $ilegendlayout = 0;
	private $iwidth=0.7, $iheight=null;
	public $rows,$cols;
	public $imargin=40;
    private $iAlpha=0; // No mixing by default
	private $iContrast = 0;
    public $collabel=array(),$rowlabel=array();
    private $csimareas='',$csimtargets='',$csimwintargets='',$csimalts='';
    private $iPlotLines=array();

	/**
	 * @param $aData
	 * @param $aMeshInt
	 * @return unknown_type
	 */
	function __construct($aData,$aMeshInt=1) {
		if( $aMeshInt > 1 ) {
			doMeshInterpolate($aData,$aMeshInt);
		}
		$this->iData = $aData;
		$this->colormap = new ColorMap();
		$this->legend = new MatrixLegend($this->colormap);
        $this->collabel = new EdgeLabel('top');
        $this->collabel->SetAngle(90);
        $this->rowlabel = new EdgeLabel('right');
	}

	/**
	 * @param $aRowlabels
	 * @param $aCollabels
	 * @return unknown_type
	 */
    function SetEdgeLabel($aRowlabels, $aCollabels) {
        $this->rowlabels->Set($aRowlabels);
        $this->colabels->Set($aCollabels);
    }

	/**
	 * @param $aX
	 * @param $aY
	 * @return unknown_type
	 */
	function SetCenterPos($aX,$aY) {
		$this->icx = $aX;
		$this->icy = $aY;
	}

	/**
	 * @param $aMarg
	 * @return unknown_type
	 */
	function SetMargin($aMarg) {
		$this->imargin = $aMarg;
	}

	/**
	 * @param $aX
	 * @param $aY
	 * @return unknown_type
	 */
	function SetPos($aX,$aY) {
		$this->SetCenterPos($aX,$aY);
	}

	function SetAutoContrast($aContrast) {
		$this->iContrast = $aContrast;
	}
	/**
	 * @param $aColor
	 * @param $aWeight
	 * @param $aStyle
	 * @param $aFlg
	 * @return unknown_type
	 */
	function SetBox($aColor='black',$aWeight=1,$aStyle='solid',$aFlg=true) {
		$this->iboxcolor = $aColor;
		$this->iboxweight = $aWeight;
		$this->iboxstyle = $aStyle;
		$this->ibox = $aFlg;
	}
    function SetAlpha($aAlpha) {
        $this->iAlpha = $aAlpha;
    }

	/**
	 * @param $aLayout
	 * @param $aMargin
	 * @return unknown_type
	 */
	function SetLegendLayout($aLayout,$aMargin=25) {
		$this->ilegendlayout = $aLayout;
		$this->legend->SetMargin($aMargin);
	}
	/**
	 * @param $aColor
	 * @return unknown_type
	 */
	function SetBackgroundColor($aColor) {
		$this->iBackgroundColor = $aColor;
	}
	/**
	 * @param $aWidth
	 * @param $aHeight
	 * @return unknown_type
	 */
	function SetSize($aWidth, $aHeight=null) {
		$this->iwidth = $aWidth;
		$this->iheight = $aHeight;
        $this->initSize = false;
	}
	/**
	 *  This is a polymorphic function
	 *  width height for square modules
	 *  diameter for circular module
	 */
	function SetModuleSize($aW,$aH=null) {
		$this->iModWidth = $aW;
		if( $aH === null )
			$aH = $aW;
		$this->iModHeight = $aH;
		$this->iwidth = -1;
		$this->iheight = -1;
	}
    /**
    *
    * @param mixed $aType
    */
    function SetModuleType($aType) {
        $this->iModType = $aType;
    }
	/**
	 * @return unknown_type
	 */
	function SetupColormap() {
		// Setup the colormap based on the values in the matrix
		list($rmin,$rmax) = $this->colormap->GetRange();
		if( $rmin == 0 && $rmax == 0 ) {
			$rows = count( $this->iData );
			$cols = count( $this->iData[0] );
			$maxval = $this->iData[0][0];
			$minval = $this->iData[0][0];
			for($r=0; $r < $rows; ++$r){
				$maxval = max($maxval,max($this->iData[$r]));
				$minval = min($minval,min($this->iData[$r]));
			}
			if( $this->iContrast !== 0 ) {
				$adj = ($maxval-$minval+1) * $this->iContrast/2;
				$minval += $adj;
				$maxval -= $adj;
			}
			$this->colormap->SetRange($minval,$maxval);
		}
	}

    function GetModSizes() {
        return array($this->iModWidth,$this->iModHeight);
    }
	/**
	 * @param Image $aImg
	 * @return unknown_type
	 */
	function GetWidth(Image $aImg) {
		$this->InitSize($aImg);
		$width = $this->cols * $this->iModWidth ;
		if( $this->ilegendlayout == 0 || $this->ilegendlayout == 2 ) {
			// On right or left side
			if( $this->legend->ishow ) {
				$width += $this->legend->imargin + $this->legend->getWidth($aImg);
			}
		}
		else {
			// On bottom or top
		}
		return $width + $this->imargin;
	}
	/**
	 * @param Image $aImg
	 * @return unknown_type
	 */
	function GetHeight(Image $aImg) {
		$this->InitSize($aImg);
		$height = $this->rows * $this->iModHeight ;
		if( $this->ilegendlayout == 1 || $this->ilegendlayout == 3 ) {
			if( $this->legend->ishow ) {
				$height += $this->legend->imargin + $this->legend->getLength($aImg);
			}
		}
		else {
			// On right or left
		}
		return $height + $this->imargin;
	}
	/**
	 * @param Image $aImg
	 * @return unknown_type
	 */
	function InitSize(Image $aImg) {
        if( $this->initSize ) return;

        $this->initSize = true;
		$this->rows = count( $this->iData );
		if( $this->rows <= 0 ) {
			JpGraphError::RaiseL(29207);
			// 'Empty input data specified for MatrixPlot'
		}
		$this->cols = count( $this->iData[0] );
		if( $this->cols <= 0 ) {
			JpGraphError::RaiseL(29207);
			// 'Empty input data specified for MatrixPlot'
		}

		// Check if the user has specified an overall size
		// in thta case this takes precedence over any specified
		// module size
		if( $this->iwidth > 0 ) {
			if( $this->iheight === null ) {
				// In this case the user has only specified one size parameter
				// This will then be used to et both the width & height to the same
				if( $this->iwidth <= 1 ) {
					$this->iwidth *= min($aImg->width,$aImg->height);
					$this->iheight = $this->iwidth;
				}
				else {
					$this->iheight = $this->iwidth;
				}
			}
			else {
				if( $this->iwidth <= 1 ) {
					$this->iwidth *= $aImg->width;
				}
				if(  $this->iheight > 0 && $this->iheight <= 1 ) {
					$this->iheight *= $aImg->height;
				}
			}
			// Now adjust module size to get close to the specified with/height
			$this->iModWidth = round($this->iwidth / $this->cols);
			$this->iModHeight = round($this->iheight / $this->rows);
		}

		$this->colormap->InitRGB($aImg->rgb);
		$this->SetupColorMap();

	}

    /**
     * Set CSIM Targets
     *
     * @param matrix $aTargets
     * @param matrix $aAlts
     * @param matrix $aWinTargets
     */
    function SetCSIMTargets($aTargets, $aAlts=array(), $aWinTargets=array() ) {
        $this->csimtargets=$aTargets;
        $this->csimwintargets=$aWinTargets;
        $this->csimalts=$aAlts;
    }

    /**
     * Return a string with CSIM map
     *
     * @return string polygon CSIM coordinates as a string
     */
    function GetCSIMAreas() {
        return $this->rowlabel->GetCSIMAreas() . $this->collabel->GetCSIMAreas() . $this->csimareas;
    }

    /**
     * Add line to plot
     * @param PlotLine $aPlotLine
     */
    function AddLine($aPlotLine) {
        if( is_array($aPlotLine) ) {
            for($i=0; $i < count($aPlotLine); ++$i ) {
                $this->iPlotLines[]=$aPlotLine[$i];
            }
        }
        else {
            $this->iPlotLines[]=$aPlotLine;
        }
    }

	/**
	 * @param MatrixGraph $aGraph
	 * @return unknown_type
	 */
	function Stroke(MatrixGraph $aGraph) {

		$this->InitSize($aGraph->img);

		if( $this->iModType == 1 ) {
			// For circles these muts be the same
			$this->iModHeight = $this->iModWidth;
		}
		$width = $this->cols * $this->iModWidth;
		$height = $this->rows * $this->iModHeight;

		// Setup values for legend
		list($min,$max) = $this->colormap->GetRange();
		$this->legend->SetMinMax($min,$max);

		if( $this->icx < 1 ) {
			$this->icx *= $aGraph->img->width;
		}
		if( $this->icy < 1 ) {
			$this->icy *= $aGraph->img->height;
		}

		// Calculate top left corner so we now where to start
		$xs = round($this->icx - $width/2 );
		$ys = round($this->icy - $height/2);

		if( __DEBUG  ) {
			$aGraph->img->SetColor('red');
			$aGraph->img->Rectangle($xs,$ys,$xs+$this->GetWidth($aGraph->img),$ys+$this->GetHeight($aGraph->img));
		}

		if( $this->iModType == 1 ) {
            // We are using circular dots to fill the matrix
			// Set the background color
			$aGraph->img->SetColor($this->iBackgroundColor);
			$aGraph->img->FilledRectangle($xs,$ys,
										  $xs+$this->cols*$this->iModWidth,
										  $ys+$this->rows*$this->iModWidth);
			$xs += round($this->iModWidth/2); // For circle width is the diameter
			$ys += round($this->iModWidth/2); // For circle width is the diameter
		}

		$y = $ys;
        for($r=0; $r < $this->rows; ++$r, $y += $this->iModHeight){
			$x = $xs;
			for($c=0; $c < $this->cols; ++$c, $x += $this->iModWidth){

                $color = $this->colormap->getColor($this->iData[$r][$c]);
                $color[3]=$this->iAlpha;
				$aGraph->img->SetColor($color);

                if( $this->iModType == 0 ) {
					$aGraph->img->FilledRectangle($x,$y,$x+$this->iModWidth-1,$y+$this->iModHeight-1);
				}
				else {
					$aGraph->img->FilledCircle($x,$y,round($this->iModWidth/2)-1);
				}

			}
		}

        // Add possible plot lines
        $n = count($this->iPlotLines);
        for ($i = 0 ; $i < $n ; $i++) {
            if( $this->iModType == 0 ) {
                $posx = $this->iPlotLines[$i]->scaleposition*$this->iModWidth + $xs;
                $posy = $this->iPlotLines[$i]->scaleposition*$this->iModHeight + $ys;
                $minx = $xs ;
                $miny = $ys ;
                $maxx = $xs + $width;
                $maxy = $ys + $height;
            }
            else {
                $posx = $this->iPlotLines[$i]->scaleposition*$this->iModWidth + $xs - $this->iModWidth/2;
                $posy = $this->iPlotLines[$i]->scaleposition*$this->iModHeight + $ys - $this->iModWidth/2;
                $minx = $xs-$this->iModWidth/2;
                $miny = $ys-$this->iModWidth/2;
                $maxx = $xs + $width - round($this->iModWidth/2);
                $maxy = $ys + $height - round($this->iModWidth/2);
            }
            $this->iPlotLines[$i]->_Stroke($aGraph->img,
                $minx, $miny,
                $maxx, $maxy,
                $posx, $posy );
        }

        if( !empty($this->csimtargets) && is_array($this->csimtargets) ) {

            $csr = count($this->csimtargets);
            $csc = count($this->csimtargets[0]);

            if( $csr != $this->rows || $csc != $this->cols ) {
                JpGraphError::RaiseL(29209, $csr, $csc, $this->rows, $this->cols); // CSIM Taregt matrix must be the same size as the data matrix
            }

            if( $this->iModType == 0 ) {
                $y = $ys;
            } else {
                // For circular marks the plotted x,y is the centrum and for the csim
                // we start in the top left corner so we must compensate
                $y = $ys - round($this->iModHeight/2);
            }
            for($r=0; $r < $this->rows; ++$r, $y += $this->iModHeight) {

                if( $this->iModType == 0 ) {
                    $x = $xs;
                } else {
                    // For circular marks the plotted x,y is the centrum and for the csim
                    // we start in the top left corner so we must compensate
                    $x = $xs - round($this->iModHeight/2);
                }

                for($c=0; $c < $this->cols; ++$c, $x += $this->iModWidth) {

                    $csimcoord = $x.", ".$y;
                    $csimcoord .= ", ".($x+$this->iModWidth).", ".$y;
                    $csimcoord .= ", ".($x+$this->iModWidth).", ".($y+$this->iModHeight);
                    $csimcoord .= ", ".$x.", ".($y+$this->iModHeight);

                    $this->csimareas .= '<area shape="poly" coords="'.$csimcoord.'" ';
                    $this->csimareas .= " href=\"".htmlentities($this->csimtargets[$r][$c])."\"";

                    if( !empty($this->csimwintargets[$r][$c]) ) {
                        $this->csimareas .= " target=\"".$this->csimwintargets[$r][$c]."\" ";
                    }

                    $sval='';
                    if( !empty($this->csimalts[$r][$c]) ) {
                        $sval=sprintf($this->csimalts[$r][$c],$this->iData[$r][$c]);
                        $this->csimareas .= " title=\"$sval\" alt=\"$sval\" ";
                    }

                    $this->csimareas .= " />\n";

                }
            }

            if( $this->iModType == 1 ) {
                // Restore x so that it is the same relative value whether we have plotted square or circle
                // markers. Otherwise we have to compensate for this further down anyway.
                $x += round($this->iModHeight/2);
                $y += round($this->iModHeight/2);
            }
        }


        if( $this->iModType == 1 ) {
            $xs -= round($this->iModWidth/2); // For circle width is the diameter
            $ys -= round($this->iModWidth/2); // For circle width is the diameter
        }


		if( $this->ibox ) {
			$aGraph->img->SetColor($this->iboxcolor);
			$aGraph->img->SetLineStyle($this->iboxstyle);
			$aGraph->img->SetLineWeight($this->iboxweight);
            if( $this->iModType == 1 ) {
                $x -= round($this->iModWidth/2);
                $y -= round($this->iModWidth/2);
            }
			$aGraph->img->Rectangle($xs,$ys,$x,$y);
		}

        $this->collabel->SetStartPos($xs, $ys);
        $this->rowlabel->SetStartPos($xs, $ys);

        $this->collabel->Stroke($this,$aGraph->img);
        $this->rowlabel->Stroke($this,$aGraph->img);

		// Legendnposition to the right
		$this->legend->SetLayout($this->ilegendlayout);
		switch ($this->ilegendlayout) {
			case 0:
				// To the right
				$lx = $x+$this->legend->imargin; $ly = $this->icy;
				break;
			case 1:
				// At bottom
				$lx = $this->icx; $ly = $y+$this->legend->imargin;
				break;
			case 2:
				// To the left
				$lx = $xs-$this->legend->imargin; $ly = $this->icy;
				break;
			case 3:
				// At the top
				$lx = $this->icx; $ly = $ys-$this->legend->imargin;
				break;
		}
		$this->legend->Stroke($aGraph->img,$lx,$ly);
	}
}

// EOF
?>
