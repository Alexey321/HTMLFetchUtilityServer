<?php
spl_autoload_register(function ($class) {
    include 'classes/' . $class . '.php';
});

$url = $_GET['url'];
$htmlElementName = $_GET['htmlElementName'];
$htmlElementCounterObj = new HtmlElementCounter($url, $htmlElementName);

$arrayData = array
(
	'responseId' => 'infoData',
	'infoData' => $htmlElementCounterObj
);

echo json_encode($arrayData); // Передача информации на клиент.
