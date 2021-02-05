<?php
global $emps;

$fn = $emps->common_module('config/smarty/plugins.php');
if (file_exists($fn)) {
    require_once $fn;
}

