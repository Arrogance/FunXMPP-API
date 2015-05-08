<?php
/**
 * Copyright 2015 Arrogance
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace FunXMPP\Core;

use FunXMPP\Core\Exception\CustomException;

class ProtocolNode
{

    /**
     * @var string
     */
    private $tag;
    
    /**
     * @var string
     */
    private $attributeHash;

    /**
     * @var string
     */
    private $children;

    /**
     * @var string
     */
    private $data;

    /**
     * Static
     *
     * @var bool
     */
    private static $cli = null;

    /**
     * Check if call is from command line
     *
     * @return bool
     */
    private static function isCli()
    {
        if (self::$cli === null) {
            //initial setter
            if (php_sapi_name() == "cli") {
                self::$cli = true;
            } else {
                self::$cli = false;
            }
        }
        return self::$cli;
    }

    /**
     * Get data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Get attributes
     *
     * @return string[]
     */
    public function getAttributes()
    {
        return $this->attributeHash;
    }

    /**
     * Get protocol node
     *
     * @return ProtocolNode[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $tag 
     * @param string $attributeHash
     * @param string $children
     * @param string $data 
     */
    public function __construct($tag, $attributeHash, $children, $data)
    {
        $this->tag = $tag;
        $this->attributeHash = $attributeHash;
        $this->children = $children;
        $this->data = $data;
    }

    /**
     * @param string $indent 
     * @param bool $isChild
     * @return string
     */
    public function nodeString($indent = "", $isChild = false)
    {
        $lt = "<";
        $gt = ">";
        $nl = "\n";
        if (!self::isCli()) {
            $lt = "&lt;";
            $gt = "&gt;";
            $nl = "<br />";
            $indent = str_replace(" ", "&nbsp;", $indent);
        }

        $ret = $indent . $lt . $this->tag;
        if ($this->attributeHash != null) {
            foreach ($this->attributeHash as $key => $value) {
                $ret .= " " . $key . "=\"" . $value . "\"";
            }
        }
        $ret .= $gt;
        if (strlen($this->data) > 0) {
            if (strlen($this->data) <= 1024) {
                $ret .= $this->data;
            } else {
                $ret .= " " . strlen($this->data) . " byte data";
            }
        }
        if ($this->children) {
            $ret .= $nl;
            $foo = array();
            foreach ($this->children as $child) {
                $foo[] = $child->nodeString($indent . "  ", true);
            }
            $ret .= implode($nl, $foo);
            $ret .= $nl . $indent;
        }
        $ret .= $lt . "/" . $this->tag . $gt;

        if (!$isChild) {
            $ret .= $nl;
            if (!self::isCli()) {
                $ret .= $nl;
            }
        }

        return $ret;
    }

    /**
     * @param $attribute
     * @return string
     */
    public function getAttribute($attribute)
    {
        $ret = "";
        if (isset($this->attributeHash[$attribute])) {
            $ret = $this->attributeHash[$attribute];
        }

        return $ret;
    }

    /**
     * @param string $needle
     * @return boolean
     */
    public function nodeIdContains($needle)
    {
        return (strpos($this->getAttribute("id"), $needle) !== false);
    }

    /**
     * Get children supports string tag or int index
     *
     * @param $tag
     * @return ProtocolNode
     */
    public function getChild($tag)
    {
        $ret = null;
        if ($this->children) {
            if (is_int($tag)) {
                if (isset($this->children[$tag])) {
                    return $this->children[$tag];
                } else {
                    return null;
                }
            }
            foreach ($this->children as $child) {
                if (strcmp($child->tag, $tag) == 0) {
                    return $child;
                }
                $ret = $child->getChild($tag);
                if ($ret) {
                    return $ret;
                }
            }
        }

        return null;
    }

    /**
     * @param $tag
     * @return bool
     */
    public function hasChild($tag)
    {
        return $this->getChild($tag) == null ? false : true;
    }

    /**
     * @param $offset integer
     */
    public function refreshTimes($offset = 0)
    {
        if (isset($this->attributeHash['id'])) {
            $id                        = $this->attributeHash['id'];
            $parts                     = explode('-', $id);
            $parts[0]                  = time() + $offset;
            $this->attributeHash['id'] = implode('-', $parts);
        }
        if (isset($this->attributeHash['t'])) {
            $this->attributeHash['t'] = time();
        }
    }

    /**
     * Print human readable ProtocolNode object
     *
     * @return string
     */
    public function __toString()
    {
        $readableNode = array(
            'tag'           => $this->tag,
            'attributeHash' => $this->attributeHash,
            'children'      => $this->children,
            'data'          => $this->data
        );

        return print_r($readableNode, true);
    }

}