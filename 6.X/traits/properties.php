<?php

trait EMPS_Common_Properties
{
    /**
     * Sets a page property
     *
     * @param $name string Page property name (code)
     * @param $value mixed Page property value
     */
    public function page_property($name, $value)
    {
        $this->page_properties[$name] = $value;
    }

    /**
     * Copy properties from a content item (page)
     *
     * @param $code string Page URI
     */
    public function copy_properties($code)
    {
        // Load properties from get_content_data for the content item $code and save them as $page_properies

        $item = $this->get_db_content_item($code);
        $props = $this->get_content_data($item);
        unset($props['_full']);

        $this->page_properties = array_merge($this->page_properties, $props);
    }

    /**
     * Set properties from a text file (can be obtained from a Smarty template with $lang and {{syn...}} applied)
     *
     * @param $code string Property codes followed by "equals" signs and property values, one property per line
     */
    public function parse_properties($code)
    {
        $x = explode("\n", $code);
        foreach($x as $v){
            $v = trim($v);
            $xx = explode("=", $v);
            $code = trim($xx[0]);
            $value = trim($xx[1]);
            $this->page_properties[$code] = $value;
        }
    }

    public function page_properties_from_settings($list){
        $x = explode(",", $list);
        foreach($x as $v){
            $v = trim($v);
            $value = $this->get_setting($v);
            if(!$value) {
                continue;
            }
            $this->page_property($v, $value);
        }
    }

    public function handle_modified()
    {
        if ($this->last_modified > 0) {
            header("Expires: " . date("r", $this->expire_guess()));
            header("Last-Modified: " . date("r", $this->last_modified));

            $if_modified = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
            if ($if_modified) {
                $if_dt = strtotime($if_modified);
                if ($this->last_modified <= $if_dt) {
                    header("HTTP/1.1 304 Not Modified");
                    exit();
                }
            }
        }
    }


}