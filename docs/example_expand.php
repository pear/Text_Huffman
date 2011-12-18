<?php

set_time_limit( 60 );

require 'Text/HuffmanExpand.php';


$he = new Text_HuffmanExpand();

try {
    $he->setFiles("sample_compressed.txt", "sample_expanded.txt");
    $he->expand();
    
    echo "Done.";
} catch (Exception $e) {
    echo $e->getMessage();    
}

?>
