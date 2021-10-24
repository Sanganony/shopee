<?php
$extractor = new DUP_PRO_Extraction($_POST);

$extractor->runExtraction();
$extractor->setFilePermission();
$extractor->finishExtraction();
