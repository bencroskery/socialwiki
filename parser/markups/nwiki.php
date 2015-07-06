<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * NWiki parser implementation
 *
 * @author Josep Arús
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_socialwiki
 */
require_once("wikimarkup.php");

class nwiki_parser extends socialwiki_markup_parser {

    protected $blockrules = array(
        'nowiki' => array(
            'expression' => "/^<nowiki>(.*?)<\/nowiki>/ims",
            'tags' => array(),
            'token' => array('<nowiki>', '</nowiki>')
        ),
        'header' => array(
            'expression' => "/^\ *(={1,6})\ *(.+?)(={1,6})\ *$/ims",
            'tags' => array(), // None.
            'token' => '='
        ),
        'line_break' => array(
            'expression' => "/^-{3,4}\s*$/im",
            'tags' => array(),
            'token' => '---'
        ),
        'desc_list' => array(
            'expression' => "/(?:^.+?\:.+?\;\n)+/ims",
            'tags' => array(),
            'token' => array(':', ';'),
            'tag' => 'dl'
        ),
        'table' => array(
            'expression' => "/\{\|(.+?)\|\}/ims"
        ),
        'tab_paragraph' => array(
            'expression' => "/^(\:+)(.+?)$/ims",
            'tag' => 'p'
        ),
        'list' => array(
            'expression' => "/^((?:\ *[\*|#]{1,5}\ *.+?)+)"
            . "(\n\s*(?:\n|<(?:h\d|pre|table|tbody|thead|tr|th|td|ul|li|ol|hr)\ *\/?>))/ims",
            'tags' => array(),
            'token' => array('*', '#')
        ),
        'paragraph' => array(
            'expression' => "/^\ *((?:<(?!\ *\/?(?:h\d|pre|table|tbody|thead|tr|th|td|ul|li|ol|hr)"
            . "\ *\/?>)|[^<\s]).+?)\n\s*\n/ims",
            // Not specified -> all tags (null or unset).
            'tag' => 'p'
        )
    );
    protected $tagrules = array(
        'nowiki' => array(
            'expression' => "/<nowiki>(.*?)<\/nowiki>/is",
            'token' => array('<nowiki>', '</nowiki>')
        ),
        'image' => array(
            'expression' => "/\[\[image:(.+?)\|(.+?)\]\]/is",
            'token' => array("[[image:", "|alt]]")
        ),
        'attach' => array(
            'expression' => "/\[\[attach:(.+?)\]\]/is",
            'token' => array("[[attach:", "|name]]")
        ),
        'link' => array(
            'expression' => "/\[\[(.+?)\]\]/is",
            'tag' => 'a',
            'token' => array("[[", "]]")
        ),
        'url_tag' => array(
            'expression' => "/\[(.+?)\]/is",
            'tag' => 'a',
            'token' => array("[", "]")
        ),
        'url' => array(
            'expression' => "/(?<!=\")((?:https?|ftp):\/\/[^\s\n]+[^,\.\?!:;\"\'\n\ ])/i",
            'tag' => 'a',
            'token' => 'http://'
        ),
        'italic' => array(
            'expression' => "/\'{3}(.+?)(\'{3}(?:\'{2})?)/is",
            'tag' => 'em',
            'token' => array("'''", "'''")
        ),
        'bold' => array(
            'expression' => "/\'{2}(.+?)\'{2}/is",
            'tag' => 'strong',
            'token' => array("''", "''")
        )
    );

    /**
     * Block hooks
     */
    protected function header_block_rule($match) {
        if ($match[1] != $match[3]) {
            return $match[0];
        }

        $num = strlen($match[1]);

        return $this->generate_header($match[2], $num);
    }

    protected function table_block_rule($match) {
        $rows = explode("\n|-", $match[1]);
        $table = array();
        foreach ($rows as $r) {
            $colsendline = explode("\n", $r);
            $cols = array();
            foreach ($colsendline as $ce) {
                $cols = array_merge($cols, $this->get_table_cells($ce));
            }

            if (!empty($cols)) {
                $table[] = $cols;
            }
        }
        return $this->generate_table($table);
    }

    private function get_table_cells($string) {
        $string = ltrim($string);
        $type = (!empty($string) && $string[0] == "!") ? 'header' : 'normal';
        $string = substr($string, 1);
        if (empty($string)) {
            $normalcells = array();
        } else {
            $normalcells = explode("||", $string);
        }
        $cells = array();
        foreach ($normalcells as $nc) {
            $headercells = explode("!!", $nc);
            $countheadercells = count($headercells);
            for ($i = 0; $i < $countheadercells; $i++) {
                $cells[] = array($type, $headercells[$i]);
                $type = 'header';
            }
            $type = 'normal';
        }
        return $cells;
    }

    protected function tab_paragraph_block_rule($match) {
        $num = strlen($match[1]);
        $text = $match[2];
        $html = "";
        for ($i = 0; $i < $num - 1; $i++) {
            $html = parser_utils::h('p', $html, array('class' => 'socialwiki_tab_paragraph'));
        }

        return parser_utils::h('p', $text, array('class' => 'socialwiki_tab_paragraph'));
    }

    protected function desc_list_block_rule($match) {
        preg_match_all("/^(.+?)\:(.+?)\;$/ims", $match[0], $listitems, PREG_SET_ORDER);

        $list = "";
        foreach ($listitems as $li) {
            $term = $li[1];
            $this->rules($term);

            $description = $li[2];
            $this->rules($description);

            $list .= parser_utils::h('dt', $term) . parser_utils::h('dd', $description);
        }

        return $list;
    }

    /**
     * Tag functions
     */

    /**
     * Bold and italic similar to creole...
     */
    protected function italic_tag_rule($match) {
        $text = $match[1];
        if (strlen($match[2]) == 5) {
            $text .= "''";
        }

        $this->rules($text, array('only' => array('bold')));
        if (strpos($text, "''") !== false) {
            $text = str_replace("''", $this->protect("''"), $text);
        }

        return array($text, array());
    }

    /**
     * Link tag functions
     */
    protected function link_tag_rule($match) {
        return $this->format_link($match[1]);
    }

    protected function url_tag_tag_rule($match) {
        $text = trim($match[1]);
        if (preg_match("/(.+?)\|(.+)/is", $text, $matches)) {
            $link = $matches[1];
            $text = $matches[2];
        } else if (preg_match("/(.+?)\ (.+)/is", $text, $matches)) {
            $link = $matches[1];
            $text = $matches[2];
        } else {
            $link = $text;
        }
        return array($this->protect($text), array('href' => $this->protect($link)));
    }

    protected function url_tag_rule($match) {
        $url = $this->protect($match[1]);
        $options = array('href' => $url);
        return array($url, $options);
    }

    /**
     * Attachments & images
     */
    protected function image_tag_rule($match) {
        return $this->format_image($match[1], $match[2]);
    }

    protected function attach_tag_rule($match) {
        $parts = explode("|", $match[1]);

        $url = array_shift($parts);

        if (count($parts) > 0) {
            $text = array_shift($parts);
        }

        $extension = substr($url, strrpos($url, "."));
        $text = empty($text) ? $url : $text;

        $imageextensions = array('jpg', 'jpeg', 'png', 'bmp', 'gif', 'tif');
        if (in_array($extension, $imageextensions)) {
            $align = 'left';
            if (count($parts) > 0) {
                switch (strtolower($text)) {
                    case 'right':
                        $align = 'right';
                        break;
                    case 'center':
                        $align = 'center';
                        break;
                    default:
                        $align = 'left';
                }
                $text = $parts[0];
            }
            return $this->format_image($url, $text, $text, $align);
        } else {
            $url = $this->real_path($url);
            return parser_utils::h('a', $text, array('href' => $url, 'class' => 'socialwiki-attachment'));
        }
    }
}
