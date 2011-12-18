<?php

set_time_limit( 60 );

require 'Text/HuffmanCompress.php';


$hc = new Text_HuffmanCompress();

try {
    $hc->setFiles("sample_orig.txt", "sample_compressed.txt");
    $hc->compress();
    
    echo "Done.";
} catch (Exception $e) {
    echo $e->getMessage();    
}

?>
