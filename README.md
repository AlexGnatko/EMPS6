# EMPS Web Framework

EMPS stands for 'Engine', 'Model', 'Procedure', 'Smarty'. The earlier versions 
of the engine used to contain folders named 'e', 'm', 'p', and 'Smarty' to store 
the different component scripts of the framework. Those letters comprise the 
acronym 'emps'. The latest versions of EMPS do not contain those folders, but 
the name stuck anyway.

Smarty (http://www.smarty.net/) is a vital component of the framework. It is currently
installed using composer, but in previous versions were found on the include_path.
Earlier versions supported 
Smarty 2, but this one only accepts Smarty 3.

EMPS is an MVC framework, which means that PHP code is totally separated from HTML 
templates by means of Smarty. Controllers and views are stored together as a *.php 
and a *.nn.htm file in a sub-folder of the "modules" folder. The "nn" in the view's 
file name means 'default language'. You can have two or more views 
for different languages.

The PHP controllers are regular plain PHP procedure scripts. The scripts can access 
all EMPS functions through the $emps object variable and all Smarty functions through 
the $smarty object variable.

EMPS supports multiple websites on a single set of modules (one engine - many websites) 
and several languages across websites or even on a single website.

The core of the EMPS framework is supposed to be loaded through "require_once" from 
somewhere on the "include_path". This will enable several websites on the same server 
to share the EMPS code (which will enable updates, bugfixing, etc.).

EMPS is Git-friendly. No data, no HTML templates, and no code vital to the website 
being developed is ever stored in the database, all code and templates are stored 
in the module folders as files.

The SQL database structure is stored in a specially-cooked SQL file that enables 
"sqlsync" - automatic synchronization of the actual database structure with the 
SQL file. Update the SQL file, call /sqlsync/ on the website, and your website's 
SQL (MySQL) database gets updated automatically (no manual adding of new fields 
in phpMyAdmin).

## Version 6

The updated version of EMPS aims to get rid of a bulk of legacy code that is not going to
be ever used again in new projects. Versions 4.5 and 5.0 are retained to keep old projects 
running.

All non-Vue interactive features (selectors, editors, admin interface, etc.) are thrown
away. Only the bluma/Vue version of the admin interface and vted (Vue Table Editor) is
kept. A Bootstrap version of the interface and vted might be added later, if needed.

### Version Numbers

The decimal part of the version number has nothing to do with consecutive numbering of
releases. Number 5 stands for "SQL", and number 0 stands for "NoSQL". Hence, 6.0 is
a NoSQL version of EMPS (mongoDB-based), and 6.5 is an SQL version EMPS (MySQL-based,
a PostreSQL abstraction layer might be added later).

## Installation

EMPS 6 is now completely self-reliant, there is no need to keep any dependencies
in the include_path. All the dependencies are now installed via Composer.

The `emps` script in the root folder sets up all the JS and CSS dependencies,
some of which are delivered with bower, some with npm. Some other dependences were
easier to include in the EMPS code itself.

## Work in Progress

There are no releases, no minor versions. All changes are tracked in this GitHub
repository. All servers download their EMPS from this repository.

## URL Routing and Folder Structure

The primary routing procedure is the following:
* The (the part after the hostname and before the `?` or `#`) URL is split
into parts by the slash symbol `/`. So, `/manage-orders/1/-/info/` becomes 
`['', 'manage-orders', '1', '-', 'info']`. 
* The values are then assigned to *EMPS variables*. The following variables
get extracted from a URL:

```
// Variable/Path mapping string. Variables listed in the order that is used
// to retrieve them from URLs.
define('EMPS_URL_VARS', 'pp,key,start,ss,sd,sk,sm,sx,sy');
```
* In our example, `/manage-orders/1/-/info/` becomes `$pp = 'manage-orders'`,
`$key = '1'`, `$start = ''` (hypen `-` means "empty value"), `$ss = 'info'`. Those
are actual global PHP variables, can be accessed from anywhere in subsequent PHP code.
* The module router only needs the `$pp` variable. If the value contains hypens, it replaces
hypens with slashes and uses that as the local folder path, e.g. `manage-orders` becomes
`htdocs/modules/manage/orders`.
* By default, EMPS expects to see at least an HTML template file or a PHP file in that module folder 
(or both files). It assumes that the PHP script file name is the same as the name of the
last folder (in this case, `orders`) with a `.php` extension. So, it looks for
`htdocs/modules/manage/orders/orders.php` and `require_once` loads that PHP script.
* If the PHP script does not exist, or if it doesn't execute `$emps->no_smarty = false`, EMPS
assumes that it needs to display the default Smarty template for that page.
It assumes that the file name of the template is the same as the name of the folder, but 
with a `.nn.htm` extension. You can actually have multiple templates for many languages, like 
`.en.htm` or `.ru.htm` or `.it.htm`. That depends on the default language setting of the
current website. But the default file is in this case - 
`htdocs/modules/manage/orders/orders.nn.htm`. That file then gets displayed inside
the Smarty template of the website (between header and footer).
* So, the PHP script can do some work and assign some Smarty variables like 
`$smarty->assign('order', $order)` and then the template will display `{{$order.client_name}}` or
`{{$order.price|money}}`.
* The Vue.js interaction works a bit differently. There, the Smarty template contains
the code that is required to load and initialize a Vue.js app or a mix-in.
The Smarty templates contain Vue.js templates (which makes an interesting and very
helpful combination of features sometimes). 
And then the Vue.js
app send requests to the `orders.php` script (or any other script for that matter)
 that return JSON data (no Smarty templates and no HTML).
They do it by calling `$emps->json_ok($data); exit;` or `$emps->json_error('No such order!'); exit;`.
Note that the `exit` prevents any further processing and displaying of the HTML code.
* The `mjs` component loader. Sometimes there are also `.css` and `.js` and `.vue` (in EMPS, 
`.vue` files contain only the plain HTML code of the template) files.
In order to load any one of the from JS or from the template `<script>` tags,
use the following URL template: `/mjs/{$module_pp}/{$filename}`, where `{$module_pp}` is
the value of the `$pp` variable in the URL of this module (in our case it will be `manage-orders`),
and `{$filename}` is the actual file name. For example, if we also need to load the JS
script for this module from this JS file - `htdocs/modules/manage-orders/orders.js`
we can open this external URL: `/mjs/manage-orders/orders.js`. The file name can be any name,
it doesn't have to match the folder name here. So it can be like `/mjs/manage-orders/extra.css`.