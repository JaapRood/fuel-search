<?php
/**
 * Search
 *
 * Search lets you search for stuff in data, allowing spelling mistakes!
 *
 * @package		Search
 * @version		0.5
 * @author		Jaap Rood / Ronald Mansveld
 * @license		MIT License
 * @link		http://github.com/JaapRood/fuel-search
 */

Autoloader::add_core_namespace('Search');


Autoloader::add_classes(array(
	'Search\\Search'			=> __DIR__.'/classes/search.php',
));


/* End of file bootstrap.php */