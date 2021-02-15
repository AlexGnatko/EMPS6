<?php

$emps->no_smarty = true;

header("Content-Type: text/html; charset=utf-8");

if ($emps->auth->credentials("admin")) {


    $id = intval($key);
    $row = $emps->blocks->get_block($id);
    if (!$row) {
        echo "Block not found!"; exit;
    }

    $tpl = $smarty->createTemplate("sblk:".$row['id']);

//    echo $tpl->source->getContent();
    $html = $tpl->fetch();
    echo $html;
    //echo "<pre>".htmlspecialchars($html)."</pre>";

} else {
    echo "Admin access needed!";
}