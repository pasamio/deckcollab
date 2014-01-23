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


$repository = $config->repository->presentations;
$originalFilePath = $repository . '/' . $_SERVER['argv'][1];
$originalDirectory = dirname($originalFilePath);
if (!file_exists($originalFilePath))
{
	die("File does not exist: " . $originalFilePath . "\n");
}

$baseFileName = basename($originalFilePath);
$baseFile = preg_replace('#(.*)\.[^.]*#', '\1', $baseFileName);

$replacedArgs = str_replace(
	array('{GIT_DIR}', '{GIT_WORKDIR}'), 
	array($config->repository->presentations . '/.git', $config->repository->presentations),
	$config->tools->git_version->args
);
$args = array($config->tools->git_version->path, $replacedArgs);
$version = exec(implode(' ', $args));


$versionDirectory = $config->repository->pages . '/' . basename($originalDirectory) . '/' . $version;
if (!file_exists($versionDirectory))
{
	mkdir($versionDirectory);
}

echo "Starting conversion for $originalFilePath\n";
echo "Destination directory: " . $versionDirectory . "\n";

$pdfFilePath = $versionDirectory . '/' . $baseFile . '.pdf';

if (!file_exists($pdfFilePath))
{
	echo "Converting document to PDF...\n";
	$args = array($config->tools->soffice->path, $config->tools->soffice->args, $versionDirectory, $originalFilePath);
	exec(implode(' ', $args));

	if (!file_exists($pdfFilePath))
	{
		die("There was an error creating the PDF file!\n");
	}
}

$textFilePath = $versionDirectory . '/' . $baseFile . '.txt';

if (!file_exists($textFilePath))
{
	echo "Extracting text from PDF document...\n";
	$args = array($config->tools->pdftotext->path, $config->tools->pdftotext->args, $pdfFilePath, $textFilePath);
	exec(implode(' ', $args));

	if (!file_exists($textFilePath))
	{
		die("There was an error extracting the text from the PDF!\n");
	}

	echo "Expanding text document into individual pages...\n";
	$file = file_get_contents($textFilePath);
	$pages = explode("\f", $file);

	$pagesPath = $versionDirectory . '/pages';
	mkdir($pagesPath);
	foreach ($pages as $pageNumber => $page)
	{
		if (strlen($page))
		{
			file_put_contents("$pagesPath/page-$pageNumber.txt", $page);
		}
	}
}

$imagePath = $versionDirectory . '/images';

if (!file_exists($imagePath))
{
	echo "Extracting images from PDF...\n";
	mkdir($imagePath);
	$args = array($config->tools->pdfimages->path, $config->tools->pdfimages->args, $pdfFilePath, $imagePath . '/image');
	exec(implode(' ', $args));

	echo "Converting images from PPM to PNG...\n";
	foreach(glob($imagePath . '/*.ppm') as $ppmPath)
	{
		echo "Converting $ppmPath\n";
		$args = array($config->tools->mogrify->path, $config->tools->mogrify->args, $ppmPath);
		exec(implode(' ', $args));
		$pngPath = str_replace('.ppm', '.png', $ppmPath);
		if (!file_exists($pngPath))
		{
			die("Failed to create PNG file: $pngPath");
		}
		unlink($ppmPath);
	}

}


$slidePath = $versionDirectory . '/slides';
$thumbnailPath = $versionDirectory . '/thumbnails';

if (!file_exists($slidePath))
{
	echo "Generating page slides from PDF...\n";
	mkdir ($slidePath);
	$args = array($config->tools->gs->path, $config->tools->gs->args, $slidePath . '/slide_%03d.png', $pdfFilePath);
	exec(implode(' ', $args));
}

if (!file_exists($thumbnailPath))
{
	mkdir($thumbnailPath);
	foreach (glob($slidePath . '/*.png') as $source)
	{
		$destination = $thumbnailPath . '/' . str_replace('slide_', 'thumbnail_', basename($source));
		$args = array($config->tools->convert->path, $config->tools->convert->args, $source, $destination);
		exec(implode(' ', $args));
	}
}

echo "Completed conversion.\n";


