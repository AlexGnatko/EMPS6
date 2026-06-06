# EMPS Development Patterns for Claude

## Admin Module Pattern (no vted)

3 files: `modules/manage/{section}/{name}.php` + `.nn.htm` + `.js`

**PHP handler skeleton:**
```php
if ($emps->auth->credentials("admin")) {
    $emps->page_property("vuejs", 1);
    $emps->page_property("mainapp", true);

    $perpage = 50;
    $start = intval($start);   // pagination offset
    $item_id = intval($key);   // current item id

    if ($_GET['load_list'] ?? false) {
        $r = $emps->db->query("select SQL_CALC_FOUND_ROWS * from ".TP."table
            order by id desc limit {$start}, {$perpage}");
        $pages = $emps->count_pages($emps->db->found_rows($r));
        $lst = [];
        while ($ra = $emps->db->fetch_named($r)) {
            $ra = $emps->db->row_types("table", $ra);  // ALWAYS! fixes int/string types
            $emps->loadvars();
            $key = $ra['id'];
            $ss = "info";
            $ra['elink'] = $emps->elink();
            $lst[] = $ra;
        }
        $emps->loadvars();
        $emps->json_ok(['lst' => $lst, 'pages' => $pages]); exit;
    }

    if ($_GET['load_row'] ?? false) {
        $row = $emps->db->get_row("table", "id = {$item_id}");
        if (!$row) { $emps->json_error("Not found"); exit; }
        $row = $emps->db->row_types("table", $row);
        $emps->loadvars(); $key = ""; $ss = "";
        $row['back_link'] = $emps->elink();
        $emps->loadvars();
        $emps->json_ok(['row' => $row]); exit;
    }

    if ($_POST['post_create'] ?? false) {
        $nr = ['name' => $_POST['payload']['name']];
        $emps->db->sql_insert_row("table", ['SET' => $nr]);
        $id = $emps->db->last_insert();
        $emps->loadvars(); $key = $id; $ss = "info";
        $emps->json_ok(['link' => $emps->elink()]); exit;
    }

    if ($_POST['post_save'] ?? false) {
        $nr = $_POST['payload'];
        $id = intval($nr['id']);
        unset($nr['id'], $nr['cdt'], $nr['dt']);
        $emps->db->sql_update_row("table", ['SET' => $nr], "id = {$id}");
        $emps->json_ok(); exit;
    }

    if ($_POST['post_remove_selected'] ?? false) {
        foreach ($_POST['payload'] as $raw_id) {
            $id = intval($raw_id);
            if ($id > 0) $emps->db->query("delete from ".TP."table where id={$id}");
        }
        $emps->json_ok(); exit;
    }
} else { $emps->deny_access("AdminNeeded"); }
```

**JS mixin skeleton:**
```js
emps_scripts.push(function () {
    window.main_app_mixins.push({
        mixins: [table_mixin, navigation_mixin],
        data: function () {
            return { lst: [], row: {}, selected_row: {}, enums: {}, newrow: {name: ''} };
        },
        mounted: function () {
            $(".app-loading").hide();
            EMPS.vue_load_enums(this, "my_enum_name");
        },
        methods: {
            create_item: function () {
                this.create_new_row({name: this.newrow.name});
                this.newrow.name = '';
                this.close_modal("modalCreate");
            }
        }
    });
});
```

**Template skeleton:**
```smarty
{{script src="/mjs/comp-formats/formats.js" defer=1}}
{{script src="/mjs/comp-mixins/common.js" defer=1}}
{{script src="/mjs/comp-table/table.js" defer=true}}
{{script src="/mjs/comp-navigation/navigation.js" defer=1}}
{{script src="/mjs/manage-section-name/name.js" defer=1}}
{{include file="db:_comp/selector"}}
{{include file="db:_comp/modal"}}

<div>
    <template v-if="list_mode">
        <!-- list: table with navigate(row.elink) links -->
    </template>
    <template v-else>
        <!-- tabs: EMPS.elink({ss:'info'},[]) -->
        <template v-if="path.ss == 'info'">
            {{include file="db:_manage/section/name/pads,info"}}
        </template>
    </template>
    <modal id="modalCreate">
        <template slot="header">New Item</template>
        <!-- form fields -->
        <template slot="actions">
            <button @click="create_item" class="button is-primary">Create</button>
        </template>
    </modal>
</div>
<div class="app-loading">{{include file="db:inc/spinner"}}</div>
```

## Key Gotchas

1. **Always call `row_types()`** after `fetch_named()`/`get_row()` — otherwise numbers come as strings, `enum_val()` won't match
2. **`$start` = pagination offset** — never use as item ID if page has a paginator
3. **`$emps->loadvars()` after loop** — always restore URL vars after generating elinks in a while loop
4. **EMPS parses JSON POST body** — axios sends JSON, EMPS merges it into `$_POST` in `pre_init()`, so `$_POST['payload']` works
5. **`mainapp` vs `no_mainapp`** — depends on project. In one project: `page_property("mainapp", true)` enables mainapp.js. In another, `page_property("no_mainapp", true)` disables it. Check `templates/site/headscripts.nn.htm` for the project's pattern
6. **Selector component** — `type="table_name_without_TP"`, shows `name` field, returns `id`. Include with `{{include file="db:_comp/selector"}}`
7. **Tabs via `path.ss`** — `EMPS.elink({ss:'info'},[])` to navigate, `v-if="path.ss == 'info'"` to show content
8. **`$sd` for secondary filter** — better than GET params. Server reads `$sd`, JS navigates via `EMPS.elink({sd: val}, ['key','ss'])`

## Useful EMPS Methods

```php
$emps->db->row_types("table_name", $row)    // cast DB row to proper PHP types
$emps->db->get_row("table", "where_clause") // get single row
$emps->db->sql_insert_row("table", ['SET' => $nr])
$emps->db->sql_update_row("table", ['SET' => $nr], "id = {$id}")
$emps->db->last_insert()
$emps->db->found_rows($r)                   // total rows for SQL_CALC_FOUND_ROWS
$emps->count_pages($total)                  // returns pages object for vue_paginator
$emps->transliterate_url($str)              // "Привет мир" → "privet-mir"
$emps->auth->json_user($user_id)            // safe user object (no password)
```

## EMPS_Service (periodic tasks)

Say, a file like `modules/do/work/work.php` (URL `/do-work/`):

```php
<?php

$emps->plaintext_response();

require_once $emps->core_module("service.class");
$srv = new EMPS_Service();
$srv->init("_unique_task_name", 60*60); // throttle: once per hour
if ($srv->is_runnable()) {
    // do work
}
```

This is usually called from a `modules/heartbeat/project.php` like this:

```php
<?php

$emps->plaintext_response();

require_once $emps->core_module("heartbeat.class");

$hb = new EMPS_Heartbeat();

if($emps->website_ctx == $emps->default_ctx){
    // common tasks
    echo "Common tasks: ";

    $hb->add_url("/do-work/");

    $hb->execute();

}else{
    // per-website tasks
}
```

## pool.class.php Pattern

Create a domain class for shared logic across modules (`/modules/pool/pool.class.php`):
```php
class Pool {
    public $table_contests = "pool_contests";
    
    public function explain_contest($row) {
        global $emps;
        return $emps->db->row_types($this->table_contests, $row);
    }
}
// Usage: require_once $emps->page_file_name("_pool,pool.class", "controller");
// $pool = new Pool();
```
