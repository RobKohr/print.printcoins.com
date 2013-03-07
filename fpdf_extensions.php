<?php
require_once('fpdf.php');

class VariableStream
{
    var $varname;
    var $position;
	
    function stream_open($path, $mode, $options, &$opened_path)
    {
        $url = parse_url($path);
        $this->varname = $url['host'];
        if(!isset($GLOBALS[$this->varname]))
			{
				trigger_error('Global variable '.$this->varname.' does not exist', E_USER_WARNING);
				return false;
			}
        $this->position = 0;
        return true;
    }

    function stream_read($count)
    {
        $ret = substr($GLOBALS[$this->varname], $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    function stream_eof()
    {
        return $this->position >= strlen($GLOBALS[$this->varname]);
    }

    function stream_tell()
    {
        return $this->position;
    }

    function stream_seek($offset, $whence)
    {
        if($whence==SEEK_SET)
			{
				$this->position = $offset;
				return true;
			}
        return false;
    }
    
    function stream_stat()
    {
        return array();
    }
}

////pdf class

class PDF extends FPDF
{
	var $use_cache_images = true;
	function UseCachedImages($bool=true){
		$this->use_cache_images = $bool;
	}
	function QR($str, $tmp_dir, $x, $y, $w, $h){
		$file = md5($str).'.png';
		$file = $tmp_dir.$file;
		if(!$str)
			return;
		$qr = QRcode::png($str, $file, null, 10, 1);
		$this->Image($file, $x, $y, $w, $h);
		unlink($file);
	}


	function SetDrawColor($a, $b=null, $c=null){
		if(is_array($a)){
			$b = $a[1];
			$c = $a[2];
			$a = $a[0];
		}
		if($b == null)
			$b = $c = $a;
		return parent::SetDrawColor($a, $b, $c);
	}


	function SetTextColor($a, $b=null, $c=null){
		if(is_array($a)){
			$b = $a[1];
			$c = $a[2];
			$a = $a[0];
		}
		if($b == null)
			$b = $c = $a;
		return parent::SetTextColor($a, $b, $c);
	}

	function SetFillColor($a, $b=null, $c=null){
		if(is_array($a)){
			$b = $a[1];
			$c = $a[2];
			$a = $a[0];
		}
		if($b == null)
			$b = $c = $a;
		return parent::SetFillColor($a, $b, $c);
	}

	function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link=''){
		if(!$this->use_cache_images){
			unset($this->images[$file]);
		}
		return parent::Image($file, $x, $y, $w, $h, $type, $link);
	}

	function Circle($x, $y, $r, $style='D')
	{
		$this->Ellipse($x,$y,$r,$r,$style);
	}

	function Ellipse($x, $y, $rx, $ry, $style='D')
	{
		if($style=='F')
			$op='f';
		elseif($style=='FD' || $style=='DF')
			$op='B';
    else
        $op='S';
		$lx=4/3*(M_SQRT2-1)*$rx;
		$ly=4/3*(M_SQRT2-1)*$ry;
		$k=$this->k;
		$h=$this->h;
		$this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
							($x+$rx)*$k,($h-$y)*$k,
							($x+$rx)*$k,($h-($y-$ly))*$k,
							($x+$lx)*$k,($h-($y-$ry))*$k,
							$x*$k,($h-($y-$ry))*$k));
		$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
							($x-$lx)*$k,($h-($y-$ry))*$k,
							($x-$rx)*$k,($h-($y-$ly))*$k,
							($x-$rx)*$k,($h-$y)*$k));
		$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
							($x-$rx)*$k,($h-($y+$ly))*$k,
							($x-$lx)*$k,($h-($y+$ry))*$k,
							$x*$k,($h-($y+$ry))*$k));
		$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
							($x+$lx)*$k,($h-($y+$ry))*$k,
							($x+$rx)*$k,($h-($y+$ly))*$k,
							($x+$rx)*$k,($h-$y)*$k,
							$op));
	}
	function PDF_MemImage($orientation='P', $unit='mm', $format='A4')
    {
        $this->FPDF($orientation, $unit, $format);
        //Register var stream protocol
        stream_wrapper_register('var', 'VariableStream');
    }

    function MemImage($data, $x=null, $y=null, $w=0, $h=0, $link='')
    {
        //Display the image contained in $data
        $v = 'img'.md5($data);
        $GLOBALS[$v] = $data;
        $a = getimagesize('var://'.$v);
        if(!$a)
            $this->Error('Invalid image data');
        $type = substr(strstr($a['mime'],'/'),1);
        $this->Image('var://'.$v, $x, $y, $w, $h, $type, $link);
        unset($GLOBALS[$v]);
    }

    function GDImage($im, $x=null, $y=null, $w=0, $h=0, $link='')
    {
        //Display the GD image associated to $im
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        $this->MemImage($data, $x, $y, $w, $h, $link);
    }
	var $angle=0;

	function Rotate($angle, $x=-1, $y=-1)
	{
		if($x==-1)
			$x=$this->x;
		if($y==-1)
			$y=$this->y;
		if($this->angle!=0)
			$this->_out('Q');
		$this->angle=$angle;
		if($angle!=0)
			{
				$angle*=M_PI/180;
				$c=cos($angle);
				$s=sin($angle);
				$cx=$x*$this->k;
				$cy=($this->h-$y)*$this->k;
				$this->_out(sprintf('q %.5f %.5f %.5f %.5f %.2f %.2f cm 1 0 0 1 %.2f %.2f cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
			}
	}

	function _endpage()
	{
		if($this->angle!=0)
			{
				$this->angle=0;
				$this->_out('Q');
			}
		parent::_endpage();
	}
}


?>
