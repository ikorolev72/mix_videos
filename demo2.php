<?php
require_once __DIR__."/Processing.php";
$tmpDir="./tmp"; // tmp dir, eg /tmp/videos
$tmpFiles=array(); // array for temporary files
@mkdir( $tmpDir, 0755, true) ;

// final file
$outputVideo="out.mp4"; 
$width=1280;
$height=720;

//input files
$inputVideos= array( 
  "demo/1.mp4",
  "demo/2.mp4",
  "demo/3.mp4",
  "demo/4.mp4",
  "demo/5.mp4", 
);


$processing= new Processing(false, false,'ffmpeg', 'ffprobe', 'info'); // 


$videos=array();
foreach( $inputVideos as $input ) {
  $tmpOutput=$processing->getTemporaryFile($tmpDir, "ts", $tmpFiles) ;
  $videos[]=$tmpOutput;
  $cmd=$processing->normalizeVideo($input, $tmpOutput, $width , $height )   ;  
  echo $cmd.PHP_EOL;       
  $processing->doExec( $cmd );
}


// concat video parts
$cmd=$processing->concatVideos($videos, $outputVideo);
echo $cmd.PHP_EOL;
$processing->doExec( $cmd );

// remove temporery files
foreach( $tmpFiles as $file ){
  @unlink ($file);
}

