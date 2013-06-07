<?php

/**
 * Sencha Animator to Gloo Converter
 *
 * Revision History
 * 1.0  Jun 07 2013  LLL  added code to make Sencha Animator controller object global
 */
$sa2gcDescription='Sencha Animator to Gloo Converter';
$sa2gcVersion='1.0';

// allowed file extensions
$allowedExts = array("html");

// get incoming file extension
$fnmarr = explode(".", $_FILES["file"]["name"]);    // break up filename into substrings
$ext = end($fnmarr);                                // get last substring

// validate file
if(($_FILES["file"]["type"] != "text/html") ||      // check type
   ($_FILES["file"]["size"] > 300000) ||            // check size
   !in_array($ext, $allowedExts))                   // check extension
{
  echoErrorAndExit("Invalid file");
}

// check for errors
if($_FILES["file"]["error"] > 0) { echoErrorAndExit($_FILES["file"]["error"]); }

// open file (read entire contents into string)
$inpfildta = file_get_contents($_FILES["file"]["tmp_name"]);

// validate Sencha Animator version
if(!validateVersion($inpfildta)) { exit(1); }

// SECURITY WORK-AROUND: search and replace: "function!" -> "function "
// PROBLEM: the lowelllist.com server (register.com) has an upload file content restriction!
$inpfildta = str_replace('function!','function ',$inpfildta);

// phase 1: strip HTML wrapper
$outfildta = stripHtmlWrapper($inpfildta);

// phase 2: replace all 'assets/' relative paths with substitute
$outfildta = replaceAssetPaths($outfildta, $_POST['assetPathReplacement']);

// phase 3: insert JS code
$outfildta = insertJavascriptCode($outfildta, $_POST['saScaleMode'], $_POST['saStageMargin']);

// phase 4: modify Sencha Animator JS
$outfildta = modifySenchaAnimatorCode($outfildta);

// phase 5: wrap with comments
$outfildta = wrapWithComments($outfildta);

// output file
if(strpos($_POST['submitButton'],'Download')!==FALSE)
{
  // output file for download
  
  // send HTTP headers indicating file download
  $outputFnm = "gloo-eapi-code.txt";
  header("Cache-Control: public");
  header("Content-Description: File Transfer");
  header("Content-Length: " . strlen($outfildta) . ";");
  header("Content-Disposition: attachment; filename=$outputFnm");
  header("Content-Type: application/octet-stream; "); 
  header("Content-Transfer-Encoding: binary");

  // output file data
  echo $outfildta;
}
else
{
  // output file for display
  echo '<pre>' . htmlentities($outfildta) . '</pre>';
}

// done!

// -------------------- ERROR FUNCTIONS --------------------

function echoErrorAndExit($errtxt)
{
  echoError($errtxt);
  exit(1);
}

function echoError($errtxt)
{
  echoIncomingFileDetails();
  echo "<br>";
  echo "Error: " . $errtxt . "<br>";
}

function echoIncomingFileDetails()
{
  echo "Upload: " . $_FILES["file"]["name"] . "<br>";
  echo "Extension: " . end(explode(".", $_FILES["file"]["name"])) . "<br>";
  echo "Type: " . $_FILES["file"]["type"] . "<br>";
  echo "Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br>";
}

// -------------------- PARSING FUNCTIONS --------------------

/**
 * Validates the incoming Sencha Animator file
 * Returns: TRUE if valid, FALSE otherwise
 */
function validateVersion($dta)
{
  // search for tokens
  $vertkn = '<!-- Sencha Animator Version: ';       // version token
  $veridx = strpos($dta,$vertkn);
  $bldidx = strpos($dta,'Build: ');                 // build token
  
  // check if tokens were found
  if($veridx===FALSE || $bldidx==FALSE) {
    echoError('Could not read Sencha Animator version from input file');
    return FALSE;
  }
  
  // isolate version string
  $stridx = $veridx + strlen($vertkn);
  $verstr = trim( substr($dta,$stridx,$bldidx-$stridx) );
  
  // check version string
  if($verstr!='1.3')
  {
    echoError('Invalid Sencha Animator version [' . $verstr . ']');
    return FALSE;
  }

  // success
  return TRUE;
}

/**
 * First pass: strip HTML header, footer, body tags
 * Returns: the stripped string
 */
function stripHtmlWrapper($dta)
{
  // 1) strip everything before the first '<script'
  $begpos=strpos($dta,'<script');
  if($begpos===FALSE) { echoErrorAndExit('script tag not found'); }
  $dta=substr($dta,$begpos);

  // 2) delete middle snippet: </head><body style="margin:0;">
  $begpos=strpos($dta,'</head>');
  $endpos=strpos($dta,'<div id=');
  if($begpos===FALSE || $endpos===FALSE) { echoErrorAndExit('head/body tags not found'); }
  $dta=substr($dta,0,$begpos) . substr($dta,$endpos);

  // 3) delete ending snippet: </body></html>
  $begpos=strpos($dta,'</body>');
  if($begpos===FALSE) { echoErrorAndExit('closing body tag not found'); }
  $dta=substr($dta,0,$begpos);

  return $dta;
}

/**
 * Second pass: replace all 'asset/' path values with the new asset path value
 * Returns: the modified string
 */
function replaceAssetPaths($dta, $newpth)
{
  return str_replace('assets/',$newpth,$dta);
}

/**
 * Third pass: insert jQuery and custom scaling and asset selection JS code
 * Returns: the modified string
 */
function insertJavascriptCode($dta, $sclmod, $stgmgn)
{
  // append <script> tags
  return $dta . "\n\n" .
    '<script src="gloo_assets_202/shared/js/jquery-2.0.0.min.js"></script>' . "\n" .
    '<script src="gloo_assets_202/shared/js/saUtility.js"></script>' . "\n" .
    '<script type="text/javascript">' . "\n" .
    '  $(function() { saUtility.createObscuringDiv(); saUtility.autoScale(saUtility.SCALE_MODE.' . $sclmod . ',' . $stgmgn . '); });' . "\n" .
    '  $(window).load(function(){ saUtility.fadeOutObscuringDiv(); });' . "\n" .
    '</script>' . "\n";
}

/**
 * Fourth pass: modify Sencha Animator JS code
 * Returns: the modified string
 */
function modifySenchaAnimatorCode($dta)
{
  // make Sencha Animator controller object global
  $schstr="controller.setConfig(configData);\n"; // search string
  $begpos=strpos($dta,$schstr);
  if($begpos===FALSE) { echoErrorAndExit('controller config line not found'); }
  $begpos+=strlen($schstr);
  // insert new line
  $dta=substr($dta,0,$begpos) . "       window.saController=controller; // make the controller global\n" . substr($dta,$begpos);

  return $dta;
}

/**
 * Wraps some simple comments around the output data
 * Returns: the modified string
 */
function wrapWithComments($dta)
{
  global $sa2gcDescription, $sa2gcVersion;

  $datcmt = '<!-- ' . $sa2gcDescription . ' ' . $sa2gcVersion . ' : ' . date(DATE_RFC822) . ' -->';
  $cpycmt = '<!-- copy and paste to Gloo element -->';

  return $datcmt . "\n" .
         $cpycmt . "\n\n" .
         $dta    . "\n" .
         $cpycmt . "\n" .
         $datcmt . "\n";
}

?>