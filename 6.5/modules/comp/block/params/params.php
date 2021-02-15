<?php

if ($_POST['post_get_params']) {
    $payload = $_POST['payload'];

    $template = str_replace("{slash}", '/', $key);

    $nlst = $emps->blocks->list_template_params($template);

    $lst = [];
    foreach ($nlst as $n => $v) {
        $v['name'] = $n;
        if (!isset($v['value'])) {
            $v['value'] = $v['default'];
        }

        $lst[] = $v;
    }

    $emps->json_ok(['lst' => $lst]);

}