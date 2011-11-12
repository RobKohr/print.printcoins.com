<?php
require_once('fpdf.php');
require_once('fpdf_extensions.php');
require_once('lib/phpqrcode/qrlib.php');
require_once('utils.php');
$s->bill_w = 6.14+.2;
$s->bill_h = 2.61+.1;
if($_REQUEST['card']){
	$s->bill_w = 3.5;
	$s->bill_h = 2;
 }
$s->draw = 100;//light draw color
$s->l_w = .008;
$s->page_width = 8.5;
$s->page_height = 11;
$s->margin_top_bottom = 00;
$s->margin_left_right = 0.5;
$s->page_usable_width = $s->page_width - ($s->margin_left_right*2);
$s->page_usable_height = $s->page_height - ($s->margin_top_bottom*2);
$s->bill_pad = 0.2;
$s->shadow=.02;
$s->shadow_c=40;

//Source (div by 100) http://boardgames.about.com/od/poker/a/chip_denoms.htm 
$s->colors = array(
				   /*
				   '0.01'=>array(255, 255, 255), 
				   //				   '0.02'=>array(255, 255, 0), 
				   '0.05'=>array(255, 0, 0), 
				   '0.10'=>array(0, 0, 255),
				   //				   '0.20'=>array(127, 127, 127),
				   */
				   '0.25'=>array(0, 255, 0),
				   '0.50'=>array(255, 165, 0),
				   '1'=>array(0, 0, 0),
				   '5'=>array(160, 32, 240),
				   '10'=>array(159, 139, 112),//beaver brown
				   '20'=>array(173, 216, 230),
				   '50'=>array(165, 42, 42),
				   '100'=>array(255, 215, 0)//Gold (not a normal poker chip)
);
$pdf = new PDF('P','in','Letter');
$pdf->SetMargins($s->margin_top_bottom, $s->margin_left_right);
$pdf->SetAutoPageBreak(false);
$p_c = $s->page_width/2;
$off_x = $p_c - $s->bill_w/2;
$off_y = 99999999;
if(!$_REQUEST['bills']){
	include('form.html');
	exit;
 }

if($_REQUEST['bills'] == 'test'){
	foreach($s->colors as $amount=>$color){
		$off_y +=$s->bill_h;
		if($off_y>($s->page_height-$s->bill_h)){
			$pdf->AddPage();
			$off_y = $s->margin_top_bottom;
		}
		$pdf->SetXY($off_x, $off_y);		
		bill('test', $amount, 'PrintCoins.com', $amount);
	}
 }else{
	$bills = str_replace('"', '', $_REQUEST['bills']);
	$bills = explode("\n", $bills);
	$printer = $_REQUEST['printer'];	
	$printer = 'PrintCoins.com';
	foreach($bills as $bill){
		$off_y +=$s->bill_h;
		if($off_y>($s->page_height-$s->bill_h)){
			$pdf->AddPage();
			$off_y = $s->margin_top_bottom;
		}
		$pdf->SetXY($off_x, $off_y);		
		$bill = explode(',', $bill);
		$pub = $bill[1];
		$priv = $bill[2];
		if($bill[3])
			$amount = $bill[3];
		if(!$pub)
			continue;
		if(!$priv)
			continue;
		bill($pub, $priv, $printer, $amount, $_REQUEST['reverse']);
	}
 }//else from if bills = test
$pdf->Output();

function bill($pub, $priv, $printer, $amount, $reverse=null){
	global $s, $pdf;
	$bill = (object) array(
						   'pub'=>$pub, 
						   'priv'=>$priv, 
						   'printer'=>$printer, 
						   'amount'=>$amount, 
						   'reverse'=>$reverse,
						   'x'=>$pdf->GetX(),
						   'y'=>$pdf->getY(),
						   'card'=>false
						);
	$bill_x = $pdf->GetX();
	$bill_y = $pdf->GetY();
	$shadow = $s->shadow;
	$shadow_c = $s->shadow_c;
	$pdf->SetLineWidth($s->l_w);
	$pdf->SetDrawColor($s->draw, $s->draw, $s->draw);
	$card = false;
	if(((float) $s->bill_w) < 4){
		$bill->card = true;
		$card = true;//shrink down and drop off unneeded stuff
	}

	if($pub=='test'){
		$amount = $priv;
		$priv = 'test';
		$priv = 'http://printcoins.com/r/5HueCGU8rMjxEXxiPuD5BDku4MkFqeZyd4dZ1jvhTVqvbTLvyTJ';
		$pub = 'Example Public key asdfljasdlfjlasjdflsadjf';
	}else{
		if(!$amount){
			$url = 'http://blockexplorer.com/q/addressbalance/'.$pub;
			$amount = get_page($url);
		}
	}

	if($amount<1)
		$amount = number_format($amount, 2, '.', ',');
	else
		$amount = number_format($amount, 0, '.', ',');

	$pdf->SetDrawColor(240,240, 240);



	//Bitcoin image
	$base = 1.2;//width at 1 btc
	$max = 1.8;//100 btc
	if($card){
		$base = 1.1;
		$max = 1.5;
	}
	if($amount<=1){
		$size = $base + ($amount - 1);
	}

	if($amount>1){
		$size = $base + ($max-$base)*(log($amount)/4.6);
	}

	$color = $s->colors[$amount];
	$pdf->SetFillColor(light($color[0]), light($color[1]), light($color[2]));
	$pdf->SetXY(0, $bill_y);	
	$pdf->Cell($s->page_width, $s->bill_h, '', 1);
	$pdf->SetXY($bill_x, $bill_y);	
	$pdf->Cell($s->bill_w, $s->bill_h, '', 1);
	$pdf->Rect($bill_x, $bill_y, $s->bill_w, $s->bill_h, 'FD');



	$w = $size;
	$h = $size;
	$x = $bill_x + ($s->bill_w/2);
	$y = $bill_y + ($s->bill_h/2);

	$pdf->SetFillColor($color[0], $color[1], $color[2]);
	//	$pdf->Circle($x, $y, $w/2+.05, 'F');
	if(!$reverse)
		$pdf->Image('bitcoin_450x450.png',$x-$w/2, $y-$h/2, $w, $h);

	if($reverse){
		//ellipse
		$pdf->SetDrawColor($color[0], $color[1], $color[2]);
		$pdf->SetLineWidth(.02);
		$pdf->Ellipse($x, $y, 1.8, .7, 'D');
		$pdf->SetDrawColor($s->draw, $s->draw, $s->draw);
		$pdf->SetLineWidth($s->l_w);
		//text qant
		$txt = denomination_to_words($amount);
		
		$pdf->SetXY($bill_x+$shadow, $bill_y+$s->bill_h/2+$shadow);
		$pdf->SetTextColor($shadow_c, $shadow_c, $shadow_c);
		$pdf->Cell($s->bill_w, 0, $txt ,0, 0, 'C');

		$pdf->SetXY($bill_x, $bill_y+$s->bill_h/2);
		$pdf->SetTextColor($color[0], $color[1], $color[2]);
		$pdf->Cell($s->bill_w, 0, $txt ,0, 0, 'C');

	}



	$w = 1;
	$h = .5;
	$p = .2;
	if($card)
		$p = .15;
	if($amount==1)
		$pl = '';
	else 
		$pl = 's';
	
	$font = 'Helvetica';
	//Denomination top left
	$font_size = 23;
	if($card)
		$font_size = 18;
	$pdf->SetFont($font,'B',$font_size);
	$color = $s->colors[$amount];
	$pdf->SetTextColor($shadow_c, $shadow_c, $shadow_c);
	$pdf->SetXY($bill_x+$p+$shadow, $bill_y+$p+$shadow);
	$pdf->Cell($w, $h, $amount.' Bitcoin'.$pl, 0);

	$pdf->SetXY($bill_x+$p, $bill_y+$p);
	$pdf->SetTextColor($color[0], $color[1], $color[2]);
	$pdf->Cell($w, $h, $amount.' Bitcoin'.$pl, 0);

	if(!$card){
	//Denomination bottom right
		$pdf->SetFont($font,'B',20);
		$x = $bill_x;
		$y = $bill_y + $s->bill_h - $h -$p;
		$pdf->SetXY($x + $shadow, $y + $shadow);
		$pdf->SetTextColor($shadow_c, $shadow_c, $shadow_c);
		$pdf->Cell($s->bill_w-$p, $h, $amount.' BTC', null, null, 'R');
		
		$pdf->SetXY($bill_x,
					$bill_y + $s->bill_h - $h -$p);
		$pdf->SetTextColor($color[0], $color[1], $color[2]);
		$pdf->Cell($s->bill_w-$p, $h, $amount.' BTC', null, null, 'R');
					/*

					*/
		//denomination bottom left
		$pdf->SetFont($font,'B', 15);
		$x = $bill_x + $p;
		$y = $bill_y + $s->bill_h - $p - .1;
		shadow_text($pdf, array('x'=>$x,
								'y'=>$y,
								'color'=>$color,
								'text'=>$amount,
								'align'=>'L',
								'width'=>$s->bill_w-$p,
								'shadow'=>$s->shadow/2));

		//denomination top right
		$x = $bill_x - $p;
		$y = $bill_y + $p + .1;
		shadow_text($pdf, array('x'=>$x,
								'y'=>$y,
								'color'=>$color,
								'text'=>$amount,
								'align'=>'R',
								'width'=>$s->bill_w,
								'shadow'=>$s->shadow/2));

	}
	

	if(!$reverse){
		//Printer info
		$h = 0;
		$x = $p+$bill_x+.5;
		$y = $bill_y+$s->bill_h - $h - $p - .02;
		$pdf->SetXY($x, $y);
		$pdf->SetFont($font,'B',6);
		$pdf->SetTextColor(0,0,0);		
		$pdf->Cell($s->bill_w, $h, 'Printed By: '.$printer);
		$pdf->SetXY($x, $y+.07);
		$pdf->SetFont($font,'',4);
		$pdf->MultiCell($s->bill_w, 0, 'Redeem instructions at http://printcoins.com/redeem');
		//		$pdf->MultiCell($s->bill_w, .05, 'The printer certifies that they have destroyed all copies of the private key.');
		
		$w = .5;
		$h = .5;
		$qr_p = (1-$w)/2;
		//QR for private key
		$x = $bill_x + $p + .2;
		$y = $bill_y + $s->bill_h - 1 - $p - .35;
		//frame qr
		$pdf->SetXY($x, $y);
		$pdf->Cell(1, 1, '', 1);
		$dir = '/home/robkohr/www/robkohr.com/files/bitcoin/printing/tmp/';
		$pdf->QR($priv, $dir, $x + $qr_p, $y + $qr_p, $w, $h);
		//Private key label
		$h = .1;
		$pdf->SetXY($x, $y-$h);
		$pdf->Cell($s->bill_w, $h, 'Private Key (Hidden QR code)');

		$link = 'http://printcoins.com/CheckBalance/'.substr($pub, 0, 8);
		//public key
		$w = .8;
		$h = .8;
		$x = $bill_x +$s->bill_w - $h - $p;
		$y = $bill_y + .65;
		$pdf->QR($pub, $dir, $x, $y, $w, $h);

		//label
		$h = 0;
		$pdf->SetXY($bill_x, $y-.14);
		$pdf->SetFont($font,'',5);
		$pdf->Cell($s->bill_w-$p, $h, $pub, null, null, 'R');
		/*
		//label link
		$h = 0;
		$pdf->SetXY($bill_x, $y-.06);
		$pdf->SetFont($font,'',5);
		$pdf->Cell($s->bill_w-$p, $h, $link, null, null, 'R');
		*/
	}

	//color borders
	$pdf->SetFillColor($color[0], $color[1], $color[2]);
	$cp = .4;//padding to corners
	$t = .06;//thickness
	$bp = .05;//padding to edges of bill
	$pdf->Rect($bill_x+$cp, $bill_y+$bp, $s->bill_w-($cp*2), $t, 'F');//top
	$pdf->Rect($bill_x+$cp, $bill_y+$s->bill_h-$t-$bp, $s->bill_w-($cp*2), $t, 'F');//bottom
	$pdf->Rect($bill_x+$bp, $bill_y+$cp, $t, $s->bill_h-($cp*2), 'F');//left
	$pdf->Rect($bill_x + $s->bill_w - $t - $bp, $bill_y+$cp, $t, $s->bill_h-($cp*2), 'F');//right


	if((!$card)&&(!$reverse)){
		//Vires in Numeris
		$pdf->SetFont($font,'I',8);
		$h=.4;
		$pdf->SetXY($bill_x, $bill_y+$s->bill_h - $h -.05);
		$pdf->Cell($s->bill_w, $h, 'Vires in Numeris', null, null, 'C');
		
		//series
		$pdf->SetFont($font,'I',6);
		$h=.35;
		$pdf->SetXY($bill_x, $bill_y);
		$pdf->Cell($s->bill_w-$p-.5, $h, 'Version 2011A', null, null, 'R');

		//Funded
		$pdf->SetFont($font,'',6);
		$h=.4;
		$pdf->SetXY($bill_x, $bill_y+$s->bill_h - $h);
		$pdf->Cell($s->bill_w-$p, $h, '[_] Checkmark here when this bill is funded', null, null, 'R');

		//Crypto message
		$pdf->SetFont('Courier','',8);
		$pdf->SetXY($bill_x, $bill_y+$p-.2 );
		$pdf->Cell($s->bill_w, $h, 'IN CRYPTOGRAPHY WE TRUST', null, null, 'C');
	
		//Not legal tender
		$pdf->SetFont($font,'',8);
		$pdf->SetXY($bill_x+$p, $bill_y+.65);
		$pdf->MultiCell(2, .15, 'Not recognized as legal tender by any government.', null, null, '');
		
		//Similar to a gift card
		$text = 'Similar to a gift card, this contains a hidden private key. This private key is used to transfer bitcoins out of the public address. Once done, this note is useless. Recipents are encouraged to transfer the coins to their digital wallet before a transaction if they don\'t trust the state of this note. Mark void and discard after extraction.';
		$pdf->SetFont($font,'',6);
		$pdf->SetXY($bill_x+$s->bill_w-2.2, $bill_y+$s->bill_h-1.1);
		$pdf->MultiCell(2.1, .08, $text, null, null, '');
	}//!card;
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

function shadow_text($pdf, $p){
	global $s;
	$defaults = array('height'=>0, 'align'=>'L', 'shadow'=>$s->shadow);
	$p = array_merge($defaults, $p);
	$x = $p['x'];
	$y = $p['y'];

	$pdf->SetXY($x+$p['shadow'], $y+$p['shadow']);
	$pdf->SetTextColor($s->shadow_c, $s->shadow_c, $s->shadow_c);
	$pdf->Cell($p['width'], $p['height'], $p['text'], null, null, $p['align']);

	$pdf->SetXY($x, $y);
	$pdf->SetTextColor($p['color'][0], $p['color'][1], $p['color'][2]);
	$pdf->Cell($p['width'], $p['height'], $p['text'], null, null, $p['align']);	
}

function light($num){
	$diff = 255 - $num;
	$inc = round($diff*.95);
	return $num+$inc;
}



?>