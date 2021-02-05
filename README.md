# EMPS Web Framework

EMPS stands for 'Engine', 'Model', 'Procedure', 'Smarty'. The earlier versions 
of the engine used to contain folders named 'e', 'm', 'p', and 'Smarty' to store 
the different component scripts of the framework. Those letters comprise the 
acronym 'emps'. The latest versions of EMPS do not contain those folders, but 
the name stuck anyway.

Smarty (http://www.smarty.net/) is a vital component of the framework, you have 
to store it somewhere in the include_path of your server's PHP. EMPS will expect 
to find Smarty 3 at: "Smarty3/libs/Smarty.class.php". Earlier versions supported 
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
easier to include the EMPS code itself.

## Work in Progress

There are no releases, no minor versions. All changes are tracked in this GitHub
repository. All servers download their EMPS from this repository.
