<?php

require_once "functions.php";

$config = json_decode(file_get_contents("config.json"), true);

$bad_config = false;
if ($config['factory_hostname'] == "hostname") {
    $installer->say("config: factory_hostname is not specified");
    $bad_config = true;
}

if ($config['factory_root_pwd'] == "password") {
    $installer->say("config: factory_root_pwd is not specified");
    $bad_config = true;
}

if ($config['mysql_root_password'] == "password") {
    $installer->say("config: mysql_root_password is not specified");
    $bad_config = true;
}

if ($config['user_password'] == "password") {
    $installer->say("config: user_password is not specified");
    $bad_config = true;
}

if ($config['mysql_user_password'] == "password") {
    $installer->say("config: mysql_user_password is not specified");
    $bad_config = true;
}

if ($bad_config) {
    $installer->say("Please edit the config.json file and try again (run `php install.php`).");
    exit;
}

$version = phpversion();
$installer->say( "PHP version: {$version}");

$x = explode(".", $version);
$ver = $x[0].".".$x[1];

$installer->say("PHP ver: {$ver}");

$etc_path = "/etc/php/{$ver}";

$installer->say("Fixing php.ini (fpm)...");
$phpini_path = $etc_path."/fpm/php-test.ini";
$installer->modify_ini_file($phpini_path, __DIR__."/templates/phpini.json");

$installer->say("Fixing php.ini (cli)...");
$phpini_path = $etc_path."/cli/php-test.ini";
$installer->modify_ini_file($phpini_path, __DIR__."/templates/phpini.json");

$installer->say("Fixing www.conf (fpm)...");
$fpmconf_path = $etc_path."/fpm/pool.d/www-test.conf";
$installer->modify_ini_file($fpmconf_path, __DIR__."/templates/wwwconf.json");

system("service php{$ver}-fpm restart");

$factory_hostname = $config['factory_hostname'];
$factory_root_pwd = $config['factory_root_pwd'];

file_put_contents("/tmp/mysql-init-emps.txt",
"ALTER USER 'root'@'localhost' IDENTIFIED BY '{$config['mysql_root_password']}';
    CREATE USER '{$config['main_user']}'@'%' IDENTIFIED BY '{$config['mysql_user_password']}';
    grant all privileges on `{$config['main_user']}\_%`.* to '{$config['main_user']}'@'%';
    create database if not exists `{$config['main_user']}_emps_factory` 
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

system("service mysql stop");
$installer->say("Interrupt the mysql server by pressing Ctrl+C");
system("mysqld --user=mysql --init-file=/tmp/mysql-init-emps.txt --console");
system("service mysql stop");
system("service mysql start");

$installer->nginx_config_file("conf.d/logformat.conf");
$installer->nginx_config_file("sites-enabled/00-factory.conf", ['factory.ag38.ru' => $factory_hostname]);
$installer->nginx_config_file("deny.conf");
$installer->nginx_config_file("gzip.conf");
$installer->nginx_config_file("rewrite.conf", ['php7.0-fpm' => 'php'.$ver.'-fpm'], "rewrite-{$ver}.conf");

mkdir("/srv");
mkdir("/srv/www");
chdir("/srv/www");
system("git clone http://gitlab.ag38.ru/root/emps-factory.git");

$installer->ensure_user($config);

$factory_path = "/srv/www/emps-factory";
mkdir($factory_path."/htdocs/local");
mkdir($factory_path."/htdocs/local/temp", 0777);
mkdir($factory_path."/htdocs/local/temp_c", 0777);
mkdir($factory_path."/htdocs/local/upload", 0777);

$installer->paths_config_file($factory_path."/sample_local.php",
    $factory_path."/htdocs/local/local.php",
    [
        'factory.somehost.com' => $factory_hostname,
        'user_emps_factory' => $config['main_user'] . "_emps_factory",
        'passW0rd' => $config['mysql_user_password'],
        'rootPassW0rd' => $config['mysql_root_password'],
    ]
    );

system("chown {$config['main_user']} /srv/www");
system("chown -R {$config['main_user']} /srv/www/emps-factory");

system("service nginx reload");

file_get_contents("http://{$factory_hostname}/sqlsync/");
file_get_contents("http://{$factory_hostname}/sqlsync/factory/");
file_get_contents("http://{$factory_hostname}/ensure_root/{$factory_root_pwd}/");
