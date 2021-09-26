<?php declare(strict_types=1);

use bin\spawn\IO;

const DS = DIRECTORY_SEPARATOR;
$newPathVars = [
    ROOT.DS."vendor".DS."nodejs".DS."nodejs".DS."bin"
];

foreach($newPathVars as $newPath) {
    $path = getenv("PATH");
    putenv("PATH=$path:$newPath");
}

IO::printLine("-> Added node to PATH");