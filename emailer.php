<?

if($_REQUEST['code']!='awesomeness'){
	echo json_encode(array($_REQUEST, $_GET, $_POST));//	echo '{"success":0}';
	exit;
 }

$from = $_REQUEST['from'];
if(!$from)
	$from = 'support@printcoins.com';

if(!$_REQUEST['no_bcc'])
	$bcc = 'Bcc: robkohr@gmail.com'. "\r\n".

$headers = 'From: <'.$from.'>' . "\r\n" .
	  'Reply-To: '.$from . "\r\n" .
		$bcc . 
	  'X-Mailer: PHP/' . phpversion();


if(mail($_REQUEST['to'], $_REQUEST['subject'], $_REQUEST['message'], $headers)){
	echo '{"success":1}';
 }else{
	print_r(array($_REQUEST['to'], $_REQUEST['subject'], $_REQUEST['message'], $headers));
 }