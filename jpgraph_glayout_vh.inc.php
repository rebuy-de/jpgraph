<?php
/*=======================================================================
 // File:        JPGRAPH_GLAYOUT_VH.INC.PHP
 // Description: JpGraph Layout classes for automatic layout of
 //              objects in a graph. The layout is a simple even division
 //              depending on the objects sizes
 // Created:     2009-07-08
 // Ver:         $Id: jpgraph_glayout_vh.inc.php 1709 2009-07-30 08:00:08Z ljp $
 //
 // Copyright (c) Asial Corporation. All rights reserved.
 //========================================================================
 */

class LayoutRect {
    protected $icx=0.5, $icy=0.5;
    protected $iwidth = -1, $iheight = -1;

    public function __construct($width,$height)  {
        $this->iwidth = $width;
        $this->iheight = $height;
    }

    public function SetCenterPos($x, $y) {
        $this->icx = $x;
        $this->icy = $y;
    }
    public function getWidth($aImg) {
        return $this->iwidth;
    }
    public function getHeight($aImg) {
        return $this->iheight;
    }
    public function Stroke($graph) {
    }
  }

class LayoutHor extends LayoutRect {

    protected $iobj = array();

    public function __construct($obj) {
        $this->iobj = $obj;
    }

    public function getHeight($aImg) {
        if( $this->iheight > -1 )
            return $this->iheight;

        $n = count($this->iobj);
        $height = 0;
        for( $i=0; $i < $n; ++$i ) {
            $height = max( $height, $this->iobj[$i]->getHeight($aImg) );
        }
        $this->iheight = $height;
        return $height;
    }

    public function getWidth($aImg) {
        if( $this->iwidth > -1 )
            return $this->iwidth;

        $n = count($this->iobj);
        $width = 0;
        for( $i=0; $i < $n; ++$i ) {
            $width += $this->iobj[$i]->getWidth($aImg);
        }
        $this->iwidth = $width;
        return $width;
    }

    public function Stroke($graph) {

        if( $this->icx > 0 && $this->icx < 1 ) {
            $this->icx = round( $graph->img->width * $this->icx ) ;
        }

        if( $this->icy > 0 && $this->icy < 1 ) {
            $this->icy = round( $graph->img->height * $this->icy ) ;
        }

        //echo "cx={$this->icx}, cy={$this->icy}<br>";

        $n = count($this->iobj);
        $xstart = $this->icx - $this->getWidth($graph->img)/2.0;
        for( $i=0; $i < $n; ++$i ) {
            $obj = $this->iobj[$i];
            $w = $obj->getWidth($graph->img);
            //echo "w=$w, x1=".($xstart + $w/2.0).", y1=".($this->icy)."<br>";
            $obj->SetCenterPos( $xstart + $w/2.0, $this->icy );
            $xstart += $w;
            $obj->Stroke($graph);
        }
    }
}

class LayoutVert extends LayoutRect {

    public function __construct($obj) {
        $this->iobj = $obj;
    }

    public function getWidth($aImg) {
        if( $this->iwidth > -1 )
            return $this->iwidth;

        $n = count($this->iobj);
        $width = 0;
        for( $i=0; $i < $n; ++$i ) {
            $width = max( $width, $this->iobj[$i]->getWidth($aImg) );
        }
        $this->iwidth = $width;
        return $width;
    }

    public function getHeight($aImg) {
        if( $this->iheight > -1 )
            return $this->iheight;

        $n = count($this->iobj);
        $height = 0;
        for( $i=0; $i < $n; ++$i ) {
            $height += $this->iobj[$i]->getHeight($aImg);
        }
        $this->iheight = $height;
        return $height;
    }

    public function Stroke($graph) {

        if( $this->icx > 0 && $this->icx < 1 ) {
            $this->icx = round( $graph->img->width * $this->icx ) ;
        }

        if( $this->icy > 0 && $this->icy < 1 ) {
            $this->icy = round( $graph->img->height * $this->icy ) ;
        }

        $n = count($this->iobj);
        $ystart = $this->icy - $this->getHeight($graph->img)/2.0;
        for( $i=0; $i < $n; ++$i ) {
            $obj = $this->iobj[$i];
            $h = $obj->getHeight($graph->img);
            $obj->SetCenterPos( $this->icx, $ystart + $h/2.0 );
            $ystart += $h;
            $obj->Stroke($graph);
        }
    }
}

?>
