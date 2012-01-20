<?php


/**
 * Latte template.
 *
 * @param  string  template file
 * @param  string  destination file
 * @param  array   variables
 * @return void
 */
$project->latte = function($template, $dest, array $params = array()) use ($project) {
	$project->log("Latte $template -> $dest");

	$template = new Nette\Templating\FileTemplate($template);
	$template->registerFilter(new Nette\Latte\Engine);
	$template->registerHelperLoader('Nette\Templating\Helpers::loader');
	$template->setParameters($params);
	$template->save($dest);
};
