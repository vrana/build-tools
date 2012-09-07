<?php

/**
 * Makefile for building Nette Framework.
 *
 * Call task 'main' to build a full release.
 * The release built will be stored in 'dist' directory.
 *
 * Can be used for version 2.0 or higher.
 */

require 'tools/Nette/nette.min.php';
use Nette\Utils\Finder;


// configuration
$project->gitExecutable = 'C:\Program Files (x86)\Git\bin\git.exe';
$project->phpExecutable = realpath('tools/PHP-5.3/php.exe');
$project->php52Executable = realpath('tools/PHP-5.2/php.exe');
$project->apiGenExecutable = realpath('tools/ApiGen/apigen.php');
$project->zipExecutable = realpath('tools/7zip/7z.exe');
$project->compilerExecutable = realpath('tools/Google-Closure-Compiler/compiler.jar');


// add custom tasks
require 'tasks/apiGen.php';
require 'tasks/git.php';
require 'tasks/latte.php';
require 'tasks/minify.php';
require 'tasks/minifyJs.php';
require 'tasks/netteLoader.php';
require 'tasks/convert52.php';
require 'tasks/convert53.php';
require 'tasks/php.php';
require 'tasks/zip.php';


$project->main = function($tag = 'master', $label = '2.1dev') use ($project) {
	$project->log("Building {$label}");

	$dir53 = "NetteFramework-{$label}-PHP5.3";
	$dir52p = "NetteFramework-{$label}-PHP5.2";
	$dir52n = "NetteFramework-{$label}-PHP5.2-nonprefix";
	$distDir = "dist/" . substr($label, 0, 3);

	$project->exportGit($dir53, $tag);

	// rename *.md and delete some files
	foreach (Finder::findFiles('*.md')->from($dir53) as $file) {
		$project->rename($file, substr($file, 0, -2) . 'txt');
	}
	$project->delete("$dir53/sandbox/license.txt");
	$project->delete("$dir53/examples/license.txt");
	$project->delete("$dir53/tools/license.txt");
	$project->delete("$dir53/composer.json");
	$project->delete("$dir53/.travis.yml");
	$project->copy("$dir53/client-side/forms/netteForms.js", "$dir53/sandbox/www/js/netteForms.js");

	// build specific packages
	$project->delete($dir52p);
	$project->copy($dir53, $dir52p);
	$project->log("Building 5.2 prefixed package");
	$project->buildPackage($dir52p, '52p');

	$project->delete($dir52n);
	$project->copy($dir53, $dir52n);
	$project->log("Building 5.2-nonprefix package");
	$project->buildPackage($dir52n, '52n');

	$project->log("Building 5.3 package");
	$project->buildPackage($dir53, '53');

	// build minified version
	$project->minify("$dir53/Nette", "$dir53/Nette-minified/nette.min.php", TRUE);
	$project->minify("$dir52p/Nette", "$dir52p/Nette-minified/nette.min.php", FALSE);
	$project->minify("$dir52n/Nette", "$dir52n/Nette-minified/nette.min.php", FALSE);

	// lint & try run PHP files
	$project->log("Linting files");
	$project->lint($dir53, $project->phpExecutable);
	$project->lint($dir52p, $project->php52Executable);
	$project->lint($dir52n, $project->php52Executable);

	// copy Nette to submodules
	$project->copy("$dir53/Nette", "$dir53/sandbox/libs/Nette");
	$project->copy("$dir52p/Nette", "$dir52p/sandbox/libs/Nette");
	$project->copy("$dir52n/Nette", "$dir52n/sandbox/libs/Nette");

	// build API doc
	$apiGenConfig = dirname($project->apiGenExecutable) . '/apigen.neon';
	$project->apiGen("$dir53/Nette", "$dir53/API-reference", $apiGenConfig, "Nette Framework $label API");
	$project->apiGen("$dir52p/Nette", "$dir52p/API-reference", $apiGenConfig, "Nette Framework $label (for PHP 5.2, prefixed) API");
	$project->apiGen("$dir52n/Nette", "$dir52n/API-reference", $apiGenConfig, "Nette Framework $label (for PHP 5.2, un-prefixed) API");

	// create archives
	if ($tag === 'master') {
		$project->zip("$distDir/../snapshots/NetteFramework-{$label}-(".date('Y-m-d').").7z", array($dir53, $dir52p, $dir52n));
	}

	$project->zip("$distDir/$dir53.zip", $dir53);
	$project->zip("$distDir/$dir52p.zip", $dir52p);
	$project->zip("$distDir/$dir52n.zip", $dir52n);
	$project->zip("$distDir/$dir53.tar.bz2", $dir53);
	$project->zip("$distDir/$dir52p.tar.bz2", $dir52p);
	$project->zip("$distDir/$dir52n.tar.bz2", $dir52n);


	// build PEAR
	$dirPear = "Nette-$label";
	$project->log("Building PEAR package");
	$project->delete($dirPear);
	$project->copy("$dir53/Nette", "$dirPear/Nette");
	$project->copy("$dir53/readme.txt", "$dirPear/readme.txt");
	$project->copy("$dir53/license.txt", "$dirPear/license.txt");
	$project->latte("tasks/package.xml.latte", "package.xml", array(
		'time' => time(),
		'version' => $label,
		'state' => 'stable',
		'files' => Finder::findFiles('*')->from($dirPear),
	));

	$project->zip("$distDir/../pear/$dirPear.tgz", array($dirPear, "package.xml"));
	$project->delete("package.xml");
};



$project->exportGit = function($dir, $tag = NULL) use ($project) {
	$project->delete($dir);
	$project->gitClone('git://github.com/nette/nette.git', $tag, $dir);
	$project->gitClone('git://github.com/nette/examples.git', $tag, "$dir/examples");
	$project->gitClone('git://github.com/nette/sandbox.git', $tag, "$dir/sandbox");
	$project->gitClone('git://github.com/nette/tools.git', NULL, "$dir/tools");

	if (PHP_OS === 'WINNT') {
		$project->exec("attrib -H $dir\.htaccess* /s /d");
		$project->exec("attrib -R $dir\* /s /d");
	}

	// create history.txt
	$project->git("log -n 500 --pretty=\"%cd (%h): %s\" --date-order --date=short > $dir/history.txt", $dir);

	// expand $WCREV$ and $WCDATE$
	$wcrev = $project->git('log -n 1 --pretty="%h"', $dir);
	$wcdate = $project->git('log -n 1 --pretty="%cd" --date=short', $dir);
	foreach (Finder::findFiles('*.php', '*.txt')->from($dir)->exclude('3rdParty') as $file) {
		$project->replace($file, array(
			'#\$WCREV\$#' => $wcrev,
			'#\$WCDATE\$#' => $wcdate,
		));
	}

	// remove git files
	foreach (Finder::findDirectories(".git")->from($dir)->childFirst() as $file) {
		$project->delete($file);
	}
	foreach (Finder::findFiles(".git*")->from($dir) as $file) {
		$project->delete($file);
	}
};



// supported packages: 53, 52p and 52n
$project->buildPackage = function($dir, $package = '5.3') use ($project) {
	if ($package !== '53') {
		$project->delete("$dir/examples/Micro-blog");
		$project->delete("$dir/examples/Micro-tweet");
		$project->delete("$dir/tools/Code-Migration");
	} else {
		$project->replace("$dir/tools/Requirements-Checker/checker.php", array(
			'#5\.2\.0#' => '5.3.0',
		));
	}

	foreach (Finder::findFiles('*.php', '*.phpt', '*.phpc', '*.inc', '*.phtml', '*.latte', '*.neon')->from($dir)->exclude('www/adminer') as $file) {
		$project->{"convert$package"}($file, TRUE);
	}
	$project->netteLoader("$dir/Nette");

	// shrink JS & CSS
	foreach (Finder::findFiles('*.js', '*.css', '*.phtml')->from("$dir/Nette") as $file) {
		$project->minifyJs($file);
	}
};



$project->lint = function($dir, $phpExecutable) use ($project) {
	// try run
	$project->php("$dir/Nette-minified/nette.min.php", $phpExecutable);

	foreach (Finder::findFiles('*.php', '*.phpt')->from($dir) as $file) {
		$project->phpLint($file, $phpExecutable);
	}
};
