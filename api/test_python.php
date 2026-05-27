<?php

echo "<pre>";

$script = "C:\\xampp\\htdocs\\back-streamRank\\scraping\\amazon.py";

exec("python \"$script\" 2>&1", $output, $code);

echo "CODIGO: " . $code . "\n\n";

print_r($output);

echo "</pre>";
