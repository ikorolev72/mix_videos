<?php
/*
Common function and variables for dynamic processing

 */

class Processing
{
    private $__error; // last error
    private $__debug;
    private $__log;

    public function __construct($log = false, $debug = false, $ffmpeg = 'ffmpeg', $ffprobe = 'ffprobe', $ffmpegLogLevel = 'info')
    {
        $this->ffmpeg = $ffmpeg;
        $this->ffprobe = $ffprobe;
        $this->ffmpegLogLevel = $ffmpegLogLevel;
        $this->error = '';
        $this->debug = $debug;
        $this->log = $log; // absolute path to log file
    }

    /**
     * doExec
     * @param    string    $Command
     * @return integer 0-error, 1-success
     */
    public function doExec($Command)
    {
        $outputArray = array();
        if ($this->debug) {
            print $Command . PHP_EOL;
            //return 1;
        }
        exec($Command, $outputArray, $execResult);
        if ($execResult) {
            $this->writeToLog(join("\n", $outputArray));
            return 0;
        }
        return 1;
    }

    public function getTemporaryFile($tmpDir, $extension, &$tmpFiles)
    {
        $tmp = $tmpDir . "/" . time() . rand(1000, 9999) . ".$extension";
        $tmpFiles[] = $tmp;
        return ($tmp);
    }

    public function removeTemporaryFiles($tmpFiles)
    {
        foreach ($tmpFiles as $tmpFile) {
            @unlink($tmpFile);
        }
        return (true);
    }

    /*
     * date2unix
     * this function translate time in format 00:00:00.00 to seconds
     *
     * @param    string $t
     * @return    float
     */
    public function date2unix($dateStr)
    {
        $time = strtotime($dateStr);
        if (!$time) {
            $this->error = "Incorrect date format for string '$dateStr'";
        }
        return ($time);
    }

    /**
     * time2float
     * this function translate time in format 00:00:00.00 to seconds
     *
     * @param    string $t
     * @return    float
     */
    public function time2float($t)
    {
        $matches = preg_split("/:/", $t, 3);
        if (array_key_exists(2, $matches)) {
            list($h, $m, $s) = $matches;
            return ($s + 60 * $m + 3600 * $h);
        }
        $h = 0;
        list($m, $s) = $matches;
        return ($s + 60 * $m);
    }

    /**
     * float2time
     * this function translate time from seconds to format 00:00:00.00
     *
     * @param    float $i
     * @return    string
     */
    public function float2time($i)
    {
        $h = intval($i / 3600);
        $m = intval(($i - 3600 * $h) / 60);
        $s = $i - 60 * floatval($m) - 3600 * floatval($h);
        return sprintf("%01d:%02d:%05.2f", $h, $m, $s);
    }

    public function writeToLog($message)
    {
        #echo "$message\n";
        $date = date("Y-m-d H:i:s");
        $stderr = fopen('php://stderr', 'w');
        fwrite($stderr, "$date   $message" . PHP_EOL);
        fclose($stderr);

        if (!empty($this->log)) {
            file_put_contents($this->log, "$date   $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    public function readJson($configFile)
    {
        $out = array();
        if (!file_exists($configFile)) {
            $this->writeToLog("File '$configFile' do not exists");
            return ($out);
        }
        $json = file_get_contents($configFile);
        if (!$json) {
            $this->writeToLog("Cannot read file '$configFile'");
            return ($out);
        }
        $out = json_decode($json, true);
        if (!$out) {
            $this->writeToLog("Incorrect json string in json file '$configFile'");
            return (array());
        }
        return ($out);
    }

    /**
     * getLastError
     * return last error description
     *
     * @return    string
     */
    public function getLastError()
    {
        return ($this->error);
    }

    /**
     * setLastError
     * set last error description
     *
     * @param    string  $err
     * @return    string
     */
    public function setLastError($err)
    {
        $this->error = $err;
        return (true);
    }

    public function cleanTmpDir($tmpDir)
    {
        if (is_dir($tmpDir)) {
            @array_map('unlink', glob("$tmpDir/*.*"));
            return (rmdir($tmpDir));
        }
        return (true);
    }

    public function checkProcessByPidFile($pidFile)
    {
        if (file_exists($pidFile)) {
            $k = 0;
            while ($k < 10) { // waiting up to 5 seconds for file. If we trying to kill when new file do not started processing yet
                $pid = trim(file_get_contents($pidFile));
                if (is_numeric($pid)) {
                    if (posix_kill($pid, 0)) {
                        return (true);
                    }
                }
                usleep(intval(0.5 * 1000000));
                $k++;
            }
            return (posix_kill($pid, 0)); // check if process running
        }
        return (false);
    }

    public function checkProcessByName($cmd)
    {
        $outputArray = array();
        $Command = "/usr/bin/pkill -0 -f '$cmd'";

        exec($Command, $outputArray, $execResult);
        if ($execResult) {
            return (false);
        }
        return (true);
    }

    public function compareTwoFiles($fileName1, $fileName2)
    {
        if (file_exists($fileName1 && file_exists($fileName2))) {
            if (crc32($fileName1) == crc32($fileName2)) {
                return (true);
            }
        }
        return (false);
    }

    public function prepareVideo($presenter, $sub_presenters, $output, $width = 1280, $height = 720)
    {
        $ffmpeg = $this->ffmpeg;
        $ffmpegLogLevel = $this->ffmpegLogLevel;
        $countOfSubPresenter = 8;

        $key = 0;

        //$value=$videoRecord;
        $input = array();
        $scaleFilter = array();
        $overlayFilter = array();
        $concatFilter = '';
        $audioFilter = array();
        $amergeFilter = array();

        $item = $presenter;

        /*
        if (!file_exists($item['video_mp4'])) {
        $this->setLastError("Error: Input file '".$item['video_mp4']."' do not exists") ;
        return(false);
        }
         */

        $start = $item['offset'];
        $end = $item['length'];
        $input[$key] = "-ss $start -t $end -i " . $item['video_mp4'];
        $scaleFilter[$key] = "[${key}:v] fps=25, setpts=PTS-STARTPTS, scale=w=$width:h=$height, setsar=1 [video${key}];";
        $overlayFilter[$key] = "[bg][video${key}] overlay=shortest=1 [bg${key}];";

        $subX = intval($width / $countOfSubPresenter);
        $subY = $height;
        $subScaleWidth = intval($width / $countOfSubPresenter);
        $subScaleHeight = intval($subScaleWidth / $width * $height);

        $bgHeight = $subScaleHeight + $height;
        $audioFilter[] = "[${key}:a] asetpts=PTS-STARTPTS,aresample=async=1000:first_pts=0:ocl=2 [a${key}] ;";
//        $audioFilter[]="[${key}:a] asetpts=PTS-STARTPTS, aformat=sample_fmts=fltp:sample_rates=44100:channel_layouts=stereo, volume=1, dynaudnorm,apad [a${key}] ;";
        $amergeFilter[] = "[a${key}]";

        foreach ($sub_presenters as $item) {
            /*
            if (!file_exists($item['video_mp4'])) {
            $this->setLastError("Error: Input file '".$item['video_mp4']."' do not exists") ;
            return(false);
            }
             */
            $n = $key;
            $key++;
            $scaleFilter[$key] = "[${key}:v] fps=25, setpts=PTS-STARTPTS, scale=w=$subScaleWidth:h=$subScaleHeight, setsar=1 [video${key}];";
            $overlayFilter[$key] = "[bg${n}][video${key}] overlay=x=" . ($n * $subX) . ":y=$subY:eof_action=pass [bg${key}];";
            $start = $item['offset'];
            $end = $item['length'];
            $input[$key] = "-ss $start -t $end -i " . $item['video_mp4'];
            $audioFilter[] = "[${key}:a] asetpts=PTS-STARTPTS,aresample=async=1000:first_pts=0:ocl=2 [a${key}] ;";
            //$audioFilter[]="[${key}:a] asetpts=PTS-STARTPTS, aresample, dynaudnorm,apad [a${key}] ;";
            $amergeFilter[] = "[a${key}]";
        }

        $cmd = join(" ", array(
            $ffmpeg,
            "-y", // overwrite output file
            "-loglevel $ffmpegLogLevel", //  ( default level is info )
            join(" ", $input), // input
             "-filter_complex \" ", // use filters
            "color=black:s=${width}x${bgHeight} [bg];",
            join(" ", $audioFilter), //
            join(" ", $amergeFilter), //
             "amix=duration=first:inputs=", //
            count($amergeFilter),
            "[a];",
            join(" ", $scaleFilter), //
            join(" ", $overlayFilter), //
            "[bg${key}] null [v]\"", //
             " -map \"[v]\"",
            "-c:v h264 -crf 23 -preset veryfast -bsf:v h264_mp4toannexb -pix_fmt yuv420p", // use output video codec h264 with Constant Rate Factor(crf=20), and veryfast codec settings
             "-map \"[a]\"",
            "-c:a aac -ac 2 -bsf:a aac_adtstoasc ",
            "-mpegts_copyts 1 -f mpegts $output", // output in mp4 format
        ));

        return ($cmd);
    }

    public function concatVideos($videos, $output)
    {
        $cmd = $this->ffmpeg . "  -y -loglevel " . $this->ffmpegLogLevel . " -i \"concat:" . join('|', $videos) . "\" -c copy -movflags faststart  -f mp4 $output";
        //$cmd = $this->ffmpeg . "  -y -loglevel " . $this->ffmpegLogLevel . " -i \"concat:" . join('|', $videos) . "\" -fflags +igndts -c:v copy -c:a copy -f mp4 $output";
        return ($cmd);
    }

    public function getAudioInfo($input)
    {
        $ffprobe = $this->ffprobe;
        $cmd = "$ffprobe -v quiet -hide_banner -show_streams -select_streams a:0 -of json $input";
        //echo $cmd;
        $json = shell_exec($cmd);
        $out = json_decode($json, true);
        return ($out);
    }

    public function getVideoInfo($input)
    {
        $ffprobe = $this->ffprobe;
        $cmd = "$ffprobe -v quiet -hide_banner -show_streams -select_streams v:0 -of json $input";
        //echo $cmd;
        $json = shell_exec($cmd);
        $out = json_decode($json, true);
        return ($out);
    }    


    public function normalizeVideo($input, $output, $width = 1280, $height = 720)
    {
        $ffmpeg = $this->ffmpeg;
        $ffmpegLogLevel = $this->ffmpegLogLevel;

        $audioInfo=$this->getAudioInfo($input);
     
        $audioFilter="[0:a] aresample=44100:first_pts=0 [a]";
        if( empty( $audioInfo['streams'][0]['duration'])) { //check if audio stream exists
            $videoInfo=$this->getVideoInfo($input);    
            if( !empty( $videoInfo['streams'][0]['duration'])) { //check if video stream exists
                $duration=$videoInfo['streams'][0]['duration'];
                $audioFilter="aevalsrc=0|0:s=44100:duration=$duration [a]";
            } else {
                $this->setLastError("Error: Cannot check duration of this media file");
                return( false);
            }                      

        }
        $cmd = join(" ", array(
            $ffmpeg,
            "-y", // overwrite output file
            "-loglevel $ffmpegLogLevel", //  ( default level is info )
            "-i $input", // input
             "-filter_complex \" ", // use filters
             "[0:v] fps=25, scale=w=min(iw*${height}/ih\,${width}):h=min(${height}\,ih*${width}/iw), pad=w=${width}:h=${height}:x=(${width}-iw)/2:y=(${height}-ih)/2 , setsar=1 [v];", 
             "$audioFilter\"",
             " -map \"[v]\"",
            "-c:v h264 -crf 23 -preset veryfast -bsf:v h264_mp4toannexb -pix_fmt yuv420p", // use output video codec h264 with Constant Rate Factor(crf=20), and veryfast codec settings
             "-map \"[a]\"",
            //"-c:a mp3 -ac 2 -ar 44100 -b:a 128k  ",
            "-c:a aac -ac 2 -ar 44100 -b:a 128k  -bsf:a aac_adtstoasc  ",
            "-shortest -g 150 -keyint_min 150 -mpegts_copyts 1 -f mpegts $output", // output in mp4 format
        ));

        return ($cmd);
    }
 

}
