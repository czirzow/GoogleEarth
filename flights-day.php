<?php
/*
Copyright (c) 2012, Curt Zirzow
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met: 

1. Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer. 

2. Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution. 

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those
of the authors and should not be interpreted as representing official policies, 
either expressed or implied, of the FreeBSD Project.
 */

$day = strftime('%Y-%m-%d', strtotime($_REQUEST['day']));

$kml_name = 'bhi-' . $day;
$kml_file =  PATH_GE_KML . $kml_name . '.kmz';

$last_update = Flights::getUpdated($day);
$generate_kml = true;

if(file_exists($kml_file)) {
	$file_time = filemtime($kml_file);
	if($file_time < $last_update) {
		$generate_kml = true;
	} else {
		$generate_kml = false;
	}
}


if($ge_debug) {
	$generate_kml = true;
}

if($generate_kml) {

	$fp_lock = fopen(PATH_GE_KML . 'lock.txt', 'w');

	$have_lock = $fp_lock && flock($fp_lock, LOCK_EX);

	Flights::forDay($day);
	Flights::fly();
	$kml = Flights::kml();

	if($have_lock) {
		$zip = new ZipArchive;
		$res = $zip->open($kml_file, ZipArchive::CREATE);
		if ($res === TRUE) {
			$zip->addFromString('doc.kml', $kml);
		}

		$zip->close();
		touch($kml_file, $last_update);
		flock($fp_lock, LOCK_UN);
	} else {
		header('Content-Type: text/xml');
		echo $kml;
		exit;
	}
}


if($ge_debug) {
	header('Content-Type: text/xml');
	echo $kml;
	exit;
}


header('Content-Type: application/vnd.google-earth.kmz');

//if(! $ge_is_client) {
// google earth doesn't like these
	//header('Content-Length: ' . filesize($kml_file));
	//header('Content-Disposition', 'attachment; filename=' . $kml_name . '.kml');
//}

readfile($kml_file);

