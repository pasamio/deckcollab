#!/usr/bin/env php
<?php

define('DECKCOLLAB_ROOT', dirname(__DIR__));

$config = json_decode(file_get_contents(DECKCOLLAB_ROOT . '/config/config.json'));

if (!isset($_SERVER['argv']))
{
	die('This script is for command line usage only.');
}

if ($_SERVER['argc'] < 2)
{
	die(usage());
}

function usage()
{
	echo $_SERVER['argv'][0] . ": Usage?!?\n";
}


$repository = $config->repository->pages;
$originalDirectory = $repository . '/' . dirname($_SERVER['argv'][1]);

if (!file_exists($originalDirectory))
{
	die("Directory does not exist: $originalDirectory\n");
}


echo "Building indexes for $originalDirectory\n";

foreach(glob($originalDirectory . '/*', GLOB_ONLYDIR) as $directory)
{
	if (preg_match('/\b[0-9a-f]{40}\b/', basename($directory)))
	{
		processDirectory($directory, $config);	
	}
}

function processDirectory($directory, $config)
{
	$webRoot = $config->webRoot;
	$titleParts = array();
	foreach (explode(' ', ucwords(str_replace('_', ' ', basename(dirname($directory))))) as $word)
	{
		if (preg_match('/[0-9]/', $word))
		{
			$word = strtoupper($word);
		}
		$titleParts[] = $word;
	}
	$title = implode(' ', $titleParts);

	$document = <<<DOC
<html>
	<head>
		<title>$title</title>
		<link href="$webRoot/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
	</head>

	<body>
		<div id="myCarousel" class="carousel slide" align="center">
			<div class="carousel-inner">
{{INNER}}
			</div>
			<a class="carousel-control left" href="#myCarousel" data-slide="prev">&lsaquo;</a>
  			<a class="carousel-control right" href="#myCarousel" data-slide="next">&rsaquo;</a>
		</div>
		<script src="http://code.jquery.com/jquery-latest.js"></script>
		<script src="$webRoot/bootstrap/js/bootstrap.min.js"></script>
	</body>
</html>
DOC;

	$inner = '';
	foreach (glob($directory . '/thumbnails/*.png') as $file)
	{
		$imageSize = getimagesize($file);
		$webPath = str_replace($config->repository->pages, $webRoot, $file);
		if (empty($inner))
		{
			$inner .= '				<div class="active item">';
		}
		else
		{
			$inner .= '				<div class="item">';
		}
		$inner .= '<img src="'. $webPath .'" ' . $imageSize[3] . ' /></div>' . "\n";
	}

	$document = str_replace('{{INNER}}', $inner, $document);

	file_put_contents($directory . '/index.html', $document);
}