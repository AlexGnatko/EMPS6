<?php

require_once "functions.php";

foreach (['mysql_root_password', 'mysql_user_password', 'user_password', 'factory_root_pwd'] as $k) {
    if (!preg_match('/^[A-Za-z0-9_.@%+=-]{8,}$/', $config[$k] ?? '')) {
        $installer->say("config: {$k} must be 8+ chars of A-Za-z0-9_.@%+=-");
        $bad_config = true;
    }
}

$phpMyAdmin_url = "https://files.phpmyadmin.net/phpMyAdmin/5.2.3/phpMyAdmin-5.2.3-all-languages.tar.gz";
$phpMyAdmin_name = "phpMyAdmin-5.2.3-all-languages";

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
$phpini_path = $etc_path."/fpm/php.ini";
$installer->modify_ini_file($phpini_path, __DIR__."/templates/phpini.json");

$installer->say("Fixing php.ini (cli)...");
$phpini_path = $etc_path."/cli/php.ini";
$installer->modify_ini_file($phpini_path, __DIR__."/templates/phpini.json");

$installer->say("Fixing www.conf (fpm)...");
$fpmconf_path = $etc_path."/fpm/pool.d/www.conf";
$installer->modify_ini_file($fpmconf_path, __DIR__."/templates/wwwconf.json");

system("service php{$ver}-fpm restart");

$factory_hostname = $config['factory_hostname'];
$factory_root_pwd = $config['factory_root_pwd'];

function mysql_root_exec(string $sql): void
{
    $p = proc_open(
        ['mysql', '-u', 'root'],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes
    );
    if (!is_resource($p)) {
        throw new RuntimeException("Cannot start mysql client");
    }
    fwrite($pipes[0], $sql);
    fclose($pipes[0]);
    if (proc_close($p) !== 0) {
        throw new RuntimeException("MySQL command failed");
    }
}

function sql_str(string $s): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
}

$user = $config['main_user'];
if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $user)) {
    throw new RuntimeException("Bad main_user name");
}

$fp = sql_str($config['mysql_root_password']);
$up = sql_str($config['mysql_user_password']);
$db = "{$user}_emps_factory";

mysql_root_exec("
CREATE USER IF NOT EXISTS 'factory'@'localhost' IDENTIFIED WITH caching_sha2_password BY $fp;
ALTER USER 'factory'@'localhost' IDENTIFIED WITH caching_sha2_password BY $fp;
GRANT ALL PRIVILEGES ON *.* TO 'factory'@'localhost' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS '$user'@'localhost' IDENTIFIED WITH caching_sha2_password BY $up;
ALTER USER '$user'@'localhost' IDENTIFIED WITH caching_sha2_password BY $up;
GRANT ALL ON `{$user}\\_%`.* TO '$user'@'localhost';

CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
");

mkdir("/etc/nginx/ssl");
$installer->nginx_config_file("conf.d/logformat.conf");
$installer->nginx_config_file("conf.d/http.conf");

system("openssl req -x509 -nodes -newkey rsa:2048 -days 3650"
    . " -keyout /etc/nginx/ssl/nginx.key -out /etc/nginx/ssl/nginx.crt"
    . " -subj " . escapeshellarg("/CN=" . $factory_hostname));
chmod("/etc/nginx/ssl/nginx.key", 0600);

//$installer->nginx_config_file("ssl/nginx.crt");
//$installer->nginx_config_file("ssl/nginx.key");

$installer->nginx_config_file("sites-enabled/00-factory.conf", ['factory.ag38.ru' => $factory_hostname]);
$installer->nginx_config_file("deny.conf");
$installer->nginx_config_file("gzip.conf");
$installer->nginx_config_file("rewrite.conf", ['php7.0-fpm' => 'php'.$ver.'-fpm'], "rewrite.conf");

foreach (['8.2', $ver] as $v) {
    if (!is_file("/usr/sbin/php-fpm{$v}")) {
        continue;   // версия не установлена, сокета не будет
    }
    $installer->nginx_config_file("rewrite.conf", ['php7.0-fpm' => "php{$v}-fpm"], "rewrite-{$v}.conf");
}

mkdir("/srv");
mkdir("/srv/www");
mkdir("/srv/www/htdocs");
chdir("/srv/www");
system("git clone https://github.com/AlexGnatko/emps-factory.git");
chdir("emps-factory");
system("git pull");

system("groupadd git");
$installer->ensure_user($config);

mkdir("/srv/www/lib");
chdir("/srv/www/lib");
system("git clone https://github.com/AlexGnatko/EMPS.git");
system("git clone https://github.com/AlexGnatko/EMPS6.git");
chdir("EMPS");
system("git pull");
chdir("../EMPS6");
system("git pull");
chdir("..");

$composer_temp = "/tmp/composer-setup.php";
copy("https://getcomposer.org/installer", $composer_temp);
chmod($composer_temp, 0755);
system("php ".$composer_temp);
unlink($composer_temp);
rename("composer.phar", "/bin/composer");
putenv("COMPOSER_ALLOW_SUPERUSER=1");

chdir("/srv/www/lib/EMPS");
system("composer install");
chdir("/srv/www/lib/EMPS6");
system("composer install");
system("npm install -g bower");
system("php ./emps.php install");

chdir("/srv/www/emps-factory/");
system("composer install");

$factory_path = "/srv/www/emps-factory";
mkdir($factory_path."/htdocs/local");
mkdir($factory_path."/htdocs/local/temp", 0777);
mkdir($factory_path."/htdocs/local/temp_c", 0777);
mkdir($factory_path."/htdocs/local/upload", 0777);
chmod($factory_path."/htdocs/local/temp", 0777);
chmod($factory_path."/htdocs/local/temp_c", 0777);
chmod($factory_path."/htdocs/local/upload", 0777);

$installer->paths_config_file($factory_path."/sample_local.php",
    $factory_path."/htdocs/local/local.php",
    [
        'factory.somehost.com' => $factory_hostname,
        'user_emps_factory' => $config['main_user'] . "_emps_factory",
        'emps_factory_user' => $config['main_user'],
        'passW0rd' => $config['mysql_user_password'],
        'rootPassW0rd' => $config['mysql_root_password'],
    ]
    );

system("chown {$config['main_user']} /srv/www");
system("chown -R {$config['main_user']} /srv/www/htdocs");
system("chown -R {$config['main_user']} /srv/www/emps-factory");

copy(__DIR__."/templates/nginx/index.html", "/srv/www/htdocs/index.html");
copy(__DIR__."/templates/nginx/sites-available/default", "/etc/nginx/sites-available/default");

system("nginx -t", $rc);
if ($rc !== 0) { $installer->say("nginx config is broken, aborting"); exit(1); }
system("systemctl reload nginx");

copy($phpMyAdmin_url, "/srv/www/emps-factory/htdocs/".$phpMyAdmin_name.".tar.gz");
chdir("/srv/www/emps-factory/htdocs/");
system("rm -R {$phpMyAdmin_name}");
system("tar xzf ./{$phpMyAdmin_name}.tar.gz");
rename($phpMyAdmin_name, $config['phpmyadmin']);
chdir($config['phpmyadmin']);
system("rm -R setup");

$installer->paths_config_file("config.sample.inc.php",
    "config.inc.php",
    [
        "cfg['blowfish_secret'] = '';" => "cfg['blowfish_secret'] = '".md5(uniqid(time()))."';",
    ]
);


function local_get(string $host, string $path) {
    $ctx = stream_context_create(['http' => ['header' => "Host: $host\r\n", 'timeout' => 120]]);
    return @file_get_contents("http://127.0.0.1" . $path, false, $ctx);
}
foreach (["/sqlsync/", "/sqlsync/factory/", "/ensure_root/" . rawurlencode($factory_root_pwd) . "/"] as $p) {
    if (local_get($factory_hostname, $p) === false) $installer->say("FAILED: $p");
}
