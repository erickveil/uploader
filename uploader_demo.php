<?php
session_start();

include "uploader_back.php";

echo "
    <html>
    <head>
    ";

echo "<script type='text/javascript' src='jquery-1.9.0.min.js'></script>";

echo "
    </head>
    <body>
    ";

echo pageData();



// ============================================================
// start page requirement

$upload_dir='/home/fac/update/';
$upload_tmp='/tmp/upload/';

$draw_upload=new Uploader('upload_file', $upload_dir, $upload_tmp, "", "");

// end page requirements
// ============================================================


echo "
    </body>
    </html>
    ";


function pageData()
{
    echo "<p>";
    echo "<br/>post:<br/>";
    print_r($_POST);
    echo "<br/>session:<br/>";
    print_r($_SESSION);
    echo "<br/>files:<br/>";
    print_r($_FILES);
    echo "<br/>js response:<br/>";
    echo "<span id='jsr'></span><br/>\n";

    echo "</p>";
}
