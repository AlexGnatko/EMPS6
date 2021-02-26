<?php

class EMPS_Blocks {
    public function get_block($name) {
        global $emps;
        
        $id = intval($name);
        if ($id == $name) {
            $row = $emps->db->get_row("e_blocks", "id = {$id}");
        }
        if (!$row) {
            $e_name = $emps->db->sql_escape($name);
            $row = $emps->db->get_row("e_blocks", "code = '{$e_name}'");
        }
        if (!$row) {
            return false;
        }
        return $row;
    }

    public function get_block_params($dom) {

        $plst = [];

        foreach ($dom->params->param as $param) {
            $pa = [];
            foreach ($param->attributes() as $n => $v) {
                $pa[strval($n)] = strval($v);
            }
            if (isset($param->default)) {
                $pa['default'] = $param->default->children()->asXML();
            }

            if ($pa['name']) {
                $spa = $pa;
                unset($spa['name']);
                $plst[$pa['name']] = $spa;
            }
        }
        return $plst;
    }

    public function convert_xml_for_static(&$el) {
        if (is_null($el)) {
            return "";
        }
        $text = "";
        $name = strtolower($el->getName());

        $plst = [];
        foreach ($el->attributes() as $n => $v) {
            $plst[strval($n)] = strval($v);
        }

        if ($name == 'html') {
            return '{{eval var=$' . $plst['param'] . '}}';
        } elseif ($name == 'value') {
            return '{{$' . $plst['param'] . '}}';
        } elseif ($name == 't') {
            return $el->__toString() . PHP_EOL;
        } elseif ($name == 'array') {
            $param = $plst['param'];
            $rv = '';
//            $rv .= '{{$'.$param.'|var_dump}}'.PHP_EOL;
            $rv .= '{{foreach from=$' . $param . ' item="row" name="a"}}'.PHP_EOL;
            $rv .= '{{if $row.type == "ref"}}'.PHP_EOL;
            //$rv .= '{{"sblk:`$row.value`"}}'.PHP_EOL;
            $rv .= '{{$block_id = $row.value}}'.PHP_EOL;
            $rv .= '{{if !$avoid_block.$block_id}}'.PHP_EOL;
            $rv .= '{{$avoid_block.$block_id = true}}';
            $rv .= '{{include file="sblk:`$row.value`" vars=[]}}'.PHP_EOL;
            $rv .= '{{else}}Recursive block {{$block_id}}!'.PHP_EOL;
            $rv .= '{{/if}}'.PHP_EOL;
            $rv .= '{{/if}}'.PHP_EOL;
            $rv .= '{{if $row.type == "raw"}}'.PHP_EOL;
//            $rv .= "SBLT!".PHP_EOL;
//            $rv .= '{{$row|var_dump}}'.PHP_EOL;
            $rv .= '{{if !$row.template}}{{$row.template = "blocks/plain"}}{{/if}}'.PHP_EOL;
            $rv .= '{{include file="sblt:`$row.template`" vars=$row.value}}'.PHP_EOL;
            $rv .= '{{/if}}'.PHP_EOL;
            $rv .= '{{/foreach}}';
            return $rv;
        } else {
            foreach ($el->children() as $n => $v) {
                $text .= $this->convert_xml_for_static($v);
            }

            $params = [];
            foreach ($plst as $pn => $pv) {
                if ($pn == "add-class") {
                    $plst["class"] .= " {{\$" . $pv . "}}";
                }
            }
            foreach ($plst as $pn => $pv) {
                if ($pn == "add-class") {
                    continue;
                }
                $params[] = $pn . "=\"" . $pv ."\"";
            }
            $params = implode(" ", $params);

            $raw = trim($el->__toString());
            if ($raw) {
                $raw .= PHP_EOL;
            }

            if ($name != 'block') {
                $text = '<'.$name.' '.$params.'>'.PHP_EOL.$text.PHP_EOL.$raw.'</'.$name.'>'.PHP_EOL;
            }

        }
        return $text;
    }

    public function render_block_template_static($template) {
        global $smarty;

        $temp = $smarty->fetch("db:" . $template);
        if (!$temp) {
            return ['html' => ''];
        }

        $dom = new SimpleXMLElement($temp);

        $params = $this->get_block_params($dom);

        foreach ($params as $param) {
            $smarty->assign($param['name'], $param['default']);
        }

        $text = $this->convert_xml_for_static($dom->block);

//        echo $text; exit;
        $text = '
{{foreach from=$vars item="var"}}
{{if $var.vtype[0] == "a"}}
{{assign var=$var.name value=$var.value|json_decode:true}}
{{else}}
{{assign var=$var.name value=$var.value}}
{{/if}}
{{*$var.name}}: {{$var.value|var_dump*}}
{{/foreach}}
'.$text;

        return ['html' => $text];
    }

    public function list_template_params($template) {
        global $smarty;

        $temp = $smarty->fetch("db:" . $template);
        if (!$temp) {
            return false;
        }

        $dom = new SimpleXMLElement($temp);

        $params = $this->get_block_params($dom);
        return $params;
    }

    public function list_block_param_values($block_id) {
        global $emps;

        $lang = $emps->lang;
        $r = $emps->db->query("select * from ".TP."e_block_param_values where block_id = {$block_id}
            and lang = '{$lang}' 
            order by ord asc");

        $lst = [];
        while ($ra = $emps->db->fetch_named($r)) {
            $lst[] = $ra;
        }
        return $lst;
    }

    public function render_block_static($row) {
        $lst = $this->list_block_param_values($row['id']);

        $params = $this->list_template_params($row['template']);

        $names = [];
        foreach ($lst as $v) {
            $names[] = $v['name'];
        }

        $dlst = [];

        foreach ($params as $name => $value) {
            if (!in_array($name, $names)) {
                $dlst[] = ['name' => $name, 'v_char' => $value['default'], 'vtype' => $value['type']];
            } else {
                foreach ($lst as $v) {
                    if ($v['name'] == $name) {
                        $dlst[] = $v;
                    }
                }
            }
        }

        //var_dump($dlst); exit;
        $text = "";
        foreach ($dlst as $v) {
            if ($v['vtype'] == 'i') {
                $text .= '{{$' . $v['name'] . '=' . $v['v_int'] . '}}' . PHP_EOL;
            } elseif ($v['vtype'] == 'f') {
                $text .= '{{$' . $v['name'] . '=' . $v['v_float'] . '}}' . PHP_EOL;
            } elseif ($v['vtype']{0} == 'a') {
                $text .= '{{$' . $v['name'] . '="' . addslashes($v['v_text']) . '"|json_decode:true}}' . PHP_EOL;
//                echo json_encode(json_decode($v['v_text'], true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $text .= '{{capture assign="' . $v['name'] . '"}}' . PHP_EOL;
                if ($v['vtype'] == 't' || $v['vtype'] == 'h') {
                    $text .= $v['v_text'];
                } else {
                    $text .= $v['v_char'];
                }
                $text .= '{{/capture}}' . PHP_EOL;
            }
        }

        $text .= '{{include vars=[] file="sblt:' . $row['template'] . '"}}' . PHP_EOL;

//        echo $text; exit;

        return ['html' => $text];
    }

    public function save_param_value($block_id, $param, $lang, $idx) {
        global $emps;

        /*
         * `id` bigint NOT NULL AUTO_INCREMENT,
  `block_id` bigint NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(2) NOT NULL DEFAULT 'nn',
  `vtype` char(2)  NOT NULL DEFAULT 'c',
  `idx` int NOT NULL DEFAULT '0',
  `v_char` varchar(255) NOT NULL DEFAULT '',
  `v_text` mediumtext NOT NULL DEFAULT '',
  `v_int` bigint DEFAULT NULL,
  `v_float` float DEFAULT NULL,
  `cdt` bigint NOT NULL DEFAULT '0',
  `dt` bigint NOT NULL DEFAULT '0',
         */
        $nr = [];
        $nr['block_id'] = $block_id;
        $nr['name'] = $param['name'];
        $nr['lang'] = $lang;
        $qr = $nr;
        $nr['vtype'] = $param['type'];
        $nr['idx'] = $idx;
        if ($param['type'] == 'c') {
            $nr['v_char'] = $param['value'];
        }
        if ($param['type'] == 't' || $param['type'] == 'h') {
            $nr['v_text'] = $param['value'];
        } elseif ($param['type']{0} == 'a') {
            $nr['v_text'] = json_encode($param['value']);
        }
        $row = $emps->db->sql_ensure_row("e_block_param_values", $qr);
        if ($row) {
            $emps->db->sql_update_row("e_block_param_values", ['SET' => $nr], "id = {$row['id']}");
        }

    }

    public function check_expanded(&$array) {
        foreach ($array as &$v) {
            if (!isset($v['expanded'])) {
                $v['expanded'] = false;
            }
            if (is_array($v['value'])) {
                $this->check_expanded($v['value']);
            }
        }
    }
}