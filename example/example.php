<?php

include '../SideBySide.php';
$diff = new SideBySide();
list($source, $target) = $diff->compute(
    file_get_contents('texts/anthem-1.txt'),
    file_get_contents('texts/anthem-2.txt')
);
print "{$source}\n---------\n{$target}\n";