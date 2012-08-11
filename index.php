<?php
require_once('fpdf.php');
require_once('fpdf_extensions.php');
require_once('lib/phpqrcode/qrlib.php');
require_once('utils.php');
$s->bill_w = 6.14;
$s->bill_h = 2.61;
if($_REQUEST['card']){
	$s->bill_w = 3.5;
	$s->bill_h = 2;
 }
$s->draw = 100;//light draw color
$s->l_w = .008;
$s->page_width = 8.5;
$s->page_height = 11;
$s->margin_top_bottom = 0.10;
$s->margin_left_right = 0.5;
$s->page_usable_width = $s->page_width - ($s->margin_left_right*2);
$s->page_usable_height = $s->page_height - ($s->margin_top_bottom*2);
$s->bill_pad = 0.2;
$s->shadow=.02;
$s->shadow_c=80;

//Source (div by 100) http://boardgames.about.com/od/poker/a/chip_denoms.htm 
$s->colors = array(
				   '0.01'=>array(255, 255, 255), 
				   //				   '0.02'=>array(255, 255, 0), 
				   '0.05'=>array(255, 0, 0), 
				   '0.10'=>array(0, 0, 255),
				   //				   '0.20'=>array(127, 127, 127),
				   '0.25'=>array(0, 255, 0),
				   '0.50'=>array(255, 165, 0),
				   'open'=>array(255,217,0),
				   '1'=>array(0, 0, 0),
				   '5'=>array(160, 32, 240),
				   '10'=>array(159, 139, 112),//beaver brown
				   '20'=>array(173, 216, 230),
				   '50'=>array(165, 42, 42),
				   '100'=>array(255, 215, 0)//Gold (not a normal poker chip)
);

$s->icon = 'bitcoin_450x450.png';
$s->crypto = 'IN CRYPTOGRAPHY WE TRUST';
if($_REQUEST['client']=='IBB'){
	$s->icon = 'IBBlogo3agreen.png';
	$f = 2.5;
	$s->icon_w = $f * 807/807;
	$s->icon_h = $f * 501/807;
	//	$s->sample = 1;
	$s->crypto = 'Decentralized P2P Digital Currency';
	$s->hide_vires = 1;
	$s->hide_printcoins = 1;
	$s->bottom_message = 'Interest Free Banking In Your Interest';
 }

if($_REQUEST['client']=='ronpaul'){
	$s->icon = 'ronpaul.png';
	$f = 2;
	$s->icon_w = $f * 600/840;
	$s->icon_h = $f * 840/840;
	$s->crypto = 'END THE FED';
	$s->hide_vires = 1;
 }


$pdf = new PDF('P','in','Letter');

$pdf->SetMargins($s->margin_top_bottom, $s->margin_left_right);
$pdf->SetAutoPageBreak(false);
if($_REQUEST['squares']){
	squares();
	exit;
 }

$p_c = $s->page_width/2;
$off_x = $p_c - $s->bill_w/2;
$off_y = 99999999;
if(!$_REQUEST['bills']){
	include('form.html');
	exit;
 }
$bills = str_replace('"', '', $_REQUEST['bills']);
$bills = str_replace('\\', '', $bills);
$bills = explode("\n", $bills);
$printer = $_REQUEST['printer'];	
if(!$printer){
 echo "ERROR no printer set";
 exit;
 }
$printer = "Printed By: ".$printer;
$bills = array_slice($bills, 0, 50);//limit number of bills
foreach($bills as $bill){
	$off_y +=$s->bill_h;
	if($off_y>($s->page_height-$s->bill_h)){
		$pdf->AddPage();
		$off_y = $s->margin_top_bottom;
	}
	$reverse=$_REQUEST['reverse'];
	$pdf->SetXY($off_x, $off_y);		
	$bill = explode(',', $bill);
	$pub = $bill[1];
	$priv = $bill[2];
	if($bill[3])
		$amount = trim($bill[3]);
	if(!$amount)
		$amount = 'open';
	if(!$pub)
		continue;
	if(!$priv)
		continue;
	bill(array('pub'=>$pub, 'priv'=>$priv, 'printer'=>$printer, 
			   'amount'=>$_REQUEST['amount'], 'reverse'=>$reverse, 'funded_by', $funded_by));
	
}
$pdf->Output();

function squares(){
	$width = .6;
	global $s, $pdf;
	$pdf->AddPage();
	$pdf->SetDrawColor(0,0,0);
	for($i = 0; $i<($s->page_width/$width); $i++){
		$j =1;
		for($j = 0; $j<($s->page_height/$width); $j++){
			$x = $i * $width;
			$y = $j * $width;
			$pdf->Rect($x, $y, $width, $width, 'D');

		}
	}
	$pdf->Output();
}

function bill($params){
	global $s, $pdf;
	$bill = (object) array_merge(
								 $params,
								 array(
									   'x'=>$pdf->GetX(),
									   'y'=>$pdf->getY(),
									   'w'=>$s->bill_w,
									   'h'=>$s->bill_h,
									   'shadow_c'=>$s->shadow_c,
									   'shadow'=>$s->shadow,
									   'font'=>'Helvetica'
									   )
								 );
	$bill->s = $s;
	$bill->pdf = $pdf;
	set_is_card($bill);
	bill_init($bill);
	bill_reset($bill);

	if($bill->reverse){
		leave_your_mark($bill);
		return;
	}
	bill_background($bill);	
	bill_cuts($bill);
	color_borders($bill);
	bill_denominations($bill);

	bitcoin_image($bill);
	printer_info($bill);
	private_and_public_qr($bill);
	
	if($bill->amount=='open'){
		$pdf->SetFont($bill->font,'',7);
		quick_text($bill, ' Funding amount',
				   $bill->y+0.65, 'L');
	}

	$pdf->SetFont($bill->font,'I',8);	

	if(!$s->hide_vires){
		quick_text($bill, 'Vires in Numeris', 
				   $bill->y + $bill->h - $bill->p, 'C');
	}

	$pdf->SetFont($bill->font,'',5);	
	quick_text($bill, 'Version 2012B       ', $bill->y+$bill->p-.02, 'R');
	bitcoin_cheque_text($bill);
	checkmark_funded($bill);


	if($s->sample){
		show_sample_mark($bill);
	}

	$pdf->SetFont($bill->font,'B', 10);
	$pdf->SetTextColor(73,146,65);
	quick_text($bill, $s->bottom_message, $bill->y+ $bill->h - $bill->p - .25, 'C');
	
	if($_REQUEST['client']=='ronpaul'){
		ron_paul_details($bill);
	}
}


/////Helper Functions

function bitcoin_cheque_text($bill){
	global $s, $pdf;
	$pdf->SetFont($bill->font,'',8);	
	if($bill->amount=='open'){
		$y = $bill->y+$bill->h-$bill->p - .3;
		$pdf->SetFont($bill->font,'B',6);
		$pdf->SetTextColor(0);		
		quick_text($bill, 'Bitcoin Cheque', $y, 'L');
		
		$pdf->SetFont($bill->font,'B',6);
		$pdf->SetTextColor(0);
		$txt = 'An easy way to give someone bitcoins';
		if($_REQUEST['client']=='IBB')
			$txt = 'An easy way to give physical bitcoins';
		quick_text($bill, $txt, $y+.07, 'L');
	}
}

function checkmark_funded($bill){
	global $pdf, $s;
	$txt = '[_] Checkmark here when bill has been funded.';
	quick_text($bill, $txt,
			   $bill->y + $bill->h - $bill->p, 'R');
	$pdf->SetFont('Courier','',8);
	quick_text($bill, $s->crypto, $bill->y+$bill->p, 'C');
}

function show_sample_mark($bill){
	global $pdf, $s;
	$pdf->SetFont('Courier','',18);
	quick_text($bill, 'SAMPLE    SAMPLE  SAMPLE   SAMPLE  SAMPLE', $bill->y+$bill->h*.2, 'C');
	quick_text($bill, 'SAMPLE    SAMPLE  SAMPLE   SAMPLE  SAMPLE', $bill->y+$bill->h*.5, 'C');
}

function ron_paul_details($bill){
	global $pdf, $s;
	$pdf->SetTextColor(0);
	$pdf->SetFont($bill->font,'I', 7);
	$a = '"A system of capitalism presumes sound money,';
	$b = 'not fiat money manipulated by a central bank."';
	$lh = .12;
	quick_text($bill, $a, $bill->y+ $bill->h - $bill->p - .25, 'C');
	quick_text($bill, $b, $bill->y+ $bill->h - $bill->p - .25+$lh, 'C');
	$pdf->SetFont($bill->font,'', 7);
	quick_text($bill, 'Ron Paul', $bill->y+ $bill->h - $bill->p - .25+$lh*2, 'C');
}

function bill_init($bill){
	global $s;
	if($bill->pub=='test'){
		$bill->amount = $bill->priv;
		$bill->priv = 'test';
		$bill->priv = 'http://printcoins.com/r/5HueCGU8rMjxEXxiPuD5BDku4MkFqeZyd4dZ1jvhTVqvbTLvyTJ';
		$bill->pub = 'Example Public key asdfljasdlfjlasjdflsadjf';
	}else{
		if(!$bill->amount){
			$url = 'http://blockexplorer.com/q/addressbalance/'.$bill->pub;
			$bill->amount = get_page($url);
		}
	}
	if($bill->amount=='open'){
		//do nothing
	}elseif($bill->amount < 1){
		$bill->amount = number_format($bill->amount, 2, '.', ',');
	}else{
		$bill->amount = number_format($bill->amount, 0, '.', ',');
	}
	$bill->color = $s->colors[$bill->amount];
	foreach($bill->color as $key=>$val){
		if($val==0)
			$bill->color[$key] = 1;//for some reason 0s were causing problem for setFillColor
	}
	$bill->p = .2;
	if($bill->card){
		$p = .15;
	}
}


function leave_your_mark($bill){
	global $pdf;
	$size = .6;
	$count_w = 5;
	$top = $bill->h/2 - $size;
	$left = $bill->w/2 - ($size * ($count_w/2));
	for($i = 0; $i<5; $i++){
		for($j = 0; $j < 2; $j++){
			$x = $left + $i*$size + $bill->x;
			$y = $top + $j*$size + $bill->y;
			$pdf->setXY($x, $y);
			$pdf->Rect($x, $y, $size, $size, 'D');			
		}
	}
	$pdf->SetFont($bill->font,'',7);
	$top = $bill->y + $top;
	quick_text($bill, 'Leave Your Mark', $top-.05, 'C');
	quick_text($bill, 'Upload photos of well traveled bills to:', $top+($size*2)+.07, 'C');
	quick_text($bill, 'www.flickr.com/groups/bitcoin/', $top+($size*2)+.18, 'C');
	$link = 'http://www.flickr.com/groups/bitcoin/';
	//	qr($bill, $link, $top+($size*2)+.16, 'C', .4, .4, false, -$size*1.6);
	
}


function check_balance($bill){
	global $pdf;
	$y = $bill->y+.9;
	$pdf->SetFont($font,'',5);
	quick_text($bill, 'Check balance link         ', $y+.10, 'L');
	$link = 'http://blockexplorer.com/q/addressbalance/'.$bill->pub;
	qr($bill, $link, $y, 'L', .6, 1, false);
}

function priv_block($bill){
	global $pdf;
	$y = $bill->y+.9;
	qr($bill, 'fill', $y, 'R', .6, 1, false);
	qr($bill, 'fill', $y, 'L', .6, 1, false);
}

function private_and_public_qr($bill){
	global $pdf;
	$p = $bill->p;
	$y = $bill->y+.6;
	$priv_y = $y+.3;
	$line_height = .08;
	$pdf->SetFont($font,'',5);
	quick_text($bill, 'Private Key (Used to transfer coins)', $priv_y-.05, 'L');
	qr($bill, $bill->priv, $priv_y, 'L', .6, 1.125);
	$size = .8;

	$y = $bill->y+.65;
	$nudge = 1.2;
	$link='http://blockexplorer.com/q/addressbalance/'.$bill->pub;
		$size = .6;
		quick_text($bill, 'Check Balance at this QR link', $y-$line_height*2, 'R');
		quick_text($bill, 'with a smartphone barcode scanner', $y-$line_height, 'R');
		qr($bill, $link, $y, 'R', .6, .6);
		$y = $bill->y+1.5;
		quick_text($bill, "Fund this bill at this public address", $y-$line_height*2, 'R');


	quick_text($bill, $bill->pub, $y-$line_height, 'R');
	qr($bill, $bill->pub, $y, 'R', $size, $size, 0);
}

function color_borders($bill){
	global $pdf;
	$pdf->SetFillColor($bill->color[0], $bill->color[1], $bill->color[2]);
	$cp = .4;//padding to corners
	$t = .06;//thickness
	$bp = .05;//padding to edges of bill
	$pdf->Rect($bill->x+$cp, $bill->y+$bp, $bill->w-($cp*2), $t, 'F');//top
	$pdf->Rect($bill->x+$cp, $bill->y+$bill->h-$t-$bp, $bill->w-($cp*2), $t, 'F');//bottom
	$pdf->Rect($bill->x+$bp, $bill->y+$cp, $t, $bill->h-($cp*2), 'F');//left
	$pdf->Rect($bill->x + $bill->w - $t - $bp, $bill->y+$cp, $t, $bill->h-($cp*2), 'F');//right
}

function printer_info($bill){
	global $pdf, $s;
	$p = $bill->p;
	$y = $bill->y + $bill->h - $p -.03;
	$pdf->SetFont($bill->font,'B',6);
	$pdf->SetTextColor(0);		
	if(!$s->hide_printcoins)
		quick_text($bill, "".$bill->printer, $y, 'L');
	$pdf->SetFont($bill->font,'',4);
	quick_text($bill, 'Convert back to digital bitcoins: http://printcoins.com/redeem', $y+.06);
}

function quick_text($bill, $text, $y, $align='L', $nudge_x=0){
	global $pdf;
	$x = $bill->x + $bill->p + $nudge_x;
	$pdf->SetXY($x, $y);
	$width = $bill->w - ($bill->p*2);
	$pdf->Cell($width, 0, $text, 0, 0, $align);
}

function qr($bill, $contents, $y, $align, $width, $frame_width, $show_frame=true, $nudge_x=0){
	global $pdf;
	$x = $bill->x + $bill->p;
	if($align=='R')
		$x = $bill->x + $bill->w - $bill->p - $frame_width;
	if($align=='C'){
		$x = $bill->x + $bill->w/2 - $frame_width/2;
	}
	$x = $x+$nudge_x;

	$pdf->SetXY($x, $y);
	if($show_frame)
		$pdf->Cell($frame_width, $frame_width, '', 1);
	$qr_p = ($frame_width - $width)/2;
	if($contents=='fill'){
		$pdf->SetFillColor(0);
		$pdf->Rect($x + $qr_p, $y + $qr_p, $width, $width, 'F');
	}else{
		$pdf->QR($contents, '', $x + $qr_p, $y + $qr_p, $width, $width);
	}
}


function bill_denominations($bill){
	global $pdf;
	$p = $bill->p;
	$font_size = 23;
	if($bill->card)
		$font_size = 18;
	$pdf->SetFont('Helvetica','B',$font_size);
	$pl = 's';//plaural
	if($bill->amount==1)
		$pl = '';
	$disp = $bill->amount;

	if($bill->amount == 'open'){
		$disp.=' Bitcoin'.$pl;
	    $disp = '____ Bitcoins';
		$main = $disp;
	}else{
		$main = $disp.' Bitcoin'.$pl;
	}

	//top left
	shadow_text($bill,
				$bill->y+$p + .2,
				$main,
				$bill->color, $bill->shadow_c, $bill->shadow);

	if($bill->amount =='open')
		return;

	//bottom right
	$pdf->SetFont($font,'B',20);
	shadow_text($bill,
				$bill->y + $bill->h - $p -.18,
				$disp.' BTC',
				$bill->color, $bill->shadow_c, $bill->shadow,
				'R');
	
	//top right
	$pdf->SetFont($font,'B',16);
	shadow_text($bill,
				$bill->y + $p+.12,
				$disp,
				$bill->color, $bill->shadow_c, $bill->shadow/2,
				'R');

	//bottom left
	$pdf->SetFont($font,'B',16);
	shadow_text($bill,
				$bill->y + $bill->h - $p -.18,
				$disp,
				$bill->color, $bill->shadow_c, $bill->shadow*.75,
				'L');
	$pdf->SetTextColor(0);

}

function shadow_text($bill, $y, $text, $color, $shadow_c, $shadow, $align = 'L'){
	global $pdf;
	$width = $bill->w - ($bill->p*2);
	$x = $bill->x + $bill->p;
	$pdf->SetTextColor($shadow_c);
	$pdf->SetXY($x+$shadow, $y+$shadow);
	$pdf->Cell($width, 0, $text, 0, 0, $align);
	$pdf->SetTextColor($color);
	$pdf->SetXY($x, $y);
	$pdf->Cell($width, 0, $text, 0, 0, $align);
}


function bill_reset($bill){
	global $s, $pdf;
	$pdf->SetLineWidth($s->l_w);
	$pdf->SetDrawColor($s->draw);
	$pdf->SetFillColor($bill->color);
}

function draw_frame($bill){
	bill_reset($bill);
	
}

function bill_background($bill){
	global $pdf;
	bill_reset($bill);
	$pdf->SetFillColor(
					   light($bill->color[0]), 
					   light($bill->color[1]), 
					   light($bill->color[2])
					   );
	$inset = -.05;
	$pdf->Rect($bill->x+$inset, $bill->y+$inset, $bill->w-$inset*2, $bill->h-$inset*2, 'F');
	bill_reset($bill);
}

function bill_cuts($bill){
	global $pdf, $s;
	bill_reset($bill);
	$pdf->SetDrawColor($s->draw);
	//top bottom of sheet
	$pdf->Line($bill->x, 0, $bill->x, $s->margin_top_bottom/2);
	$pdf->Line($bill->x+$bill->w, 0, $bill->x+$bill->w, $s->margin_top_bottom/2);

	//bill horz
	if($bill->y < 1){
		//do first line
		$pdf->Line(0, $bill->y, $bill->x-.1, $bill->y);
		$pdf->Line($bill->x+ $bill->w + .1, $bill->y, $s->page_width, $bill->y);
	}
	$pdf->Line(0, $bill->y+$bill->h, $bill->x-.1, $bill->y+$bill->h);
	$pdf->Line($bill->x+ $bill->w + .1, $bill->y+$bill->h, $s->page_width, $bill->y+$bill->h);
	
	//bill vert
	$y_s = .05;
	$pdf->Line($bill->x, $bill->y-$y_s, $bill->x, $bill->y+$y_s);
	$pdf->Line($bill->x+$bill->w, $bill->y-$y_s, $bill->x+$bill->w, $bill->y+$y_s);

}


function bill_center_text_amount($bill){
		$txt = denomination_to_words($bill->amount);		
		$pdf->SetXY($bill->x+$s->shadow, $bill->y+$bill->h/2+$s->shadow);
		$pdf->SetTextColor($s->shadow_c, $s->shadow_c, $s->shadow_c);
		$pdf->Cell($bill->w, 0, $txt ,0, 0, 'C');

		$pdf->SetXY($bill->x, $bill->y+$bill->h/2);
		$pdf->SetTextColor($color[0], $color[1], $color[2]);
		$pdf->Cell($bill->w, 0, $txt ,0, 0, 'C');
}


function bitcoin_image($bill){
	global $pdf, $s;
	$base = 1.2;//width at 1 btc
	$max = 1.8;//100 btc
	if($bill->card){
		$base = 1.1;
		$max = 1.5;
	}
	if($bill->amount<=1){
		$size = $base + ($bill->amount - 1);
	}

	if($bill->amount>1){
		$size = $base + ($max-$base)*(log($bill->amount)/4.6);
	}
	if($bill->amount=='open')
		$size = $base;
	$w = $size;
	$h = $size;
	if($s->icon_w){
		$w = $s->icon_w;
		$h = $s->icon_h;
	}
	$x = $bill->x + ($bill->w/2);
	$y = $bill->y + ($bill->h/2);
	$image = 
	$pdf->SetFillColor($color[0], $color[1], $color[2]);
	$pdf->Image($s->icon,$x-$w/2, $y-$h/2, $w, $h);

}

function set_is_card($bill){
	$bill->card = false;
	if(((float) $bill->w) < 4){
		$bill->card = true;
	}
}
function denomination_to_words($amt){
	if($amt<1){
		return ucwords(int_to_words($amt*100).' Bitcents');
	}else{
		return ucwords(int_to_words($amt).' Bitcoins');
	}
}


function get_page($url){
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$output = curl_exec($ch); 
	curl_close($ch);
	return $output;
}

//make a lighter version of a color
function light($num){
	if(is_array($num)){
		return array(light($num[0]), light($num[1]), light($num[2]));
	}else{
		$diff = 255 - $num;
		$inc = round($diff*.85);
		return $num+$inc;
	}
}

//for testing
function pr($var){
	echo '<pre>';
	print_r($var);
	echo '</pre>';
}


?>