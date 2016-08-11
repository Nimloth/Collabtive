<?php
//set PHP configuration
ini_set("arg_separator.output", "&amp;");
//default to utf8
ini_set("default_charset", "utf-8");
//allow larger and more file uploads than default
ini_set("post_max_size", "128M");
ini_set("upload_max_filesize","128M");
ini_set("max_file_uploads","50");

// Set content security policy header. This instructs the browser to block various unsafe behaviours.
header("Content-Security-Policy:default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval';frame-src 'self'");
// Start output buffering with gzip compression and start the session
ob_start('ob_gzhandler');
session_start();
// get full path to collabtive
define("CL_ROOT", realpath(dirname(__FILE__)));
// configuration to load
define("CL_CONFIG", "standard");
// collabtive release date
define("CL_PUBDATE", "1426201200");
// uncomment next line for debugging
error_reporting(E_ALL || E_STRICT);
// include config file , pagination and global functions
require(CL_ROOT . "/config/" . CL_CONFIG . "/config.php");
//include composer dependencies
require(CL_ROOT . "/vendor/autoload.php");
// load init functions
require(CL_ROOT . "/include/initfunctions.php");
require(CL_ROOT . "/include/pluginFunctions.php");

//assume mysql as the default db
if (!isset($db_driver)) {
    $db_driver = "mysql";
}
// Start database connection
// Depending on the DB driver, instantiate a PDO object with the necessary credentials.
$db_drivers = PDO::getAvailableDrivers();
if (!in_array($db_driver, $db_drivers)) {
    die('Requested to use ' . $db_driver . ', which is not enabled!');
}
switch ($db_driver) {
    case "mysql":
        if (empty($db_name) or empty($db_user)) {
            die("You must set db_name and db_user in /config/" . CL_CONFIG . "/config.php to use mysql, or set db_driver to \"sqlite\" to use an SQLite database!");
        }
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        break;
    case "sqlite":
        $conn = new PDO("sqlite:" . CL_ROOT . "/files/collabtive.sdb");
        break;
}
// Start template engine
$template = new Smarty();
// STOP smarty from spewing notices all over the html code
$template->error_reporting = E_ALL & ~E_NOTICE;
// get the available languages
$languages = getAvailableLanguages();
// get URL to collabtive
$url = getMyUrl();

//$template->force_compile = true;

$template->assign("url", $url);
$template->assign("languages", $languages);
// set the version number for display
$template->assign("myversion", "3.0");
$template->assign("cl_config", CL_CONFIG);
// Assign globals to all templates
if (isset($_SESSION["userid"])) {
    // unique ID of the user
    $userid = $_SESSION["userid"];
    // name of the user
    $username = $_SESSION["username"];
    // timestamp of last login
    $lastlogin = $_SESSION["lastlogin"];
    // selected locale
    $locale = $_SESSION["userlocale"];
    // gender
    $gender = $_SESSION["usergender"];
    // what the user may or may not do
    $userpermissions = $_SESSION["userpermissions"];
    // update user lastlogin for the onlinelist
    $mynow = time();
    if (isset($conn)) {
        $conn->exec("UPDATE user SET lastlogin='$mynow' WHERE ID = $userid");
    }
    // assign it all to the templates
    $template->assign("userid", $userid);
    $template->assign("username", $username);
    $template->assign("lastlogin", $lastlogin);
    $template->assign("usergender", $gender);
    $template->assign("userpermissions", $userpermissions);
    $template->assign("loggedin", 1);
} else {
    $template->assign("loggedin", 0);
    $userpermissions = array();
    $settings = array();
    $userid = 0;
}
// get system settings
if (isset($conn)) {
    // Set PDO options
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    // $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    // create a global mylog object for loging system events
    $mylog = new mylog();
    // get a settings object, and fetch an array containing the system settings
    $settingsObj = (object)new settings();
    $settings = $settingsObj->getSettings();
    // define a constant that holds the default dateformat
    define("CL_DATEFORMAT", $settings["dateformat"]);
    // set the default TZ for date etc
    date_default_timezone_set($settings["timezone"]);
    $template->assign("settings", $settings);
} else {
    $settings = array();
}
// Set template directory
// If no directory is set in the system settings, default to the standard theme
if (isset($settings['template'])) {
    $template->setTemplateDir(CL_ROOT . "/templates/$settings[template]/");
    // $template->tname = $settings["template"];
} else {
    $template->setTemplateDir(CL_ROOT . "/templates/standard/");
    // $template->tname = "standard";
}
// If no locale is set, get the settings locale or default to english
if (!isset($locale)) {
    if (isset($settings["locale"])) {
        $locale = $settings['locale'];
    } else {
        $locale = "en";
    }
    $_SESSION['userlocale'] = $locale;
}
// if detected locale doesnt have a corresponding langfile , use system default locale
// if, for whatever reason, no system default language is set, default to english as a last resort
if (!file_exists(CL_ROOT . "/language/$locale/lng.conf")) {
    $locale = $settings['locale'];
    $_SESSION['userlocale'] = $locale;
}
// Set locale directory
$template->setConfigDir(CL_ROOT . "/language/$locale/");
// Smarty 3 seems to have a problem with re-compiling the config if the user config is different than the system config.
// this forces a compile of the config.
// uncomment this if you have issues with language switching
// $template->compileAllConfig('.config',true);
// read language file into PHP array
$langfile = readLangfile($locale);
$template->assign("langfile", $langfile);
$template->assign("locale", $locale);
// css classes for headmenue
// this indicates which of the 3 main stages the user is on
$mainclasses = array("desktop" => "desktop",
    "profil" => "profil",
    "admin" => "admin"
);
$template->assign("mainclasses", $mainclasses);
// get current year and month
$they = date("Y");
$them = date("n");
$template->assign("theM", $them);
$template->assign("theY", $they);
// if user is logged in
if (isset($userid)) {
    $project = new project();

    //list of vue components to load in the <head> section
    $vueComponentList = ["pagination", "progress"];
    $template->assign("vueComponents", $vueComponentList);
    //create plugins manager
    $pluginManager = new pluginManager();
    $pluginManager->loadPlugins();

}

