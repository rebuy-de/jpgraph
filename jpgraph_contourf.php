<?php
/*=======================================================================
// File:        JPGRAPH_CONTOURF.PHP
// Description: Filled contour plots
// Created:     2009-09-21
// Ver:         $Id: jpgraph_contourf.php 1855 2009-09-28 14:32:43Z ljp $
//
// Copyright (c) Asial Corporation. All rights reserved.
//========================================================================
*/
require_once('jpgraph_meshinterpolate.inc.php');

/*
 * This class implements an adaptive contour finding algorithm. The algorithm works
 * by subdividing the entire area into either rectangles or triangles depending
 * on the mode setting (user selectable). The algorithm is recirsive and will subdivide
 * the mesh until one of two things happens
 * 1) It has reached the maximum depth allowed for the recursion. In that case the
 *    entire submesh is filled with a single color which will correspond to the
 *    contour of the average of the corener values in the submesh.
 * 2) The subdivided rectangle (or triangle) is crossed by a maximum of one contour
 *    line on each side of the rectangle (triangle).
 *    The the contour line is drawn if needed and the corresponding areas
 *    are filled with the appropriate color belong to the contour. The area so dividded
 *    can have at most three different filled sub areas
 *
 * Since there is a choice of coloring the oncvention used in this algorithm is the color
 * belonging to contour i is used to fill the are between contour i and contour i+1 (exclusive)
 * This also has the consequence that the first color specified in the color array will be used
 * to fill the are below the minimum contour line specified (or automatically calculated).
 * 
 */
class ContourWorker {
    // Data for contour (isobar) lines
    public $nContours=-1, $contval = array(), $contcolors = array();

    // Contour line colors to use together with filled contours
    public $contlinecolor='black';

    // Determine if contour lines should be shown
    public $showcontlines=true;

    // Should the contour be filled
    public $fillContour=false;

    // Show the triangulation/rectangularization of the adaptive algorithm in
    // the grid
    public $showtriangulation=false,$triangulation_color="lightgray";
    
    // The input data
    private $data=array(), $nRows=-1, $nCols=-1;

    // Maximum default depth for recursion (6 = divide 64 times)
    private $maxdepth=6;

    // Flag to show labels and there positionś on the grid
    private $labels = array(), $showlabels=false;

    // Label properties
    private $labelColor='black',$labelFF=FF_ARIAL,$labelFS=FS_BOLD,$labelFSize=10;
    
    // Should the labels follow the contour gradient or not
    private $angledLabels=true;

    // Make some additional collision check for labels
    private $extendedcollisioncheck=false;

    // Contoud color properties
    private $highcontrast=false,$highcontrastbw=false;

    // Invert the data around the midpoint
    private $invert=false;

    function  __construct($aData,$aContours=10) {
        // This is either an integer specifying number of contour lines to use
        // or it is an array of actual contour values to use.
        if( is_array($aContours) ) {
            $this->nContours = count($aContours);
            $this->contval = $aContours;
        }
        else {
            $this->nContours = $aContours;
            $this->contval = array();
        }
        $this->data = $aData;
        $this->nRows = count($aData);
        $this->nCols = count($aData[0]);
    }

    /*
     * Manually specify contour values
     *
     * @param $aContours color array
     *
     */
    function SetContours($aContours) {
        $this->contval = $aContours;
    }

    /*
     * Manually specify contour colors
     *
     * @param $aColors color array
     *
     */
    function SetContColors($aColors) {
        $this->contcolors = $aColors;
    }

    /**
     * Specify the font properties for the labels
     *
     * @param $aFFamily Font famly
     * @param $aFStyle Font style
     * @param $aFSize Font size
     */
    function SetFont($aFFamily,$aFStyle=FS_NORMAL,$aFSize=10) {
        $this->labelFF = $aFFamily;
        $this->labelFS = $aFStyle;
        $this->labelFSize = $aFSize;
    }

    /**
     * Specify font color for labels
     * @param $aColor Color specification
     */
    function SetFontColor($aColor) {
        $this->labelColor = $aColor;
    }

    /**
     * Seach input data matrice for minimum and maximum value. Used when determining
     * automatic contour values.
     *
     * @return array
     */
    function GetMinMaxVal() {
        $min = $this->data[0][0];
        $max = $this->data[0][0];
        for ($i = 0; $i < $this->nRows; $i++) {
            if( ($mi=min($this->data[$i])) < $min )  $min = $mi;
            if( ($ma=max($this->data[$i])) > $max )  $max = $ma;
        }
        return array($min,$max);
    }

    /**
     * Use high contrast colors
     *
     * @param $aFlg true/false determines if high contrsat colors should be usd or not
     * @param $aBW Use only black and white
     * @return void
     */
    function UseHighContrastColor($aFlg=true,$aBW=false) {
        $this->highcontrast = $aFlg;
        $this->highcontrastbw = $aBW;
    }

    /**
     * Calculate suitable colors for each defined isobar
     *
     */
    function CalculateColors() {
        if ( $this->highcontrast ) {
            if ( $this->highcontrastbw ) {
                for ($ib = 0; $ib <= $this->nContours; $ib++) {
                    $this->contcolors[$ib] = 'black';
                }
            }
            else {
                // Use only blue/red scale
                $step = 255.0/$this->nContours;
                for ($ib = 0; $ib <= $this->nContours; $ib++) {
                    $this->contcolors[$ib] = array(round($ib*$step), 50, round(255-$ib*$step));
                }
            }
        }
        else {
            // This will return a basic spectrum from blue to red in even steps
            // this should probably be upgraded to our newer spectrum method introduced
            // with matrix plots
            $n = $this->nContours;
            $v = 0; $step = 1 / ($this->nContours);
            for ($ib = 0; $ib <= $this->nContours; $ib++) {
                $this->contcolors[$ib] = RGB::GetSpectrum($v);
                $v += $step;
            }
        }
    }

    /**
     * Set maximum search depth for recursive contour algorithm
     * 
     * @param $aDepth search depth
     * 
     */
    function SetMaxSearchDepth($aDepth) {
        $this->maxdepth = $aDepth;
    }

    /**
     * Determine if contour levels should be shown close to the contour lines
     *
     * @param $aFlg true/false if labels should be shown
     *
     */
    function ShowLabels($aFlg=true,$aAngledLabels=true) {
        $this->showlabels = $aFlg;
        $this->angledLabels = $aAngledLabels;
    }

    /**
     * Determine if contour lines should be shown
     *
     * @param $aFlg true/false if lines should be shown
     * @param $aColorWhenFilled Color to use for isobar lines when we are
     * filling the countours
     *
     */
    function ShowLines($aFlg=true,$aColorWhenFilled='black') {
        $this->showcontlines=$aFlg;
        $this->contlinecolor=$aColorWhenFilled;
    }

    /**
     * Determine if contour should be filled
     *
     * @param $aFlg true/false if contour should be filled or not
     *
     */
    function SetFilled($aFlg=true) {
        $this->fillContour = $aFlg;
    }

    /**
     * Determine if the input data should have its top left corner or
     * bottom left corner as (0,0)
     *
     * @param $aFlg true/false if input data should be flipped around the
     * horizontal middle row
     *
     */
    function SetInvert($aFlg=true) {
        $this->invert = $aFlg;
    }

    /**
     * Determine if contour levels should be shown close to the contour lines
     *
     * @param $aFlg true/false if labels should be shown
     *
     */
    function ShowTriangulation($aFlg=true) {
        $this->showtriangulation = $aFlg;
    }

    /**
     * Translate from viewport coordinates to screen coordinates
     *
     * @param $p nput array of ($x,$y) coordinate pairs
     */
    function Translate(&$p) {
        $n = count($p);
        for ($i = 0 ; $i < $n ; $i += 2) {
            $p[$i]   = $this->xscale->Translate($p[$i]);
            $p[$i+1] = $this->yscale->Translate($p[$i+1]);
        }
    }

    /**
     * Draw a line with the specified color
     *
     * @param string $aColor
     * @param array $p line coordinates
     */
    function Line($aColor,&$p) {
        $this->Translate($p);
        $this->img->SetColor($aColor);
        $this->img->Line($p[0],$p[1],$p[2],$p[3]);
    }

    /**
     * Draw a polygon for the specified coordinates
     *
     * @param string $color color specification
     * @param array $p array of (x,y) coordinate pairs
     */
    function FillPolygon($color,&$p) {
        $this->Translate($p);
        if( $this->fillContour ) {
            $this->img->SetColor($color);
            $this->img->FilledPolygon($p);
        }
        if( $this->showtriangulation ) {
            $this->img->SetColor($this->triangulation_color);
            $this->img->Polygon($p);
        }
    }

    /**
     * Given a value find out what the next highest index of the
     * crossing isobar is
     *
     * @param $val value strict less than the isobar index returned
     * @return int isobar index
     */
    function GetNextHigherContourIdx($val) {
        for( $i=0; $i < count($this->contval); ++$i ) {
            if( $val < $this->contval[$i] ) return $i;
        }
        return count($this->contval);
    }

    /**
     * Return the next highest contour value given the input value
     *
     * @param float $aVal
     * @return float contour value
     */
    function GetContVal($aVal) {
        for( $i=0; $i < count($this->contval); ++$i ) {
            if( $aVal < $this->contval[$i] ) {
                return $this->contval[$i];
            }
        }
        JpGraphError::RaiseL($errnbr); // "ContourPlot2: Internal error
    }

    /**
     * Return the color for the next isobar after the specified value
     * @param float $aVal
     * @return color specification
     */
    function GetColor($aVal) {        
        return $this->contcolors[$this->GetNextHigherContourIdx($aVal)];
    }

    /**
     * Determine if there are other labels in the proximity of the specified
     * position. This is used to make sure that we don't put too many labels
     * close to eachother.
     *
     * @param $x1 x-coordinate
     * @param $y1 y-coordinate
     * @param $v1 isobar value
     * @return boolean true of there is another label "close" by
     */
    function LabelProx($x1,$y1,$v1) {

        $w = $this->img->plotwidth+$this->img->left_margin;
        $h = $this->img->plotheight+$this->img->top_margin;

        if( $x1 < 30 || $x1 > $w-20 )
            return true;

        if( $y1 < 20 || $y1 > $h-20 )
            return true;

        $idx = $this->GetNextHigherContourIdx($v1);
        if( !isset ($this->labels[$idx]) ) {
            return false;
        }
        $p = $this->labels[$idx];
        $n = count($p);
        $d = 9999999;
        for ($i = 0 ; $i < $n ; $i++) {
            $xp = $p[$i][0];
            $yp = $p[$i][1];
            $d = min($d, ($x1-$xp)*($x1-$xp) + ($y1-$yp)*($y1-$yp));
        }

        $d2 = 9999999;
        $d3 = 9999999;
        if( $this->extendedcollisioncheck ) {
            // Also check the contour line above and below this one.
            // In those cases we accept that the label is much closer
            if( $idx < count($this->labels)-1 && isset($this->labels[$idx+1]) ) {
                $p = $this->labels[$idx+1];
                $n = count($p);
                for ($i = 0 ; $i < $n ; $i++) {
                    $xp = $p[$i][0];
                    $yp = $p[$i][1];
                    $d2 = min($d2, ($x1-$xp)*($x1-$xp) + ($y1-$yp)*($y1-$yp));
                }
            }
            if( $idx > 0 && isset($this->labels[$idx-1]) ) {
                $p = $this->labels[$idx-1];
                $n = count($p);
                for ($i = 0 ; $i < $n ; $i++) {
                    $xp = $p[$i][0];
                    $yp = $p[$i][1];
                    $d3 = min($d3, ($x1-$xp)*($x1-$xp) + ($y1-$yp)*($y1-$yp));
                }
            }
        }

        $limit = $w*$h/9;
        $limit = max(min($limit,10000),3000);

        if( $d2 < $limit/3 || $d3 < $limit/3 || $d < $limit/2 ) {
            return true;
        }
        else {
            return false;
        }

    }

    /**
     * ry to put a label for the specified isobar at this position.
     * The method will see if other labels are nearby and in that case
     * this label will not be shown.
     *
     * @param $x1 x-position of label
     * @param $y1 y-position of label
     * @param $x2 direction vector from the label position
     * @param $y2 direction vector from the label position
     * @param $v1 isobar value to show
     */
    function PutLabel($x1,$y1,$x2,$y2,$v1) {
       
        $angle = 0;
        if( $x2 - $x1 != 0 ) {
            $grad = ($y2-$y1)/($x2-$x1);
            $angle = -(atan($grad) * 180/M_PI);
        }

        if( !$this->LabelProx($x1, $y1, $v1) ) {
            $this->labels[$this->GetNextHigherContourIdx($v1)][] = array($x1,$y1,$v1,$angle);
        }
    }

    /**
     * Stroke all stored labels to the plot
     *
     * @return void
     */
    function StrokeLabels() {
        $t = new Text();
        $t->SetColor($this->labelColor);
        $t->SetFont($this->labelFF,$this->labelFS,$this->labelFSize);
        $t->SetAlign('center','center');

        foreach ($this->labels as $cont_idx => $pos) {            
            // FIXME:
            //if( $cont_idx >= 10 ) return;
            foreach ($pos as $idx => $coord) { 
                $t->Set( sprintf("%.1f",$coord[2]) );
                if( $this->angledLabels )
                    $t->SetAngle($coord[3]);

                //$t->SetBox2('lightyellow:1.9');

                $t->Stroke($this->img,$coord[0],$coord[1]);
            }
        }
    }

    /**
     * Pertubate all vertice values that are identical to an isobar
     * values. This is needed so that an isobar crossing only belongs
     * exactly to one side of the submesh. We pertubate up to four values
     * in one call since that is as many labels there are for a rectangular
     * submesh. When called from the triangulation we just use s dummy last value
     *
     * @param <type> $v1 vertice value 1
     * @param <type> $v2 vertice value 2
     * @param <type> $v3 vertice value 3
     * @param <type> $v4 vertice value 4
     */
    function Pertubate(&$v1,&$v2,&$v3,&$v4) {
        $pert = 0.9999;
        $n = count($this->contval);
        for($i=0; $i < $n; ++$i) {
            if( $v1==$this->contval[$i] ) {
                $v1 *= $pert;
                break;
            }
        }
        for($i=0; $i < $n; ++$i) {
            if( $v2==$this->contval[$i] ) {
                $v2 *= $pert;
                break;
            }
        }
        for($i=0; $i < $n; ++$i) {
            if( $v3==$this->contval[$i] ) {
                $v3 *= $pert;
                break;
            }
        }
        for($i=0; $i < $n; ++$i) {
            if( $v4==$this->contval[$i] ) {
                $v4 *= $pert;
                break;
            }
        }
    }

    /**
     * Calculate the linear interpolation of the isobar line
     * crossing the edge between the two points specified
     * /x1,y1) - (x2,y2). The value at each point are given as
     * $v1 and $v2
     *
     * @param <type> $x1
     * @param <type> $y1
     * @param <type> $x2
     * @param <type> $y2
     * @param <type> $v1
     * @param <type> $v2
     * @return array($x1p,$y1p,$v1p) where the coordinates specify the
     * coordinate fr the crossing and the value the isobar that fits between
     * v1 and v2
     */
    function interp2($x1,$y1,$x2,$y2,$v1,$v2) {
        $cv = $this->GetContVal(min($v1,$v2));
        $alpha = ($v1-$cv)/($v1-$v2);
        $x1p = $x1*(1-$alpha) + $x2*$alpha;
        $y1p = $y1*(1-$alpha) + $y2*$alpha;
        $v1p = $v1 + $alpha*($v2-$v1);
        return array($x1p,$y1p,$v1p);
    }

    /**
     * Search recursively for contour lines and do possible fills using a
     * rectangular mesh.
     *
     * @param <type> $v1
     * @param <type> $v2
     * @param <type> $v3
     * @param <type> $v4
     * @param <type> $x1
     * @param <type> $y1
     * @param <type> $x2
     * @param <type> $y2
     * @param <type> $x3
     * @param <type> $y3
     * @param <type> $x4
     * @param <type> $y4
     * @param <type> $depth
     */
    function RectFill($v1,$v2,$v3,$v4,$x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4,$depth) {
         if( $depth > $this->maxdepth ) {
            // Abort and just appoximate the color of this area
            // with the average of the three values
            $color = $this->GetColor(($v1+$v2+$v3+$v4)/4);
            $p = array($x1, $y1, $x2, $y2, $x3, $y3, $x4, $y4, $x1, $y1);
            $this->FillPolygon($color,$p) ; 
        }
        else {

            $this->Pertubate($v1,$v2,$v3,$v4);

            $fcnt = 0 ;
            $vv1 = $this->GetNextHigherContourIdx($v1);
            $vv2 = $this->GetNextHigherContourIdx($v2);
            $vv3 = $this->GetNextHigherContourIdx($v3);
            $vv4 = $this->GetNextHigherContourIdx($v4);
            $eps = 0.0001;

           if( $vv1 == $vv2 && $vv2 == $vv3 && $vv3 == $vv4 ) {
                $color = $this->GetColor($v1);
                $p = array($x1, $y1, $x2, $y2, $x3, $y3, $x4, $y4, $x1, $y1);
                $this->FillPolygon($color,$p) ;
            }
            else {

                $dv1 = abs($vv1-$vv2);
                $dv2 = abs($vv2-$vv3);
                $dv3 = abs($vv3-$vv4);
                $dv4 = abs($vv1-$vv4);

                if( $dv1 == 1 ) {
                    list($x1p,$y1p,$v1p) = $this->interp2($x1,$y1,$x2,$y2,$v1,$v2);
                    $fcnt++;
                }

                if( $dv2 == 1 ) {
                    list($x2p,$y2p,$v2p) = $this->interp2($x2,$y2,$x3,$y3,$v2,$v3);
                    $fcnt++;
                }

                if( $dv3 == 1 ) {
                    list($x3p,$y3p,$v3p) = $this->interp2($x3,$y3,$x4,$y4,$v3,$v4);
                    $fcnt++;
                }

                if( $dv4 == 1 ) {
                    list($x4p,$y4p,$v4p) = $this->interp2($x4,$y4,$x1,$y1,$v4,$v1);
                    $fcnt++;
                }

                $totdv = $dv1 + $dv2 + $dv3 + $dv4 ;

                if( ($fcnt == 2 && $totdv==2) || ($fcnt == 4 && $totdv==4) ) {

                    if( $fcnt == 2 && $totdv==2 ) {

                        if( $dv1 == 1 && $dv2 == 1) {
                            $color1 = $this->GetColor($v2);
                            $p1 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x1p,$y1p);
                            $color2 = $this->GetColor($v4);
                            $p2 = array($x1,$y1,$x1p,$y1p,$x2p,$y2p,$x3,$y3,$x4,$y4,$x1,$y1);

                            $color = $this->GetColor($v1p);
                            $p = array($x1p,$y1p,$x2p,$y2p);
                            $v = $v1p;
                        }
                        elseif( $dv1 == 1 && $dv3 == 1 ) {
                            $color1 = $this->GetColor($v2);
                            $p1 = array($x1p,$y1p,$x2,$y2,$x3,$y3,$x3p,$y3p,$x1p,$y1p);
                            $color2 = $this->GetColor($v4);
                            $p2 = array($x1,$y1,$x1p,$y1p,$x3p,$y3p,$x4,$y4,$x1,$y1);

                            $color = $this->GetColor($v1p);
                            $p = array($x1p,$y1p,$x3p,$y3p);
                            $v = $v1p;
                        }
                        elseif( $dv1 == 1 && $dv4 == 1 ) {
                            $color1 = $this->GetColor($v1);
                            $p1 = array($x1,$y1,$x1p,$y1p,$x4p,$y4p,$x1,$y1);
                            $color2 = $this->GetColor($v3);
                            $p2 = array($x1p,$y1p,$x2,$y2,$x3,$y3,$x4,$y4,$x4p,$y4p,$x1p,$y1p);

                            $color = $this->GetColor($v1p);
                            $p = array($x1p,$y1p,$x4p,$y4p);
                            $v = $v1p;
                        }
                        elseif( $dv2 == 1 && $dv4 == 1 ) {
                            $color1 = $this->GetColor($v1);
                            $p1 = array($x1,$y1,$x2,$y2,$x2p,$y2p,$x4p,$y4p,$x1,$y1);
                            $color2 = $this->GetColor($v3);
                            $p2 = array($x4p,$y4p,$x2p,$y2p,$x3,$y3,$x4,$y4,$x4p,$y4p);

                            $color = $this->GetColor($v2p);
                            $p = array($x2p,$y2p,$x4p,$y4p);
                            $v = $v2p;
                        }
                        elseif( $dv2 == 1 && $dv3 == 1 ) {
                            $color1 = $this->GetColor($v1);
                            $p1 = array($x1,$y1,$x2,$y2,$x2p,$y2p,$x3p,$y3p,$x4,$y4,$x1,$y1);
                            $color2 = $this->GetColor($v3);
                            $p2 = array($x2p,$y2p,$x3,$y3,$x3p,$y3p,$x2p,$y2p);

                            $color = $this->GetColor($v2p);
                            $p = array($x2p,$y2p,$x3p,$y3p);
                            $v = $v2p;
                        }
                        elseif( $dv3 == 1 && $dv4 == 1 ) {
                            $color1 = $this->GetColor($v1);
                            $p1 = array($x1,$y1,$x2,$y2,$x3,$y3,$x3p,$y3p,$x4p,$y4p,$x1,$y1);
                            $color2 = $this->GetColor($v4);
                            $p2 = array($x4p,$y4p,$x3p,$y3p,$x4,$y4,$x4p,$y4p);

                            $color = $this->GetColor($v4p);
                            $p = array($x4p,$y4p,$x3p,$y3p);
                            $v = $v4p;
                        }

                        $this->FillPolygon($color1,$p1);
                        $this->FillPolygon($color2,$p2);

                        if( $this->showcontlines ) {
                            if( $this->fillContour) {
                                $this->Line($this->contlinecolor,$p);
                            }
                            else {
                                $this->Line($color,$p);
                            }

                        }
                        if( $this->showlabels ) {
                            if( !$this->showcontlines ) {
                                $this->Translate($p);
                            }
                            $this->PutLabel( ($p[0]+$p[2])/2, ($p[1]+$p[3])/2, $p[2],$p[3] , $v);
                        }
                    }
                    elseif( $fcnt == 4 && $totdv==4 ) {
                        $vc = ($v1+$v2+$v3+$v4)/4;

                        if( $v1p == $v2p && $v2p == $v3p && $v3p == $v4p ) {
                            // Four edge crossings (saddle point) of the same contour
                            // so we first need to
                            // find out how the saddle is crossing "/" or "\"

                            if( $this->GetNextHigherContourIdx($vc) == $this->GetNextHigherContourIdx($v1) ) {
                                // "\"
                                $color1 = $this->GetColor($v1);
                                $p1 = array($x1,$y1,$x1p,$y1p,$x4p,$y4p,$x1,$y1);

                                $color2 = $this->GetColor($v2);
                                $p2 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x3p,$y3p,$x4,$y4,$x4p,$y4p,$x1p,$y1p);

                                $color3 = $color1;
                                $p3 = array($x2p,$y2p,$x3,$y3,$x3p,$y3p,$x2p,$y2p);

                                $colorl1 = $this->GetColor($v1p);
                                $pl1 = array($x1p,$y1p,$x4p,$y4p);
                                $colorl2 = $this->GetColor($v2p);
                                $pl2 = array($x2p,$y2p,$x3p,$y3p);
                                $vl1 = $v1p; $vl2 = $v2p;

                            }
                            else {
                                // "/"
                                $color1 = $this->GetColor($v2);
                                $p1 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x1p,$y1p);

                                $color2 = $this->GetColor($v3);
                                $p2 = array($x1p,$y1p,$x2p,$y2p,$x3,$y3,$x3p,$y3p,$x4p,$y4p,$x1,$y1,$x1p,$y1p);

                                $color3 = $color1;
                                $p3 = array($x4p,$y4p,$x3p,$y3p,$x4,$y4,$x4p,$y4p);

                                $colorl1 = $this->GetColor($v1p);
                                $pl1 = array($x1p,$y1p,$x2p,$y2p);
                                $colorl2 = $this->GetColor($v4p);
                                $pl2 = array($x4p,$y4p,$x3p,$y3p);
                                $vl1 = $v1p; $vl2 = $v4p;
                            }
                        }
                        else {
                            // There are two different contours crossing so we need to find
                            // out which belongs to which
                            if( $v1p == $v2p ) {
                                // "/"
                                $color1 = $this->GetColor($v2);
                                $p1 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x1p,$y1p);

                                $color2 = $this->GetColor($v3);
                                $p2 = array($x1p,$y1p,$x2p,$y2p,$x3,$y3,$x3p,$y3p,$x4p,$y4p,$x1,$y1,$x1p,$y1p);

                                $color3 = $this->GetColor($v4);
                                $p3 = array($x4p,$y4p,$x3p,$y3p,$x4,$y4,$x4p,$y4p);

                                $colorl1 = $this->GetColor($v1p);
                                $pl1 = array($x1p,$y1p,$x2p,$y2p);
                                $colorl2 = $this->GetColor($v4p);
                                $pl2 = array($x4p,$y4p,$x3p,$y3p);
                                $vl1 = $v1p; $vl2 = $v4p;
                            }
                            else { //( $v1p == $v4p )
                                // "\"
                                $color1 = $this->GetColor($v1);
                                $p1 = array($x1,$y1,$x1p,$y1p,$x4p,$y4p,$x1,$y1);

                                $color2 = $this->GetColor($v2);
                                $p2 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x3p,$y3p,$x4,$y4,$x4p,$y4p,$x1p,$y1p);

                                $color3 = $this->GetColor($v3);
                                $p3 = array($x2p,$y2p,$x3,$y3,$x3p,$y3p,$x2p,$y2p);

                                $colorl1 = $this->GetColor($v1p);
                                $pl1 = array($x1p,$y1p,$x4p,$y4p);
                                $colorl2 = $this->GetColor($v2p);
                                $pl2 = array($x2p,$y2p,$x3p,$y3p);
                                $vl1 = $v1p; $vl2 = $v2p;
                            }
                        }
                        $this->FillPolygon($color1,$p1);
                        $this->FillPolygon($color2,$p2);
                        $this->FillPolygon($color3,$p3);

                        if( $this->showcontlines ) {
                            if( $this->fillContour ) {
                                $this->Line($this->contlinecolor, $pl1);
                                $this->Line($this->contlinecolor, $pl2);
                            }
                            else {
                                $this->Line($colorl1, $pl1);
                                $this->Line($colorl2, $pl2);
                            }
                        }
                        if( $this->showlabels ) {
                            if( !$this->showcontlines ) {
                                $this->Translate($pl1);
                                $this->Translate($pl2);
                            }
                            $this->PutLabel( ($pl1[0]+$pl1[2])/2, ($pl1[1]+$pl1[3])/2, $pl1[2], $pl1[3], $vl1);
                            $this->PutLabel( ($pl2[0]+$pl2[2])/2, ($pl2[1]+$pl2[3])/2, $pl2[2], $pl2[3],$vl2);
                        }
                    }
                }
                else {
                    $vc = ($v1+$v2+$v3+$v4)/4;
                    $xc = ($x1+$x4)/2;
                    $yc = ($y1+$y2)/2;

                    // Top left
                    $this->RectFill(($v1+$v2)/2, $v2, ($v2+$v3)/2, $vc,
                                    $x1,$yc, $x2,$y2, $xc,$y2, $xc,$yc, $depth+1);
                    // Top right
                    $this->RectFill($vc, ($v2+$v3)/2, $v3, ($v3+$v4)/2,
                                    $xc,$yc, $xc,$y2, $x3,$y3, $x3,$yc, $depth+1);

                    // Bottom left
                    $this->RectFill($v1, ($v1+$v2)/2, $vc, ($v1+$v4)/2,
                                    $x1,$y1, $x1,$yc, $xc,$yc, $xc,$y4, $depth+1);

                    // Bottom right
                    $this->RectFill(($v1+$v4)/2, $vc, ($v3+$v4)/2, $v4,
                                    $xc,$y1, $xc,$yc, $x3,$yc, $x4,$y4, $depth+1);

                }
            }
        }
    }

    /**
     * Search recursively for contour lines and do possible fills using a
     * triangular mesh.
     *
     * @param <type> $v1
     * @param <type> $v2
     * @param <type> $v3
     * @param <type> $x1
     * @param <type> $y1
     * @param <type> $x2
     * @param <type> $y2
     * @param <type> $x3
     * @param <type> $y3
     * @param <type> $depth
     */
    function TriFill($v1,$v2,$v3,$x1,$y1,$x2,$y2,$x3,$y3,$depth) {
        if( $depth > $this->maxdepth ) {
            // Abort and just appoximate the color of this area
            // with the average of the three values
            $color = $this->GetColor(($v1+$v2+$v3)/3);
            $p = array($x1, $y1, $x2, $y2, $x3, $y3, $x1, $y1);
            $this->FillPolygon($color,$p) ;
        }
        else {
            // In order to avoid some real unpleasentness in case a vertice is exactly
            // the same value as a contour we pertuberate them so that we do not end up
            // in udefined situation. This will only affect the calculations and not the
            // visual appearance

            $dummy=0;
            $this->Pertubate($v1,$v2,$v3,$dummy);

            $fcnt = 0 ;
            $vv1 = $this->GetNextHigherContourIdx($v1);
            $vv2 = $this->GetNextHigherContourIdx($v2);
            $vv3 = $this->GetNextHigherContourIdx($v3);
            $eps = 0.0001;

            if( $vv1 == $vv2 && $vv2 == $vv3 ) {
                $color = $this->GetColor($v1);
                $p = array($x1, $y1, $x2, $y2, $x3, $y3, $x1, $y1);
                $this->FillPolygon($color,$p) ;
            }
            else {
                $dv1 = abs($vv1-$vv2);
                $dv2 = abs($vv2-$vv3);
                $dv3 = abs($vv1-$vv3);

                if( $dv1 == 1 ) {
                    list($x1p,$y1p,$v1p) = $this->interp2($x1,$y1,$x2,$y2,$v1,$v2);
                    $fcnt++;
                }
                else {
                    $x1p = ($x1+$x2)/2;
                    $y1p = ($y1+$y2)/2;
                    $v1p = ($v1+$v2)/2;
                }

                if( $dv2 == 1 ) {
                    list($x2p,$y2p,$v2p) = $this->interp2($x2,$y2,$x3,$y3,$v2,$v3);
                    $fcnt++;
                }
                else {
                    $x2p = ($x2+$x3)/2;
                    $y2p = ($y2+$y3)/2;
                    $v2p = ($v2+$v3)/2;
                }

                if( $dv3 == 1 ) {
                    list($x3p,$y3p,$v3p) = $this->interp2($x3,$y3,$x1,$y1,$v3,$v1);
                    $fcnt++;
                }
                else {
                    $x3p = ($x3+$x1)/2;
                    $y3p = ($y3+$y1)/2;
                    $v3p = ($v3+$v1)/2;
                }

                if( $fcnt == 2 &&
                    ((abs($v1p-$v2p) < $eps && $dv1 ==1 && $dv2==1 ) ||
                    (abs($v1p-$v3p) < $eps && $dv1 ==1 && $dv3==1 ) ||
                    (abs($v2p-$v3p) < $eps && $dv2 ==1 && $dv3==1 )) ) {

                    // This means that the contour line crosses exactly two sides
                    // and that the values of each vertice is such that only this
                    // contour line will cross this section.
                    // We can now be smart. The cotour line will simply divide the
                    // area in two polygons that we can fill and then return. There is no
                    // need to recurse.

                    // First find out which two sides the contour is crossing
                    if( abs($v1p-$v2p) < $eps ) {
                        $p4 = array($x1,$y1,$x1p,$y1p,$x2p,$y2p,$x3,$y3,$x1,$y1);
                        $color4 = $this->GetColor($v1);

                        $p3 = array($x1p,$y1p,$x2,$y2,$x2p,$y2p,$x1p,$y1p);
                        $color3 = $this->GetColor($v2);

                        $p = array($x1p,$y1p,$x2p,$y2p);
                        $color = $this->GetColor($v1p);
                        $v = $v1p;
                    }
                    elseif( abs($v1p-$v3p) < $eps ) {
                        $p4 = array($x1p,$y1p,$x2,$y2,$x3,$y3,$x3p,$y3p,$x1p,$y1p);
                        $color4 = $this->GetColor($v2);

                        $p3 = array($x1,$y1,$x1p,$y1p,$x3p,$y3p,$x1,$y1);
                        $color3 = $this->GetColor($v1);

                        $p = array($x1p,$y1p,$x3p,$y3p);
                        $color = $this->GetColor($v1p);
                        $v = $v1p;
                    }
                    else {
                        $p4 = array($x1,$y1,$x2,$y2,$x2p,$y2p,$x3p,$y3p,$x1,$y1);
                        $color4 = $this->GetColor($v2);

                        $p3 = array($x3p,$y3p,$x2p,$y2p,$x3,$y3,$x3p,$y3p);
                        $color3 = $this->GetColor($v3);

                        $p = array($x3p,$y3p,$x2p,$y2p);
                        $color = $this->GetColor($v3p);
                        $v = $v3p;
                    }
                    $this->FillPolygon($color4,$p4);
                    $this->FillPolygon($color3,$p3);

                    if( $this->showcontlines ) {
                        if( $this->fillContour ) {
                            $this->Line($this->contlinecolor,$p);
                        }
                        else {
                            $this->Line($color,$p);
                        }
                    }
                    if( $this->showlabels ) {
                        if( !$this->showcontlines ) {
                            $this->Translate($p);
                        }
                        $this->PutLabel( ($p[0]+$p[2])/2, ($p[1]+$p[3])/2, $p[2], $p[3], $v);
                    }
                }
                else {
                    $this->TriFill($v1, $v1p, $v3p, $x1, $y1, $x1p, $y1p, $x3p, $y3p, $depth+1);
                    $this->TriFill($v1p, $v2, $v2p, $x1p, $y1p, $x2, $y2, $x2p, $y2p, $depth+1);
                    $this->TriFill($v3p, $v1p, $v2p, $x3p, $y3p, $x1p, $y1p, $x2p, $y2p, $depth+1);
                    $this->TriFill($v3p, $v2p, $v3, $x3p, $y3p, $x2p, $y2p, $x3, $y3, $depth+1);
                }
            }
        }
    }

    /**
     * Determine contour values and colors if the user hasn't
     * manually specified them
     */
    function SetupContourValues() {

        if( is_array($this->contval) && count($this->contval) == 0 ) {
            // Determine the isobar values automatically
            list($min,$max) = $this->GetMinMaxVal();
            $stepSize = ($max-$min) / $this->nContours ;
            $isobar = $min+$stepSize/2;
            for ($i = 0; $i < $this->nContours; $i++) {
                $this->contval[$i] = $isobar;
                $isobar += $stepSize;
            }
        }

        if( count($this->contcolors) == 0 ) {
            // No contour colors specifeid so detrmine them automatically
            $this->CalculateColors();
        }
        
        // Finally return the used contour values and colors
        return array($this->contval,$this->contcolors);
    }

    /**
     * Main entry point to find contours and do a possible contour fill
     * @param $meshdata input data
     * @param $maxdepth maximu search depth
     * @param $method Which method to use, either 'rect' = rectangular
     * submesh division or 'tri' = triangular submesh division
     */
    function Fillmesh($img, $xscale, $yscale, $maxdepth, $method='tri') {

        $this->img = $img;
        $this->xscale = $xscale;
        $this->yscale = $yscale;
        $this->maxdepth = $maxdepth;

        // Check that the user has specified anough colors
        $nc = $this->nContours+1;
            
        if( count($this->contcolors) != $nc ) {
            JpGraphError::RaiseL(28002);
        }

        // Now loop through all the initial submesh detrmiend by the input data
        // depending on the values and the contours these high level mesh might be
        // further subdivided.
        for( $x=0; $x < $this->nCols-1; ++$x ) {
            for( $y=0; $y < $this->nRows-1; ++$y ) {
                if( $this->invert ) {
                    $v1 = $this->data[$this->nRows-$y-1][$x];
                    $v2 = $this->data[$this->nRows-$y-1][$x+1];
                    $v3 = $this->data[$this->nRows-$y-2][$x+1];
                    $v4 = $this->data[$this->nRows-$y-2][$x];
                }
                else {
                    $v1 = $this->data[$y][$x];
                    $v2 = $this->data[$y][$x+1];
                    $v3 = $this->data[$y+1][$x+1];
                    $v4 = $this->data[$y+1][$x];
                }
                if( $method == 'tri' ) {
                    if( $this->invert ) {
                        // To make the flipped contour visually the same as the non-flipped
                        // we must also change how the triangle are cut , i.e. "/" vs "\"
                        // since if you flip "/" horizontally you get "\"
                        $this->TriFill($v3, $v4, $v1, $x+1, $y+1, $x, $y+1, $x, $y, 0);
                        $this->TriFill($v3, $v1, $v2, $x+1, $y+1, $x, $y, $x+1, $y, 0);
                    }
                    else {
                        // Fill upper and lower triangle
                        $this->TriFill($v4, $v1, $v2, $x, $y+1, $x, $y, $x+1, $y, 0);
                        $this->TriFill($v4, $v2, $v3, $x, $y+1, $x+1, $y, $x+1, $y+1, 0);
                    }
                }
                else {
                    $this->RectFill($v4, $v1, $v2, $v3, $x, $y+1, $x, $y, $x+1, $y, $x+1, $y+1, 0);
                }
            }
        }
        
        // Draw the labels on top of the contours at the end
        if( $this->showlabels ) { 
            $this->StrokeLabels();
        }

    }
}

/**
 * This class represent a plotting of a filled contour for data given as
 * a X-Y matrice
 *
 */
class FilledContourPlot extends Plot {

    /*
     * Contour values, colors and numerb of contours
     */
    private $contourVal, $contourColor, $nbrCountours = 0 ;

    /*
     * Contour data
     */
    private $dataMatrix = array();

    /*
     * If the legend should be inverted, teh lowest value at top
     */
    private $invertLegend = false;

    /*
     * Data interpolation factor
     */
    private $interpFactor = 1;

    /*
     * If input data should be flipped vertically
     */
    private $flipData = false;

    /*
     * If legend should be displayed or not
     */
    private $showLegend=false;

    /*
     * Macimu recursive depth
     */
    private $maxdepth=6;

    /*
     * Rectangualr or triangualr sub-division method
     */
    private $method='rect';

    /*
     * true/false if the contour should be filled or not
     */
    private $filled = false;

    /*
     * An instance of the contour plot algorithm
     */
    private $cw = null; 

    /**
     * Construct a contour plotting algorithm. The end result of the algorithm is a sequence of
     * line segments for each isobar given as two vertices.
     *
     * @param $aDataMatrix    The Z-data to be used
     * @param $aIsobar A mixed variable, if it is an integer then this specified the number of isobars to use.
     * The values of the isobars are automatically detrmined to be equ-spaced between the min/max value of the
     * data. If it is an array then it explicetely gives the isobar values
     * @param $aInvert By default the matrice with row index 0 corresponds to Y-value 0, i.e. in the bottom of
     * the plot. If this argument is true then the row with the highest index in the matrice corresponds  to
     * Y-value 0. In affect flipping the matrice around an imaginary horizontal axis.
     * @param $aHighContrast Use high contrast colors (blue/red:ish)
     * @param $aHighContrastBW Use only black colors for contours
     * @return an instance of the contour plot algorithm
     */
    function __construct($aDataMatrix, $aIsobar=10, $aFactor=1, $aInvert=false, $aIsobarColors=array()) {

        $this->dataMatrix = $aDataMatrix;
        $this->flipData = $aInvert;
        $this->interpFactor = $aFactor;

        if ( $this->interpFactor > 1 ) {

            if( $this->interpFactor > 5 ) {
                JpGraphError::RaiseL(28007);// ContourPlot interpolation factor is too large (>5)
            }

            $ip = new MeshInterpolate();
            $this->dataMatrix = $ip->Linear($this->dataMatrix, $this->interpFactor);
        }

        if( is_array($aIsobar) ) {
            $this->nbrContours = count($aIsobar);
        }
        else {
            $this->nbrContours = $aIsobar;
        }

        $this->cw = new ContourWorker($aDataMatrix,$aIsobar);
        $this->cw->SetContColors($aIsobarColors);
        $this->cw->SetInvert($aInvert);

    }

    /**
     * Determine if isobaar labels should be shown
     *
     * @param $aFlg true or false
     *
     */
     function ShowLabels($aFlg=true,$aAngledLabels=true) {
        $this->cw->ShowLabels($aFlg,$aAngledLabels);
     }

    /**
     * Determine if contour should be filled
     *
     * @param $aFlg true or false
     *
     */
     function SetFilled($aFlg=true) {
         $this->cw->SetFilled($aFlg);
     }

    /**
     * Determine if the adaptive triangulation should be displayed
     *
     * @param $aFlg true or false
     *
     */
     function ShowTriangulation($aFlg=true) {
        $this->cw->ShowTriangulation($aFlg);
     }

    /**
     * Determine if the isobar lines should be visible
     *
     * @param $aFlg true or false
     *
     */
     function ShowLines($aFlg=true,$aColorWhenFilled='black') {
         $this->cw->ShowLines($aFlg, $aColorWhenFilled);
     }

    /**
     * Detrmine method for interpolation, rectangualar of triangualr grid
     *
     * @param $aMethod, can be either 'rect' or 'tri'
     *
     */
     function SetMethod($aMethod) {
         $this->method = $aMethod;
     }

    /**
     * Flipe the data around the center
     *
     * @param $aFlg
     *
     */
    function SetInvert($aFlg=true) {
        $this->cw->SetInvert($aFlg);
    }

    /**
     * Set the colors for the isobar lines
     *
     * @param $aColorArray
     *
     */
    function SetIsobarColors($aColorArray) {
        $this->cw->SetContColors($aColorArray);
    }

    /**
     * Show the legend
     *
     * @param $aFlg true if the legend should be shown
     *
     */
    function ShowLegend($aFlg=true) {
        $this->showLegend = $aFlg;
    }


    /**
     * @param $aFlg true if the legend should start with the lowest isobar on top
     * @return unknown_type
     */
    function Invertlegend($aFlg=true) {
        $this->invertLegend = $aFlg;
    }

    /* Internal method. Give the min value to be used for the scaling
     *
     */
    function Min() {
        return array(0,0);
    }

    /* Internal method. Give the max value to be used for the scaling
     *
     */
    function Max() {
        return array(count($this->dataMatrix[0])-1,count($this->dataMatrix)-1);
    }

    /**
     * Internal ramewrok method to setup the legend to be used for this plot.
     * @param $aGraph The parent graph class
     */
    function Legend($aGraph) {

        if( ! $this->showLegend )
            return;

        if( $aGraph->legend->font_family <= FF_FONT2+1 ) {
            $lte = "<=";
        }
        else {
            $lte = SymChar::Get('lte');
        }
        if( $this->invertLegend ) {
            for ($i = 0; $i < $this->nbrContours; $i++) {
                $aGraph->legend->Add(sprintf($lte.' %.1f',$this->contourVal[$i]), $this->contourColor[$i]);
            }
        }
        else {
            for ($i = $this->nbrContours-1; $i >= 0 ; $i--) {
                $aGraph->legend->Add(sprintf($lte.' %.1f',$this->contourVal[$i]), $this->contourColor[$i]);
            }
        }
    }

    /**
     * Set label font properties
     *
     * @param $aFFamily Font family
     * @param $aFStyle Font style
     * @param $aFSize Font size
     */
    function SetFont($aFFamily,$aFStyle=FS_NORMAL,$aFSize=10) {
        $this->cw->SetFont($aFFamily, $aFStyle, $aFSize);
    }

    /**
     * Set font color for labels
     * @param $aColor Color specification
     */
    function SetFontColor($aColor) {
        $this->cw->SetFontColor($aColor);
    }

    /**
     *  Framework function which gets called before the Stroke() method is called
     *
     *  @see Plot#PreScaleSetup($aGraph)
     *
     */
    function PreScaleSetup($aGraph) {
        $xn = count($this->dataMatrix[0])-1;
        $yn = count($this->dataMatrix)-1;

        $aGraph->xaxis->scale->Update($aGraph->img,0,$xn);
        $aGraph->yaxis->scale->Update($aGraph->img,0,$yn);

        list($this->contourVal,$this->contourColor) = $this->cw->SetupContourValues();
    }

    /**
     * Use high contrast color schema
     *
     * @param $aFlg True, to use high contrast color
     * @param $aBW True, Use only black and white color schema
     */
    function UseHighContrastColor($aFlg=true,$aBW=false) {
        $this->cw->UseHighContrastColor($aFlg,$aBW);
    }

    /**
     * Internal method. Stroke the contour plot to the graph
     *
     * @param $img Image handler
     * @param $xscale Instance of the xscale to use
     * @param $yscale Instance of the yscale to use
     */
    function Stroke($img,$xscale,$yscale) {
        $img->SetLineWeight($this->line_weight);        
        $this->cw->Fillmesh($img, $xscale, $yscale, $this->maxdepth, $this->method);
    }
}

// EOF
?>
