<?php

class flowplayer {

    const THEID = "flowplayer-";

    private $object_attribs;
    private $object_params;
    private $fallback;
    private $option;
    private $count;
    private $location;

    public function __construct($location) {
        $this->location = $location;
        $this->defaultOption();
        $this->count = 1;
    }

    private function defaultOption() {
        $this->option = array(
                'flashIsSetup' => false,
                'swfobject' => false,
                'videoClassName' => false,
                'audioClassName' => false,
                'videoClassNameForTag' => false,
                'audioClassNameForTag' => false,
                'videoFlowPlayerEnabled' => true,
                'audioFlowPlayerEnabled' => true
        );
    }

    public function setUpFlash($object) {
        $this->object_attribs = $object['attribs'];
        $this->object_params = $object['params'];
        $this->option['flashIsSetup'] = true;
    }

    public function getOptions($param) {
        return $this->option[$param];
    }

    public function setOptions($param, $value) {
        $this->option[$param] = $value;
    }

    public function getFlashIsSetup() {
        return $this->option['flashIsSetup'];
    }

    public function setFallback($fallback) {
        $this->fallback = $fallback;
    }

    public function flowPlayerJSON($json) {
        $jsonTemp = str_replace('&#8220;','"',$json[1]);
        $jsonTemp = str_replace('&#8221;','"',$jsonTemp);
        $jsonTemp = str_replace('<br />','',$jsonTemp);
        $jsonTemp = json_decode($jsonTemp, true);
        global $wphtml5playerclass;
        if($wphtml5playerclass->is_assoc($jsonTemp)) {
            $json = $jsonTemp;
            unset($jsonTemp);
            $jsonTemp = array();
            foreach($json as $key => $value) {
                $jsonTemp[strtolower($key)] = $value;
            }
            if(isset($jsonTemp["video"])) {
                return $this->videoJSON($jsonTemp["video"]);
            } elseif(isset($jsonTemp["video"])) {
                return $this->audioJSON($jsonTemp["audio"]);
            } else {
                return "video or audio is not set.";
            }
        } else {
            return $wphtml5playerclass->jsonError();
        }
    }

    private function videoJSON($jsonTemp) {
        global $wphtml5playerclass;
        $json = array();
        foreach($jsonTemp as $key => $value) {
            $json[strtolower($key)] = $value;
        }
        if(isset($json["src"])) {
            $json["url"] = $json["src"];
            unset($json["src"]);
        }
        if(isset($json["url"])) {
            if(!is_string($json["url"])) {
                return "ERROR: URL is not string.";
            } else {
                $url = $json["url"];
            }
        } else {
            return "ERROR: URL is not specified.";
        }
        if(!(isset($json["width"]) && isset($json["height"]))) {
            $width = false;
            $height = false;
        } elseif (!(is_numeric($json["width"]) && is_numeric($json["height"]))) {
            $width = false;
            $height = false;
        } else {
            $width = (int)$json["width"];
            $height = (int)$json["height"];
        }
        if(!isset($json["htmlvideo"])) {
            $htmlvideo = false;
        } elseif(!$wphtml5playerclass->is_assoc($json["htmlvideo"])) {
            $htmlvideo = false;
        } else {
            $htmlvideo = $json["htmlvideo"];
        }
        if(!isset($json["poster"]) || is_array($json["poster"])) {
            $poster = false;
        } elseif (!preg_match("#.(jpg|jpeg|png|gif)$#i", $json["poster"])) {
            $poster = false;
        } else {
            $poster = $json["poster"];
        }
        unset($json);
        $this->fallback = $wphtml5playerclass->videoreplaceJSON(null, $htmlvideo, true);
        $this->videoCompatible($url, $width, $height, $poster, true);
        return $this->getFlashObject();
    }

    private function audioJSON($jsonTemp) {
        global $wphtml5playerclass;
        $json = array();
        foreach($jsonTemp as $key => $value) {
            $json[strtolower($key)] = $value;
        }
        if(isset($json["src"])) {
            $json["url"] = $json["src"];
            unset($json["src"]);
        }
        if(isset($json["url"])) {
            if(!is_string($json["url"])) {
                return "ERROR: URL is not string.";
            } else {
                $url = $json["url"];
            }
        } else {
            return "ERROR: URL is not specified.";
        }
        if(!isset($json["htmlaudio"])) {
            $htmlaudio = false;
        } elseif(!$wphtml5playerclass->is_assoc($json["htmlaudio"])) {
            $htmlaudio = false;
        } else {
            $htmlaudio = $json["htmlaudio"];
        }
        unset($json);
        $this->fallback = $wphtml5playerclass->audioreplaceJSON(null, $htmlaudio, true);
        $this->audioCompatible($url, true);
        return $this->getFlashObject();
    }

    private function getSWFobject() {
        if($this->option['swfobject']) {
            $this->object_attribs['id'] = self::THEID.$this->count;
            $swfobject = '<script type="text/javascript">'.
                    'swfobject.registerObject("'.$this->object_attribs['id'].'", "9.0.115")</script>';
            $this->count++;
            return $swfobject;
        } else {
            return "";
        }
    }

    public function getFlashObject() {
        if($this->option['flashIsSetup']) {
            $object_attribs = $object_params = '';
            $swfobject = $this->getSWFobject();

            foreach ($this->object_attribs as $param => $value) {
                $object_attribs .= '  ' . $param . '="' . $value . '"';
            }

            foreach ($this->object_params as $param => $value) {
                $object_params .= '<param name="' . $param . '" value=\'' . $value . '\' />';
            }
            $this->option['flashIsSetup'] = false;
            return sprintf("%s<object %s> %s  %s</object>", $swfobject, $object_attribs, $object_params, $this->fallback);
        } else {
            return "";
        }
    }

    public function videoCompatible($url, $width, $height, $poster, $tag = false) {
        if(preg_match("#(mp4|m4v)$#i",$url) && !$this->option['flashIsSetup'] &&
                ($this->option['videoFlowPlayerEnabled'] || $tag)) {
            if(!($width && $height)) {
                $width = 480;
                $height = 320;
            }
            $flashvars = "";
            if($poster) {
                $flashvars = array(
                        "playlist" => array(
                                array(
                                        "url" => $poster
                                ),
                                array(
                                        "url" => $url,
                                        "autoPlay" => false
                                )
                        )
                );
            } else {
                $flashvars = array(
                        "clip" => array(
                                "url" => $url,
                                "autoPlay" => false
                        )
                );
            }
            $flashvars['plugins'] = array(
                    "controls" => array(
                            "fullscreen" => false
                    ),
            );
            $flashvars = 'config='.json_encode($flashvars);
            $movie = $this->location."/inc/flowplayer.swf";
            $flashobject['attribs'] = array(
                    "type" => "application/x-shockwave-flash",
                    "data" => $movie,
                    "width" => $width,
                    "height" => $height

            );
            $flashobject['params'] = array(
                    "movie" => $movie,
                    "allowfullscreen" => "false",
                    "flashvars" => $flashvars
            );
            if($this->option['videoClassNameForTag'] && $tag) {
                $flashobject['attribs']['class'] = $this->option['videoClassNameForTag'];
            } elseif($this->option['videoClassName'] && !$tag) {
                $flashobject['attribs']['class'] = $this->option['videoClassName'];
            }
            $this->setUpFlash($flashobject);
        }
    }

    public function audioCompatible($url, $tag = false) {
        if(preg_match("#(mp3)$#i",$url) && !$this->option['flashIsSetup'] &&
                ($this->option['audioFlowPlayerEnabled'] || $tag)) {
            $flashvars = array(
                    "plugins" => array(
                            "controls" => array(
                                    "fullscreen" => false,
                                    "height" => 30,
                                    "autoHide" => false
                            )
                    ),
                    "clip" => array(
                            "autoPlay" => false,
                            "url" => $url
                    ),
                    "playerId" => "audio",
                    "playlist" => array(
                            array(
                                    "autoPlay" => false,
                                    "url" => $url
                            )
                    )
            );
            $flashvars = 'config='.json_encode($flashvars);
            $movie = $this->location."/inc/flowplayer.swf";
            $flashobject['attribs'] = array(
                    "type" => "application/x-shockwave-flash",
                    "data" => $movie,
                    "width" => "300",
                    "height" => "30",
            );
            $flashobject['params'] = array(
                    "movie" => $movie,
                    "allowfullscreen" => "false",
                    "cachebusting" => "true",
                    "bgcolor" => "#000000",
                    "flashvars" => $flashvars
            );
            if($this->option['audioClassNameForTag'] && $tag) {
                $flashobject['attribs']['class'] = $this->option['audioClassNameForTag'];
            } elseif($this->option['audioClassName'] && !$tag) {
                $flashobject['attribs']['class'] = $this->option['audioClassName'];
            }
            $this->setUpFlash($flashobject);
        }
    }
}

?>
