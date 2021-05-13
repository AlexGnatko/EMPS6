<?php
global $emps;

function smarty_plugin_downloads($params)
{
    global $smarty, $emps, $up;

    require_once($emps->common_module('uploads/uploads.class.php'));

    if (!isset($sp_up)) {
        $sp_up = new EMPS_Uploads;
    }

    $context_id = $params['context'];
    if ($context_id) {
        $lst = $sp_up->list_files($context_id, 1000);
        $smarty->assign("filelist", $lst);
        return $smarty->fetch("db:page/filelist");
    }

    $list = $params['list'];
    if ($list) {
        $lst = array();
        $xx = explode(",", $list);
        foreach ($xx as $v) {
            $id = $v + 0;
            $ra = $emps->db->get_row("e_files", "id = " . $id);
            $ra['fsize'] = format_size($ra['size']);
            $lst[] = $ra;

        }

        $smarty->assign("filelist", $lst);
        return $smarty->fetch("db:page/filelist");
    }
}

function smarty_plugin_video($params)
{
    global $smarty, $emps;

    $id = $params['id'];
    $mctx = $emps->p->get_context(DT_VIDEO, 1, $id);

    $video = $emps->db->get_row("e_videos", "id=$id");
    if ($video) {
        $video = $emps->p->read_properties($video, $mctx);

        $smarty->assign("video", $video);
        return $smarty->fetch("db:videos/videocon");
    }
}


$fn = $emps->common_module('config/smarty/plugins.php');
if (file_exists($fn)) {
    require_once $fn;
}

