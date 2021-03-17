<?php

class EMPS_StaticContent
{
    public $table_name = "e_static_content";
    public $path = EMPS_SCRIPT_PATH."/static-content";
    public $web_path = EMPS_SCRIPT_WEB."/static";

    public function path_from_md5($md5) {
        $c1 = $md5{0};
        $c2 = $md5{1};
        $c3 = $md5{2};
        $f = $this->path."/{$c1}";
        if (!file_exists($f)) {
            mkdir($f, 0777);
        }
        $f = $this->path."/{$c1}/{$c2}";
        if (!file_exists($f)) {
            mkdir($f, 0777);
        }
        $f = $this->path."/{$c1}/{$c2}/{$c3}";
        if (!file_exists($f)) {
            mkdir($f, 0777);
        }
        $f = $this->path."/{$c1}/{$c2}/{$c3}/{$md5}";
        if (!file_exists($f)) {
            mkdir($f, 0777);
        }
        return $f;
    }

    public function web_path_from_md5($md5) {
        $c1 = $md5{0};
        $c2 = $md5{1};
        $c3 = $md5{2};
        $f = $this->web_path."/{$c1}/{$c2}/{$c3}/{$md5}";
        return $f;
    }


    public function create_file($filename, $data, $context_id, $ord = 10) {
        global $emps;

        $md5 = md5(uniqid($filename.time()));
        $path = $this->path_from_md5($md5);
        $full_filename = $path."/".$filename;
        file_put_contents($full_filename, $data);
        $size = filesize($full_filename);
        $nr = [];
        $nr['md5'] = $md5;
        $nr['size'] = $size;
        $nr['filename'] = $filename;
        $nr['context_id'] = $context_id;
        $nr['ord'] = $ord;
        $nr['user_id'] = $emps->auth->USER_ID;

        $emps->db->sql_insert_row($this->table_name, ['SET' => $nr]);

        $id = $emps->db->last_insert();

        return $id;
    }

    public function get_file($id) {
        global $emps;
        $row = $emps->db->get_row($this->table_name, "id = {$id}");
        if ($row) {
            return $this->explain_file($row);
        }
        return false;
    }

    public function explain_file($row) {
        $row['full_path'] = $this->path_from_md5($row['md5'])."/".$row['filename'];
        $row['web_path'] = $this->web_path_from_md5($row['md5'])."/".$row['filename'];

        return $row;
    }

    public function list_files($context_id, $count = 100) {
        global $emps;

        $limit = "";
        if ($count > 0) {
            $limit = " limit {$count} ";
        }

        $r = $emps->db->query("select * from ".TP.$this->table_name." where context_id = {$context_id} 
            order by ord asc, id asc {$limit}");
        $lst = [];
        while ($ra = $emps->db->fetch_named($r)) {
            $ra = $this->explain_file($ra);
            $lst[] = $ra;
        }
        return $lst;
    }
}