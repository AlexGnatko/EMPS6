# EMPS Core Concepts for Claude

## URL Routing

URL vars in order: `pp, key, start, ss, sd, sk, sm, sx, sy`

URL `/manage-pool-matches/5/0/result/1/` → `$pp=manage-pool-matches`, `$key=5`, `$start=0`, `$ss=result`, `$sd=1`

- `$pp` → maps to module folder: `manage-pool-matches` → `modules/manage/pool/matches/matches.php`
- Hyphens = folder separators in pp
- `-` in URL = empty value (undefined)
- **`$start` is the pagination offset** — do NOT use as item ID unless there's no pagination on that page
- `$sd` is best for secondary filter (e.g. contest_id filter in matches list)
- Slugs in key: `/pool/5-football-world-cup/` → `$key="5-football-world-cup"`, `intval($key)` = 5

## PHP Module Files

Each module = folder with same-named files:
- `modules/pool/pool.php` — controller (PHP logic)
- `modules/pool/pool.nn.htm` — view (Smarty template)
- `modules/pool/pool.js` — JS (served via /mjs/)

PHP controller runs first, then Smarty template renders.

## EMPS URL Helpers

```php
$emps->loadvars();      // restore URL vars from internal storage
$emps->savevars();      // save current URL vars to internal storage
$emps->clearvars();     // clear all URL vars (except lang)
$emps->elink();         // build URL from current URL vars
$emps->slink($v, $var); // set one var and return elink()
$emps->redirect_elink(); // redirect to elink()
```

Generating links for list items:
```php
while ($ra = ...) {
    $emps->loadvars();
    $key = $ra['id'] . '-' . $ra['slug'];
    $ss = "info";
    $ra['elink'] = $emps->elink();
    $lst[] = $ra;
}
$emps->loadvars(); // IMPORTANT: restore after loop
```

## JSON Responses

```php
$emps->json_ok(['data' => $data]); exit;
$emps->json_error("Message"); exit;
```

POST data from axios (JSON body) is automatically merged into `$_POST` by EMPS `pre_init()`. So `$_POST['payload']` works.

## SQLSync

`modules/{name}/sql/module.sql` → run at `/sqlsync/{name}/`

- Uses `CREATE TEMPORARY TABLE temp_{name}` syntax
- Adds missing columns/indexes automatically
- Does NOT delete columns (manual `ALTER TABLE DROP COLUMN` needed)
- List modules in `modules/_common/config/sqlsync.txt` for heartbeat auto-sync

## MJS (Module JS Loader)

URL pattern: `/mjs/{module-pp}/{filename}.js`
- `/mjs/pool/pool.js` → `modules/pool/pool.js`
- `/mjs/comp-table/table.js` → `modules/comp/table/table.js`
- `/mjs/manage-pool-contests/contests.js` → `modules/manage/pool/contests/contests.js`
- Add `// minify` as first line to auto-uglify

## Enum Values

Defined in `modules/_common/config/enum.nn.txt`:
```
14  pool_match_status:100=Ставки открыты;200=Закрыты;300=Результат внесён
```
- Use steps of 100 (or 10) to allow inserting values between
- In Vue: `enum_val('pool_match_status', row.status)`

## Page Properties

```php
$emps->page_property("vuejs", 1);       // load Vue.js
$emps->page_property("mainapp", true);  // use mainapp.js (osharov.ru pattern)
$emps->page_property("flatpickr", 1);   // date/time picker
```

## Auth

```php
$emps->auth->credentials("users")   // logged in user
$emps->auth->credentials("admin")   // admin
$emps->auth->USER_ID                 // current user id
$emps->auth->json_user($user_id)     // safe user object (no password!)
```
