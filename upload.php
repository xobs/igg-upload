<?php
$MAX_FILE_SIZE = (4*1024*1024); # 4 megabyte max file size
$str           = ""; /* Output message string (used for debugging) */
$base          = "http://iggmarathon.com";
$max_width     = 1920;
$max_height    = 1080;
$thumb_width   = 160;
$thumb_height  = 120;
$err_width     = 60000;
$err_height    = 60000;
$gm_path       = "/usr/local/bin/"; /* Path of imagemagick or graphicsmagick */
$format        = "png";
$output_dir    = "/tmp/photos";
$ps_timeout    = 5; /* Limit processing time to 5 seconds */


function run_cmd($cmd, $args) {
    global $gm_path;
    $result = "";
    $cmd = $gm_path . $cmd . " " . $args;
    $proc = popen($cmd, "r");
    stream_set_timeout($proc, $ps_timeout);
    while (!feof($proc)) {
        $result .= fgets($proc);
    }
    $status = pclose($proc);
    return Array($result, $status, $cmd);
}

# Note: Assumes $filename is safe
# Note: $username is not to be trusted
function process_image($filename, $orig, $username) {
    global $max_width, $max_height;
    global $thumb_width, $thumb_height;
    global $err_width, $err_height;
    global $format;
    global $output_dir;

    $ident = run_cmd("identify", $filename);
    $dbg = $ident[2];
    if ($ident[1]) {
        return "Not an image file";
    }
    $info            = explode(" ", $ident[0]);
    $size            = $info[2];
    $size_ar         = explode("x", $size);
    $width           = $size_ar[0];
    $height          = $size_ar[1];
    $output_filename = "00"
                     . preg_replace("/[^0-9]/", "", $username)
                     . "-"
                     . preg_replace("/[^0-9a-zA-Z]/", "", $orig)
                     . "-";

    $new_width = $width;
    $new_height = $height;

    $new_t_width = $width;
    $new_t_height = $height;

    /* Calculate new minimum X */
    $new_width_1 = $max_width;
    $new_height_1 = (int)(($max_width * 1.0 / $width) * $height);
    $new_t_width_1 = $thumb_width;
    $new_t_height_1 = (int)(($thumb_width * 1.0 / $width) * $height);

    /* Calculate new minimum Y */
    $new_height_2 = $max_height;
    $new_width_2 = (int)(($max_height * 1.0 / $height) * $width);
    $new_t_height_2 = $thumb_height;
    $new_t_width_2 = (int)(($thumb_height * 1.0 / $height) * $width);

    if (!is_dir($output_dir)) {
        return "Output directory doesn't exist";
    }


    /* Determine if we need to resize the image */
    if ( ($width > $err_width) || ($height > $err_height) ) {
        return "Size exceeds maximum of ".$err_width."x".$err_height;
    }

    if ( ($new_width <= $max_width) && ($new_height <= $max_height) ) {
        $dbg .= "Image dimensions are fine.\n";
    }
    else if (($new_width_1 <= $max_width) && ($new_height_1 <= $max_height)) {
        $new_width = $new_width_1;
        $new_height = $new_height_1;
        $dbg .= "Taking size 1: $new_width x $new_height\n";
    }
    else if (($new_width_2 <= $max_width) && ($new_height_2 <= $max_height)) {
        $new_width = $new_width_2;
        $new_height = $new_height_2;
        $dbg .= "Taking size 2: $new_width x $new_height\n";
    }


    /* Determine if we need to resize the thumbnail */
    if ( ($new_t_width <= $thumb_width) && ($new_theight <= $thumb_height) ) {
        $dbg .= "Thumb size was okay\n";
    }
    else if (($new_t_width_1 <= $thumb_width) && ($new_t_height_1 <= $thumb_height)) {
        $new_t_width = $new_t_width_1;
        $new_t_height = $new_t_height_1;
        $dbg .= "Taking thumb size 1: $new_t_width x $new_t_height\n";
    }
    else if (($new_t_width_2 <= $thumb_width) && ($new_t_height_2 <= $thumb_height)) {
        $new_t_width = $new_t_width_2;
        $new_t_height = $new_t_height_2;
        $dbg .= "Taking thumb size 2: $new_t_width x $new_t_height\n";
    }


    $base_filename = tempnam($output_dir, $output_filename);
    unlink($base_filename);
    $dbg .= "Base filename: $base_filename\n";


    $dbg .= "File: [$filename]  Ident: [$ident]  Size: [$size]  Width: $width  Height: $height  New width: [$new_width]  New height: [$new_height]\n";

    /* Perform the resize */
    $result = run_cmd("convert", "$filename -resize " . $new_width . "x" . $new_height . " " . $base_filename . "." . $format);
    $dbg .= "Result of command " . $result[2] . ": " . $result[0] . "\n";
    if ($result[1]) {
        unlink($base_filename . "." . $format);
        unlink($base_filename . "-thumb." . $format);
        return "Unable to resize image";
    }

    /* Generate the thumbnail */
    $result = run_cmd("convert", "$filename -resize " . $new_t_width . "x" . $new_t_height . " " . $base_filename . "-thumb." . $format);
    $dbg .= "Result of command " . $result[2] . ": " . $result[0] . "\n";
    if ($result[1]) {
        unlink($base_filename . "." . $format);
        unlink($base_filename . "-thumb." . $format);
        return "Unable to generate thumbnail";
    }

    return;
}

if (array_key_exists("image", $_FILES)) {
    $image = $_FILES["image"];

    if ($image["error"] > 0) {
        $str = $image["error"];
    }
    else if ($image["size"] > $MAX_FILE_SIZE) {
        $str = "Max file size is too large";
    }
    else if (!array_key_exists("u", $_POST)) {
        $str = "No username specified";
    }
    else {
        $str = process_image($image["tmp_name"], $image['name'], $_POST["u"]);
    }
    unlink($_FILES["image"]["tmp_name"]);
}

?>

<html lang="en">

<head>
    <link rel="shortcut icon" href="<?=$base?>/static/favicon.ico">

    <!-- CSS Includes -->
    <link href="<?=$base?>/static/css/reset.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/960_12_col.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/style.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/typography.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/colors.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/tipsy.css" rel="stylesheet">
    <link href="<?=$base?>/static/css/jquery.tweet.css" rel="stylesheet">
</head>
<body>
    <div id="super">
        <div class="container container_12">
            <div class="main grid_12">
                <h6 class="pagetitle">Upload Art</h6>
<?php if ($str) { ?>
                <div class="grid_10 alpha push_1 outerborder top donate">
                    <div class="innerborder form-header">
                        <h2 class="sixteen-px expanded darkshadow">File Error</h2>
                    </div>
                    <div class="innerborder form-body">
                        <div class="form-item">
                            An error occurred when resizing your image: <?=$str?>
                        </div>
                        <div class="form-item">
                            Please choose a different file or format and try your upload again.
                        </div>
                    </div>
                </div><?php } ?>
                <div class="grid_10 alpha push_1 outerborder top donate">
                    <form class="form-horizontal" enctype="multipart/form-data" action="" method="POST">
                        <div style='display:none'></div>
                        <fieldset>
                            <div class="innerborder form-header">
                                <h2 class="sixteen-px expanded darkshadow">Upload Image</h2>
                            </div>
                            <div class="innerborder form-body">
                                <div class="form-item">
                                    <label for="amount">File:</label>
                                    <input id="image" name="image" size="56" type="file" placeholder="image.jpg" tabindex="1">
                                    <input type="hidden" name="MAX_FILE_SIZE" value="<?=$MAX_FILE_SIZE?>" /> 
                                    <input type="hidden" name="u" value="8675309" /> 
                                </div>
                            </div>
                            <div class="innerborder form-footer">
                                <div class="align-right">
                                    <button type="submit">Upload!</button>
                                    <button type="button">Cancel</button>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

