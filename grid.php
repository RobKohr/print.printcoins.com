require_once('fpdf.php');
require_once('fpdf_extensions.php');

$width = .6;
$height = .6;
$page_width = 8.5;
$page_height = 11;
$margin_top_bottom = 0.1;
$margin_left_right = 0.5;
$page_usable_width = $page_width - ($margin_left_right*2);
$page_usable_height = $page_height - ($margin_top_bottom*2);
