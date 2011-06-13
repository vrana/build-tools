<?php

/**
 * Nette Make.
 *
 * Copyright (c) 2011 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

namespace Make;


require __DIR__ . '/Project.php';
require __DIR__ . '/DefaultTasks.php';


$options = getopt('f:t:a:v');
$buildFile = isset($options['f']) ? $options['f'] : 'build.php';
if (!is_file($buildFile)) {
	if (isset($options['f'])) {
		echo("Missing build file $buildFile");
	} else { ?>
Make
----
Usage: php make.php [options]

Options:
	-f <path>   path to build file (default is 'build.php')
	-t <target> target to run (default is 'main')
	-a <value>  adds argument
	-v          verbose mode

<?php }
	die(255);
}


// initialize project
set_time_limit(0);
date_default_timezone_set('Europe/Prague');

$project = new Project;
$project->verbose = isset($options['v']);
$project->logFile = rtrim(getcwd(), '\\/') . '/make.log';
@unlink($project->logFile);
DefaultTasks::initialize($project);

// load build file
$project->log("Build file: $buildFile");
require $buildFile;

// run
$target = isset($options['t']) ? $options['t'] : 'main';
$args = isset($options['a']) ? (array) $options['a'] : array();
$project->run($target, $args);
