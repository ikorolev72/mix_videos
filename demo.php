<?php
require_once __DIR__."/processing.php";
$tmpDir="./tmp"; // tmp dir, eg /tmp/videos
$tmpFiles=array(); // array for temporary files
@mkdir( $tmpDir, 0755, true) ;


// final file
$outputVideo="out.mp4"; 


// new class instance
$processing= new Processing(false, false,'ffmpeg', 'ffprobe', 'info'); // 

// array for video parts
$videos=array();

// define input videos description for video part 1
$presenter=array(  'video_mp4' => "demo/presenter.mp4",  'offset' => 10,   'length' => 15, );
$sub_presenters=array();
$sub_presenters[]=array(  'video_mp4' => "demo/1.mp4",  'offset' => 0,  'length' => 15,);
$sub_presenters[]=array(  'video_mp4' => "demo/2.mp4",  'offset' => 1,  'length' => 15,);
$sub_presenters[]=array(  'video_mp4' => "demo/3.mp4",  'offset' => 4,  'length' => 15,);
$sub_presenters[]=array(  'video_mp4' => "demo/4.mp4",  'offset' => 3,  'length' => 15,);
$sub_presenters[]=array(  'video_mp4' => "demo/5.mp4",  'offset' => 2,  'length' => 15,);
// end of videos description


// do video part 1
$tmpOutput=$processing->getTemporaryFile($tmpDir, "ts", $tmpFiles) ;
$videos[]=$tmpOutput;
$cmd=$processing->prepareVideo($presenter, $sub_presenters, $tmpOutput ) ;
echo $cmd.PHP_EOL;
$processing->doExec( $cmd );


//////////////////////////////////////////////////////////////////
// define input videos description for video part 2
$presenter=array(  'video_mp4' => "demo/1.mp4",  'offset' => 10,   'length' => 20, );
$sub_presenters=array();
$sub_presenters[]=array(  'video_mp4' => "demo/2.mp4",  'offset' => 0,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/3.mp4",  'offset' => 10,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/presenter.mp4",  'offset' => 4,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/5.mp4",  'offset' => 30,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/1.mp4",  'offset' => 25,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/1.mp4",  'offset' => 33,  'length' => 20,);
$sub_presenters[]=array(  'video_mp4' => "demo/2.mp4",  'offset' => 40,  'length' => 20,);
// end of videos description


// do video part 2
$tmpOutput=$processing->getTemporaryFile($tmpDir, "ts", $tmpFiles) ;
$videos[]=$tmpOutput;
$cmd=$processing->prepareVideo($presenter, $sub_presenters, $tmpOutput ) ;
echo $cmd.PHP_EOL;
$processing->doExec( $cmd );


// concat video parts
$cmd=$processing->concatVideos($videos, $outputVideo);
echo $cmd.PHP_EOL;


$processing->doExec( $cmd );

// remove temporery files
foreach( $tmpFiles as $file ){
  @unlink ($file);
}


