<?php

/**
 * Extention for oEmbed.
 *
 * Copyright (C) 2011 by Christopher John Jackson
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class oEmbedExt {

    private $object_attributes;
    private $object_parameters;
    private $user_object_attributes;
    private $user_object_parameters;
    private $dom;
    private $antiIframe;
    private $htmlcode;
    private $fallback;

    public function __construct() {
        include_once 'simple_html_dom.php';
        include_once 'antiiframe.class.php';
        $this->dom = new wphtml5_simple_html_dom();
        $this->antiIframe = new wphtml5_antiIframe();
        $this->htmlcode = false;
        $this->fallback = false;
        $this->user_object_attributes = array();
        $this->user_object_parameters = array();
        $this->lock = false;
    }

    public function parseUrl($url, $attri = false, $parameter = false, $html = false) {
        $this->htmlcode = false;
        $this->fallback = false;
        if(class_exists('WP_Embed')) {
            global $wp_embed;
            $attr = array();
            $movie = "";
            $flashvars = "";
            if(!$html) {
                $html = $wp_embed->shortcode($attr,$url);
            } else {
                $this->fallback = '<a href="'.$url.'">'.$url.'</a>';
            }
            if(preg_match('#^<iframe#i', $html)) {
                $html = $this->antiIframe->checkIframe($html);
            }
            if(preg_match('#(<object|<embed) #i',$html)) {
                $this->dom->load($html);
                $validFlash = false;
                $width = 480;
                $height = 368;
                $params = array();
                foreach($this->dom->find('object') as $attribute) {
                    if($attribute->width) {
                        $width = $attribute->width;
                    }
                    if($attribute->height) {
                        $height = $attribute->height;
                    }
                    if($attribute->type == "application/x-shockwave-flash" ||
                            $attribute->classid == "clsid:d27cdb6e-ae6d-11cf-96b8-444553540000") {
                        $validFlash = true;
                    }
                }
                if(!$validFlash) {
                    foreach($this->dom->find('embed') as $embed) {
                        if($embed->type == "application/x-shockwave-flash") {
                            $validFlash = true;
                            if(isset($embed->src)) {
                                $params['movie'] = $embed->src;
                            }
                            if(isset($embed->flashvars)) {
                                $params['flashvars'] = $embed->flashvars;
                            }
                            if(isset($embed->width)) {
                                $width = $embed->width;
                            }
                            if(isset($embed->height)) {
                                $height = $embed->height;
                            }
                        }
                    }
                }
                if(!$validFlash) {
                    $this->dom->__destruct();
                    $this->htmlcode = $html;
                } else {
                    if(!isset($params['movie'])) {
                        foreach($this->dom->find('param') as $param) {
                            $params[strtolower($param->name)] = $param->value;
                        }
                    }
                    $this->dom->__destruct();
                    if(isset($params['movie'])) {
                        $movie = $params['movie'];
                    }
                    if(isset($params['flashvars'])) {
                        $flashvars = $params['flashvars'];
                    }
                    unset($params);
                    $this->setDefaultParameters($movie, $flashvars, $width, $height);
                    $this->setUpObjectAttribute($attri);
                    $this->setUpObjectParam($parameter);
                }
            } else {
                $this->htmlcode = $html;
            }
        } else {
            $this->htmlcode = '<a href="'.$url.'">'.$url.'</a>';
        }
    }

    public function oEmbedJSON($json) {
        if(isset($json["src"])) {
            $json["url"] = $json["src"];
            unset($json["src"]);
        }
        if(isset($json["url"])) {
            if(!is_string($json["url"])) {
                return "ERROR: URL is not string.";
            }
        } else {
            return "ERROR: URL is not specified.";
        }

        if(!isset($json["attribute"])) {
            $json["attribute"] = false;
        } elseif(!$this->is_assoc($json["attribute"])) {
            $json["attribute"] = false;
        }

        if(!isset($json["param"])) {
            $json["param"] = false;
        } elseif(!$this->is_assoc($json["param"])) {
            $json["param"] = false;
        }

        $this->parseUrl($json["url"], $json["attribute"], $json["param"]);

        if(isset($json["width"]) && isset($json["height"])) {
            if(is_numeric($json["width"]) && is_numeric($json["height"])) {
                $json["width"] = (int)$json["width"];
                $json["height"] = (int)$json["height"];
                $this->setWidth($json['width']);
                $this->setHeight($json['height']);
            } else {
                $json["width"] = $json["height"] = false;
            }
        } else {
            $json["width"] = $json["height"] = false;
        }

        if(!isset($json["htmlvideo"])) {
            $json["htmlvideo"] = false;
        } elseif(!$this->is_assoc($json["htmlvideo"])) {
            $json["htmlvideo"] = false;
        } elseif (!$this->fallback) {
            global $wphtml5playerclass;
            $this->fallback = $wphtml5playerclass->videoreplaceJSON(null, $json["htmlvideo"], true);
        }

        if(!isset($json["htmlaudio"])) {
            $json["htmlaudio"] = false;
        } elseif(!$this->is_assoc($json["htmlaudio"])) {
            $json["htmlaudio"] = false;
        } elseif (!$this->fallback) {
            global $wphtml5playerclass;
            $this->fallback = $wphtml5playerclass->audioreplaceJSON(null, $json["htmlaudio"], true);
        }

        if(!isset($json["poster"]) || is_array($json["poster"])) {
            $json["poster"] = false;
        } elseif (!preg_match("#.(jpg|jpeg|png|gif)$#i", $json["poster"])) {
            $json["poster"] = false;
        } elseif (!$this->fallback) {
            $this->fallback = '<a href="'.$json["url"].'"><img src="'.$json["poster"].'"></a>';
        }

        if (!$this->fallback) {
            $this->fallback = '<a href="'.$json["url"].'">'.$json["url"].'</a>';
        }

        unset($json);

        return $this->getEmbedCode();
    }

    public function getObjectParams() {
        return $this->object_parameters;
    }

    public function getObjectAttribs() {
        return $this->object_attributes;
    }

    public function getEmbedCode() {
        if($this->htmlcode) {
            return $this->htmlcode;
        }
        return $this->buildHTMLCodeForObject();
    }

    public function setHeight($height) {
        return $this->setObjectAttribute('height', $height);
    }

    public function setWidth($width) {
        return $this->setObjectAttribute('width', $width);
    }

    public function setFallback($fallback) {
        $this->fallback = $fallback;
    }

    public function setParameter($param, $value = null) {
        return $this->setObjectParameter($param, $value);
    }

    public function setObjectParameter($parameter, $data = null) {
        if (!is_array($this->object_parameters)) return false;

        if ( is_array($parameter) ) {
            foreach ($parameter as $p => $d) {
                $this->object_parameters[$p] = $d;
            }

        } else {
            $this->object_parameters[$parameter] = $data;
        }

        return true;
    }

    public function setObjectAttribute($parameter, $data = null) {
        if (!is_array($this->object_attributes)) return false;

        if ( is_array($parameter) ) {
            foreach ($parameter as $p => $d) {
                $this->object_attributes[$p] = $d;
            }

        } else {
            $this->object_attributes[$parameter] = $data;
        }

        return true;
    }

    private function buildHTMLCodeForObject() {
        if(!$this->fallback) {
            $this->fallback = "";
        }
        $object_attributes = $object_parameters = '';

        foreach ($this->object_attributes as $parameter => $data) {
            $object_attributes .= '  ' . $parameter . '="' . $data . '"';
        }

        foreach ($this->object_parameters as $parameter => $data) {
            $object_parameters .= '<param name="' . $parameter . '" value="' . $data . '" />';
        }

        return sprintf("<object %s> %s %s </object>", $object_attributes, $object_parameters, $this->fallback);
    }

    private function setDefaultParameters($source, $flashvars, $width, $height) {
        $source = htmlspecialchars($source, ENT_QUOTES, null, false);
        $flashvars = htmlspecialchars($flashvars, ENT_QUOTES, null, false);

        $this->object_parameters = array(
                'movie' => $source,
                'quality' => 'high',
                'allowFullScreen' => 'true',
                'allowScriptAccess' => 'always',
                'pluginspage' => 'http://www.macromedia.com/go/getflashplayer',
                'autoplay' => 'false',
                'autostart' => 'false',
                'wmode' => 'transparent',
                'flashvars' => $flashvars,
        );

        $this->object_attributes = array(
                'type' => 'application/x-shockwave-flash',
                'data' => $source,
                'width' => $width,
                'height' => $height,
        );
    }

    public function setUserObjectAttribute($expression, $assoc_array) {
        if($this->is_assoc($assoc_array)) {
            $this->user_object_attributes[$expression] = $assoc_array;
        }
    }
    public function setUserObjectParameter($expression, $assoc_array) {
        if($this->is_assoc($assoc_array)) {
            $this->user_object_parameters[$expression] = $assoc_array;
        }
    }

    private function setUpObjectAttribute($attributes) {
        $allAttributes = $this->getObjectAttribs();
        if(isset($this->user_object_attributes["global"])) {
            foreach($this->user_object_attributes["global"] as $key => $value) {
                if(strtolower($key) == "append") {
                    if($this->is_assoc($this->user_object_attributes["global"][$key])) {
                        foreach($this->user_object_attributes["global"][$key] as $k => $v) {
                            $allAttributes["append"][strtolower($k)] = $v;
                        }
                    }
                } elseif(is_array($value)) {
                    // Do nothing.
                } else {
                    unset($allAttributes[$key]);
                    $allAttributes[strtolower($key)] = $value;
                }
            }
        }
        foreach($this->user_object_attributes as $expression => $data) {
            if(preg_match('#'.$expression.'#i',$allAttributes['data'])) {
                foreach($data as $key => $value) {
                    if(strtolower($key) == "append") {
                        if($this->is_assoc($data[$key])) {
                            foreach($data[$key] as $k => $v) {
                                unset($allAttributes["append"][strtolower($k)]);
                                $allAttributes["append"][strtolower($k)] = $v;
                            }
                        }
                    } elseif(is_array($value)) {
                        // Do nothing.
                    } else {
                        unset($allAttributes[$key]);
                        $allAttributes[strtolower($key)] = $value;
                    }
                }
            }
        }
        if($attributes) {
            foreach($attributes as $key => $value) {
                if(strtolower($key) == "append") {
                    if($this->is_assoc($attributes[$key])) {
                        foreach($attributes[$key] as $k => $v) {
                            $allAttributes["append"][strtolower($k)] = $v;
                        }
                    }
                } elseif(is_array($value)) {
                    // Do nothing.
                } else {
                    unset($allAttributes[$key]);
                    $allAttributes[strtolower($key)] = $value;
                }
            }
        }

        unset($allAttributes["id"]);
        unset($allAttributes["type"]);
        unset($allAttributes["width"]);
        unset($allAttributes["height"]);

        if(isset($allAttributes["append"])) {
            if($this->is_assoc($allAttributes["append"])) {
                $append = $allAttributes["append"];
                foreach($append as $key => $value) {
                    if(isset($allAttributes[strtolower($key)])) {
                        if(strtolower($key) == "append" || is_array($value)) {
                            // Do nothing.
                        } elseif(strtolower($key) == "data") {
                            $allAttributes[strtolower($key)] .= htmlspecialchars($value, ENT_QUOTES, null, false);
                        } else {
                            $allAttributes[strtolower($key)] .= $value;
                        }
                    }
                }
            }
            unset($allAttributes["append"]);
        }

        if($allAttributes) {
            $this->setObjectAttribute($allAttributes);
        }
    }

    private function setUpObjectParam($parameters) {
        $allParameters = $this->getObjectParams();
        if(isset($this->user_object_parameters["global"])) {
            foreach($this->user_object_parameters["global"] as $key => $value) {
                if(strtolower($key) == "append") {
                    if($this->is_assoc($this->user_object_parameters["global"][$key])) {
                        foreach($this->user_object_parameters["global"][$key] as $k => $v) {
                            $allParameters["append"][strtolower($k)] = $v;
                        }
                    }
                } elseif(is_array($value)) {
                    // Do nothing.
                } else {
                    unset($allParameters[strtolower($key)]);
                    $allParameters[strtolower($key)] = $value;
                }
            }
        }
        foreach($this->user_object_parameters as $expression => $data) {
            if(preg_match('#'.$expression.'#i',$allParameters['movie'])) {
                foreach($data as $key => $value) {
                    if(strtolower($key) == "append") {
                        if($this->is_assoc($data[$key])) {
                            foreach($data[$key] as $k => $v) {
                                unset($allParameters["append"][strtolower($k)]);
                                $allParameters["append"][strtolower($k)] = $v;
                            }
                        }
                    } elseif(is_array($value)) {
                        // Do nothing.
                    } else {
                        unset($allParameters[strtolower($key)]);
                        $allParameters[strtolower($key)] = $value;
                    }
                }
            }
        }
        if($parameters) {
            foreach($parameters as $key => $value) {
                if(strtolower($key) == "append") {
                    if($this->is_assoc($parameters[$key])) {
                        foreach($parameters[$key] as $k => $v) {
                            $allParameters["append"][strtolower($k)] = $v;
                        }
                    }
                } elseif(is_array($value)) {
                    // Do nothing.
                } else {
                    unset($allParameters[strtolower($key)]);
                    $allParameters[strtolower($key)] = $value;
                }
            }
        }

        unset($allParameters["flashvars"]);
        unset($allParameters["pluginspage"]);
        unset($allParameters["allowfullscreen"]);
        unset($allParameters["allowscriptaccess"]);

        if(isset($allParameters["append"])) {
            if($this->is_assoc($allParameters["append"])) {
                $append = $allParameters["append"];
                foreach($append as $key => $value) {
                    if(isset($allParameters[strtolower($key)])) {
                        if(strtolower($key) == "append" || is_array($value)) {
                            // Do nothing.
                        } elseif(strtolower($key) == "movie") {
                            $allParameters[strtolower($key)] .= htmlspecialchars($value, ENT_QUOTES, null, false);
                        } else {
                            $allParameters[strtolower($key)] .= $value;
                        }
                    }
                }
            }
            unset($allParameters["append"]);
        }

        if($allParameters) {
            $this->setObjectParameter($allParameters);
        }
    }

    public function is_assoc($var) {
        return is_array($var) && array_keys($var)!==range(0,sizeof($var)-1);
    }
}
