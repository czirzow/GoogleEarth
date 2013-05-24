<?php

$ge_server = 'acars.bluehorizonintl.com';
$ge_root   = '/GoogleEarth/';
#$ge_server = '216.119.142.105';
#$ge_root   = '/acars/dev/GoogleEarth/';

//$ge_is_client = true;
$ge_debug  = false;
//$ge_debug  = true;

$xml_config = array(
	'colors' => array(
		/* hub   => color */
		'CYVR'  => 'faebe2', /* Vancouver */
		'EGLL'  => '6f2146', /* Heathrow */
		'KJFK'  => 'dcdede', /* John F. Kennedy */
		'KSFO'  => 'b09471', /* San Francisco */
		'KMIA'  => 'deda9f', /* Miami */
		'KPHX'  => 'e5c9bb', /* Phoenix */
		'WSSS'  => '877b91', /* Singapore */
		'YSSY'  => 'daaf4a', /* sydney */
	),
	'lines' => array(
		/* days => line width */
		5 => 1,
		4 => 1,
		3 => 2,
		2 => 2,
		1 => 2,
		0 => 3,
	),
);

date_default_timezone_set('America/Chicago');
