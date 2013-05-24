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

require_once('config.php');
$ge_server = 'acars.bluehorizonintl.com';
$ge_root   = '/GoogleEarth/';

$test='';
if($_REQUEST['test']) {
	$test='&amp;test='.$_REQUEST['test'];
}
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo <<<EOL
<kml xmlns="http://earth.google.com/kml/2.1">
<Document>
EOL;

for($i = 1; $i <= 5; $i++) {
	$day = strftime('%Y-%m-%d', strtotime("-$i day"));
	echo <<<EOL
	<NetworkLink>
		<name>$day</name>
		<visibility>0</visibility>
		<Link>
			<href>http://{$ge_server}{$ge_root}flights.php?for=day&amp;day=$day{$test}</href>
			<refreshMode>onExpire</refreshMode>
		</Link>
	</NetworkLink>
EOL;
}

echo <<<EOL
</Document>
</kml>
EOL;
