<?php


/**
 * Creates a zip archive.
 *
 * @param  string  archive.zip or .7z
 * @param  array|string  items to compress
 * @return void
 *
 * @depend $project->zipExecutable
 */
$project->zip = function($archive, $items) use ($project) {
	$project->log("Creating archive $archive");

	if ($tarArchive = strpos($archive, '.tar.')) {
		$tarArchive = substr($archive, 0, $tarArchive + 4);
		$project->zip($tarArchive, $items);
		$items = $tarArchive;
	}
	
	$cmd = escapeshellarg($project->zipExecutable) . ' a -mx9 ' . escapeshellarg($archive);
	foreach ((array) $items as $item) {
		$cmd .= ' ' . escapeshellarg($item);
	}

	if (is_file($archive)) {
		unlink($archive);
	}

	$project->exec($cmd);
	
	if ($tarArchive) {
		unlink($tarArchive);
	}
};
