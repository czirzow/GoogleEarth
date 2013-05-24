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
define('PATH_INCLUDE',  realpath(dirname(dirname(__FILE__))) .'/');
define('PATH_GE', realpath((dirname(__FILE__))) . '/');
define('PATH_GE_KML', PATH_GE . '/kml/');

switch($_REQUEST['for']) {
	default:
		$_REQUEST['for'] = 'current';
		// pass through
	case 'current':
	case 'day':
	case '5days':
		$flights_for = $_REQUEST['for'];
		break;
}

require(PATH_INCLUDE . '/config.php');
require(PATH_INCLUDE . '/mysqldb.php');
require(PATH_INCLUDE . '/rank.php');

try {
	$dsn = "mysql:dbname={$config['xacars']['dbname']};host={$config['xacars']['dbserver']}";
	$dbh = new PDO($dsn, $config['xacars']['dbuser'], $config['xacars']['dbpass']);
} 
catch (PDOException $e) {
	echo 'Connection failed: ' . $e->getMessage();
}

Flights::setDB($dbh);

require(PATH_GE . '/config.php');
Flights::setConfig($xml_config);

$include_file = 'flights-' . $flights_for . '.php';
include(PATH_GE . $include_file);


class Flights {

	private static $dbh;
	private static $xml_config;
	public static $flights;

	public static function setDB($db) {
		self::$dbh = $db;
	}

	public static function setConfig($xml_config) {
		self::$xml_config = $xml_config;

	}

	private static function setupFlightsWithSql($sql) {
		$sth_flights = self::$dbh->prepare($sql);
		if(! $sth_flights) {
			echo "\nPDO::errorInfo():\n";
			print_r(self::$dbh->errorInfo());
			exit;
		}
		//$rc = $sth_flights->execute(array('callsign' => 'CHR337'));
		$rc = $sth_flights->execute();
		if(! $rc) {
			echo "\nPDO::errorInfo():\n";
			print_r($sth_flights->errorInfo());
			exit;
		}

		self::$flights = $sth_flights->fetchAll(PDO::FETCH_CLASS);
	}

	public static function forCurrent() {
		$sql = "
			SELECT
		UNIX_TIMESTAMP() - ap.systemTime as seconds
		,p.name pilot_name, p.starting_hours pilot_hours, p.callsign pilot_callsign
		,af.*
		,h.code pilot_hub, h.name as hub_name
			FROM acars_position ap 
			 JOIN acars_flight af ON (af.curPositionID = ap.acarsReportID) 
			JOIN pilot p ON (p.external_id = af.userID)
			JOIN hubs h ON (p.hub_id = h.id)
		WHERE  ap.systemTime > (UNIX_TIMESTAMP() - 500) 
		order by af.acarsFlightID DESC
		";

		self::setupFlightsWithSql($sql);

	}


	public static function getUpdated($day) {
		$sql = "
			SELECT max(ap.systemTime) as lastUpdated
			FROM acars_position ap 
			 JOIN acars_flight af ON (af.curPositionID = ap.acarsReportID) 
			JOIN pilot p ON (p.external_id = af.userID)
			JOIN hubs h ON (p.hub_id = h.id)
		WHERE  ap.systemTime >= UNIX_TIMESTAMP('$day 00:00:00') AND ap.systemTime <= UNIX_TIMESTAMP('$day 23:59:59') 
			AND ap.msgtype = 'QD'
		";
		$sth = self::$dbh->prepare($sql);
		if(! $sth) {
			echo "\nPDO::errorInfo():\n";
			print_r(self::$dbh->errorInfo());
			exit;
		}
		//$rc = $sth_flights->execute(array('callsign' => 'CHR337'));
		$rc = $sth->execute();
		if(! $rc) {
			echo "\nPDO::errorInfo():\n";
			print_r($sth->errorInfo());
			exit;
		}

		return  $sth->fetchColumn();

	}

	public static function forDay($day) {
		$sql = "
			SELECT
		UNIX_TIMESTAMP() - ap.systemTime as seconds
		,p.name pilot_name, p.starting_hours pilot_hours, p.callsign pilot_callsign
		,af.*
		,h.code pilot_hub, h.name as hub_name
			FROM acars_position ap 
			 JOIN acars_flight af ON (af.curPositionID = ap.acarsReportID) 
			JOIN pilot p ON (p.external_id = af.userID)
			JOIN hubs h ON (p.hub_id = h.id)
		WHERE  ap.systemTime >= UNIX_TIMESTAMP('$day 00:00:00') AND ap.systemTime <= UNIX_TIMESTAMP('$day 23:59:59') 
			AND ap.msgtype = 'QD'
			/* AND p.external_id = 71 */
		order by p.callsign
		";

		self::setupFlightsWithSql($sql);

	}

	public static function fly() {
		$sql_position = "select distinct acarsReportID, msgtype, systemTime, flightStatus, latitude,longitude,heading,altitude,VS,GS,IAS,TAS,FOB,WND,OAT,distFromDep,altitude*0.3048 as meters from acars_position where  acarsFlightID = :acarsFlightID order by acarsReportID asc";
		$sth_position = self::$dbh->prepare($sql_position);

		foreach(self::$flights as $index => $flight) {
			//show_data(array($flight));

			$sth_position->execute(array('acarsFlightID' => $flight->acarsFlightID));
			$positions = $sth_position->fetchAll(PDO::FETCH_CLASS);
			//show_data($positions);

			$progress = new stdClass();
			$progress->vectors = array();
			$progress->flight_state = 'Getting in aircraft';
			$progress->flight_vertical = '';
			$progress->time = new stdClass();
			$progress->time->start = null;
			$progress->time->current = time();
			$progress->state = array();
			$progress->coords = array();

			foreach($positions as $position) {
				/*
				 * Keep track of unique positions
				 */
				if((float)$position->longitude) {
					$coords = "{$position->longitude},{$position->latitude},{$position->meters}";
					if($coords != $last_coords) {
						$progress->coords[] = $coords;
					}
					$last_coords = $coords;

					$vector_heading  = $position->heading;
					$vector_time     = $position->systemTime;
					$vector_vertical = $flight_vertical;

					$heading_changed = abs($vector_heading - $last_vector_heading) > 10;
					$vertical_changed = $vector_vertical != $last_vector_vertical;
					$vector_key = "$heading_changed-$vertical_changed";

					$time_changed =  abs($vector_time - $last_vector_time) >= 300;
					//echo $vector_key . '-' .abs($vector_time - $last_vector_time) . ' ' . $time_changed."\n";

				
					if($vector_key != $last_vector_key && $time_changed) {
						//echo "hdg[$vector_heading] alt[{$position->altitude}]\n";
						$v = new StdClass();
						$v->id = $vector_key;
						$v->heading = $position->heading;
						$v->meters = $position->meters;
						$v->altitude = $position->altitude;
						$v->VS = $position->VS;
						$v->GS = $position->GS;
						$v->IAS = $position->IAS;
						$v->longitude = $position->longitude;
						$v->latitude = $position->latitude;
						$progress->vectors[] = $v;
						$last_vector_time = $vector_time;
					}
					$last_vector_key = $vector_key;
					//$last_vector_time += $vector_time;
					$last_vector_heading = $vector_heading;
					$last_vector_vertical = $vector_vertical;

				}

				/*
				 * keep track of start time
				 */
				if(! $progress->time->start) {
					$progress->time->start = $position->systemTime;
				}
				$last_time = $position->systemTime;
				

				switch($position->msgtype) {
					case 'PR':
						if($position->flightStatus == 1) {
							$flight_state = 'Boarding';
						}
						break;
					case 'QA':
						$flight_state = 'Taxi';
						break;
					case 'QB':
						$flight_state = 'In-Flight';
						break;
					case 'QC':
						$flight_state = 'Landed';
						$flight_vertical = ''; // level flight.. heh
						break;
					case 'QD':
						if($position->flightStatus == 99) {
							$progress->crashed = 'Crashed';
						}
						$flight_state = 'End';
						break;
					case 'ZZ':
						$flight_state = 'Flight Ended';
						break;
					case 'AR':
						// if the user landed and has a positive vertical speed
						if($progress->flight_states['QC'] && $position->VS == 1) {
							// they took off again.
							$flight_state = 'Airborn';
						}
						switch($position->VS) {
							case 0:
								$flight_vertical = '';
								break;
							case 1:
								$flight_vertical = '+';
								break;
							case -1:
								$flight_vertical = '-';
								break;
						}

						break;
				}
				if($position->msgtype != 'ZZ') {
					$progress->current = $position;
				}
				$progress->state[$position->msgtype] = $position;
				$progress->flight_state = $flight_state;
				$progress->flight_vertical = $flight_vertical;

			}
			$progress->time->current = $last_time;
			self::$flights[$index]->progress = $progress;
			if($_REQUEST['debug']) {
				echo "<pre>"; print_r($flights[$index]); echo "</pre>";
			}
		}

	}

	public static function kml() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<kml xmlns="http://earth.google.com/kml/2.1">';
		$xml .= '<Document>';
		$xml .= <<<EOL

	<Style id="currentPoint">
			<IconStyle>
				<color>ff00a5ff</color>
				<Icon>
					<href>root://icons/palette-2.png</href>
					<y>15</y>
					<w>32</w>
					<h>32</h>
				</Icon>
			</IconStyle>
	</Style>
	<Style id="enroutePoint">
			<IconStyle>
				<color>9900a5ff</color>
				<Icon>
					<href>root://icons/palette-2.pn</href>
					<y>15</y>
					<w>32</w>
					<h>32</h>
				</Icon>
			</IconStyle>
	</Style>

EOL;


		$current_callsign = null;
		foreach(self::$flights as $flight) {
			if($flight->pilot_callsign != $current_callsign) {
				if($current_callsign) {
				$xml .= '</Folder>';
				}
				$xml .= "<Folder><name>{$flight->pilot_callsign} - {$flight->pilot_name}</name>";
			}
			$current_callsign = $flight->pilot_callsign;

			$rank_name = getRank($flight->pilot_hours);
			$rank_url = getRankUrl($flight->pilot_hours);
			$fp_clean = str_replace('~', ' ', $flight->flightPlan);
			$altitude = number_format($flight->progress->current->altitude, 0, '.', ',');

			list($seconds, $minutes, $hours) = timeinterval($flight->progress->time->current, $flight->progress->time->start);
			$time_flying = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);


			$color = self::$xml_config['colors'][$flight->pilot_hub];
			$days_ago = (int) $flight->seconds/86400;
			$line = 1;
			if(isset(self::$xml_config['lines'][$days_ago]) ) {
				$line = self::$xml_config['lines'][$days_ago];
			}

			$trans = 'dd';
			if($flight->seconds < 500) {
				$trans = 'ff';
				$line = 4;
			}

			$flight_coords = join(' ', $flight->progress->coords);
			$xml .= <<<EOL
		<Placemark>
			<name>{$flight->pilot_name} - {$flight->callsign}</name>
			<description><![CDATA[
		<table cellspacing="0" cellpadding="0" border="0" width="150">
			<tr>
				<td>{$flight->departure} - {$flight->destination} ({$flight->acType})</td>
			</tr>
			<tr>
				<td>{$flight->progress->crashed} {$flight->progress->flight_vertical}{$flight->progress->flight_state}</td>
			</tr>
		</table>
			]]></description>
			<Style>
				<BalloonStyle>
					<bgColor>dddddddd</bgColor>
					<textColor>ffdddddd</textColor>
					<text><![CDATA[
		<style>
		html, body  {margin: 0;padding: 0;border: 0;outline: 0;font-size: 100%;background: transparent; color: #DDDDDD;}
		#maintable { background-color: #7f7f7f; color: #DDDDDD; padding: 10px; }
		th { text-align: left; }
		.right { text-align: right; }
		.pilot_name { font-size: 1.5em; padding-bottom:  10px;}
		caption { color: #71b6d3; font-weight: bold; text-align: left; }
		a { color: #71b6d3; text-decoration: none; }
		a:hover { color: #ffffff;}
		</style>
		<table id="maintable" width="600" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td align="left" valign="top">
					<div style="float: right;">
						$[description]
					</div>
					<div class="">
						<img src="http://www.bluehorizonintl.com/{$rank_url}" align="left" />
						<span class="pilot_name">{$flight->pilot_name}</span><br />
						{$rank_name}
					</div>
					<br />
					<table width="90%" cellspacing="0" cellpadding="2">
						<caption>Flight Info:</caption>
						<tr>
							<th class="right">Flight&nbsp;Time</th>
							<th class="right">Altitude</th>
							<th class="right">Heading</th>
							<th class="right">TAS</th>
							<th class="right">IAS</th>
							<th class="right">Wind</th>
							<th class="right">OAT</th>
							<th class="right">FOB</th>
						</tr>
						<tr>
							<td class="right">{$time_flying}</td>
							<td class="right">{$altitude}</td>
							<td class="right">{$flight->progress->current->heading}</td>
							<td class="right">{$flight->progress->current->TAS}</td>
							<td class="right">{$flight->progress->current->IAS}</td>
							<td class="right">{$flight->progress->current->WND}</td>
							<td class="right">{$flight->progress->current->OAT}</td>
							<td class="right">{$flight->progress->current->FOB}</td>
						</tr>
					</table>
				</td>
				<td valign="top"  width="90" align="center">
					<img src="http://acars.bluehorizonintl.com/img/hub_logo_small.png"  valign="top" border="0" /><br />
					<br />
					{$flight->pilot_callsign}<br />
					{$flight->pilot_hub}<br />
					{$flight->hub_name}
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<table width="100%" cellspacing="0" cellpadding="2">
						<caption>Flight Plan:</caption>
						<tr>
							<td valign="left">{$fp_clean}</td>
						</tr>
					</table>
					<br />
					<br />
					<br />
				</td>
			</tr>
		</table>
			]]></text>
				</BalloonStyle>
				<IconStyle>
					<heading>{$flight->progress->current->heading}</heading>
				</IconStyle>
			</Style>
			<styleUrl>#currentPoint</styleUrl>
			<Point>
				<altitudeMode>absolute</altitudeMode>
				<coordinates>{$flight->progress->current->longitude},{$flight->progress->current->latitude},{$flight->progress->current->meters}</coordinates>
			</Point>
		</Placemark>
		<Placemark>
			<name>flight path</name>
			<Style>
				<LineStyle>
					<color>{$trans}{$color}</color>
					<width>{$line}</width>
				</LineStyle>
				<PolyStyle>
					<color>44{$color}</color>
				</PolyStyle>
			</Style>
				<LineString>
					<extrude>1</extrude>
					<altitudeMode>absolute</altitudeMode>
					<tessellate>1</tessellate>
					<coordinates>{$flight_coords}</coordinates>
				</LineString>
EOL;

		$xml .= <<<EOL
		</Placemark>
EOL;

			$xml .= <<<EOL
		<Folder>
			<name>    hdg-alt-ias</name>
			<description>--------------</description>
			<open>0</open>
			<visibility>0</visibility>
EOL;
			foreach($flight->progress->vectors as $vector) {
				$xml .= <<<EOL
		<Placemark>
			<visibility>0</visibility>
			<name>{$vector->heading}-{$vector->altitude}-{$vector->IAS}</name>
			<altitudeMode>absolute</altitudeMode>
			<Style>
				<IconStyle>
					<heading>{$vector->heading}</heading>
				</IconStyle>
			</Style>
			<styleUrl>#enroutePoint</styleUrl>
			<Point>
				<coordinates>{$vector->longitude},{$vector->latitude},{$vector->meters}</coordinates>
			</Point>
		</Placemark>
EOL;
			}
			$xml .= "</Folder>";

			//print_r($vector);
			//continue;
		}

		if($current_callsign) {
			$xml .= '</Folder>';
		}
		$xml .= '</Document>';
		$xml .= '</kml>';

		return $xml;

	}

}


function timeinterval($to, $from) {
	$timespan = $to - $from;
	$seconds = $timespan % 60;

	$time = ($timespan  -  $seconds);

	$hours   = (int) ($time/ 3600);
	$minutes = (int) ($time / 60) - ($hours * 60);

	return array($seconds, $minutes, $hours);

}


function show_data($rows, $show_cols=true, $conn=false) {

  echo '<div class="alertbox" style="width: 600px;">';
  echo '<table class="standard" cellpadding="5" cellspacing="0">';

	if($show_cols ) {
		$cols = get_object_vars($rows[0]);
		echo '<tr>';
		foreach($cols as $prop => $col) {
			echo "<th>{$prop}</th>";
		}
		echo '</tr>';
	}

	show_rows($rows);

	echo "</table>";

	echo '<div style="white-space: pre; overflow: auto;">', htmlentities($sql), '</div>';
	echo "</div><br>";
}

function show_rows($rows, $table_started=true) {

	if(! $table_started) {
		echo '<table class="standard" cellpadding="0" cellspacing="0">';
	}
	foreach($rows as $row) {
		echo "<tr>";
		foreach($row as $field => $val) {
			echo "<td style=\"border: 1px solid;\">{$val}</td>";
		}
		echo "</tr>";
	}
	if(! $table_started) {
		echo '</table>';
	}

}


