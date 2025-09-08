<?php
echo "mod_rewrite test<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . "<br>";
?>