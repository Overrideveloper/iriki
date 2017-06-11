<?php


//database
require_once(__DIR__ . '/database/autoload.php');

//utilities
require_once(__DIR__ . '/utilities/autoload.php');

foreach (glob(__DIR__ . "/*.php") as $filepath)
{
    //skip this very file
    if ($filepath == __FILE__) continue;
    require_once($filepath);
}

?>
