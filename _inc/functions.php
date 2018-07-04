<?php

date_default_timezone_set("Australia/Sydney");

function mins2hours($time) {
	if ($time < 1) { return; }
  $hours = floor($time / 60);
  $minutes = ($time % 60);
  return array('total'=>$time,'hours'=>$hours,'mins'=>$minutes);
}

function plural($count,$word){
	if($count==1){
		$word = $count." ".$word;
	} else {
		$word = $count." ".$word."s";
	}
	return $word;
}

function ordinal($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number%100) <= 13))
        return $number. 'th';
    else
        return $number. $ends[$number % 10];
}

function timestamptomins($time){
	$mins = 0;
	$mins = explode(':',$time);
	$mins = (($mins[0]*60) + $mins[1]);
	return $mins;
}

function visualizeDatSleep($input,$cut=NULL){
	$csv = $input;
	$dates = array();
	foreach($csv as $rowNumber => $line):
		$lineitem = str_getcsv($line);
		if($rowNumber!=0 && count($lineitem)>=3 && strpos($lineitem[1],'/')){
			$string = explode(', ',$lineitem[1]);
			$date['date']=$string[0];
			$date['time']=$string[1].":00";
			$string = explode('/',$date['date']);
			$string = array_reverse($string);
			if($string[1]<10){ $string[1] = "0".$string[1]; }
			if($string[2]<10){ $string[2] = "0".$string[2]; }
			$dateFormatted = "20".$string[0]."-".$string[1]."-".$string[2];
			$type = "nap";
			$info = NULL;
	
			if(array_key_exists(3,$lineitem)){
				$info = $lineitem[3];
			}
	
			if(!array_key_exists($dateFormatted,$dates)){
				$dates[$dateFormatted]=array();
			}
	
			if(is_numeric($lineitem[2])){
				$endTime = strtotime("+".$lineitem[2]." minutes", strtotime($date['time']));
				$endTime = date('H:i:s', $endTime);
				$tomorrow = date('Y-m-d', strtotime("+1 day", strtotime($dateFormatted)));
				$details = NULL;
				$duration = $lineitem[2];

				if(date('H',strtotime($endTime)) < date('H',strtotime($date['time']))) {
					$dates[$tomorrow] = array();
			
					// Remainder
					$data=array();
					$data['start_unix']=date('U',strtotime($tomorrow." 00:00:00"));
					$data['end_unix']=date('U',strtotime($tomorrow." ".$endTime));
			
					array_push($dates[$tomorrow],array(
						'start_time'=>'00:00:00',
						'end_time'=>$endTime,
						'duration'=>round(($data['end_unix'] - $data['start_unix']) / 60),
						'type'=>'night',
						'total_night_duration'=>$duration,
						'bedtime'=>$date['time']
					));
			
					// Before midnight
					$endTime = "23:59:59";
					$data=array();
					$data['start_unix']=date('U',strtotime($dateFormatted." ".$date['time']));
					$data['end_unix']=date('U',strtotime($dateFormatted." ".$endTime));
					$duration = round(($data['end_unix'] - $data['start_unix']) / 60);
					$type = "night";
				}
							
				// Normal
				array_push($dates[$dateFormatted],array(
					'start_time'=>$date['time'],
					'end_time'=>$endTime,
					'duration'=>$duration,
					'type'=>$type,
					'info'=>$info
				));					
			}
		}
	endforeach;	

	// Adding totals
	foreach($dates as $date => $data):
		$mins = 0;
		$naps = 0;
		$naptime = 0;
		$bedtime=NULL;
		foreach($data as $chunk => $detail):
			$mins = $mins + $detail['duration'];
			if($detail['type']=="nap"){ 
				$naps = $naps+1;
				$naptime = $naptime + $detail['duration'];
			}
			$bedtime = $detail['start_time'];
		endforeach;

		$dates[$date]['total']=mins2hours($mins);
		$dates[$date]['total']['naps']=$naps;
		$dates[$date]['total']['naptime']=$naptime;
		$dates[$date]['total']['bedtime']=$bedtime;

		$dates[$date]['total']['night_sleep'] = 0;
		if(array_key_exists('total_night_duration',$data[0])){
			$dates[$date]['total']['night_sleep']=mins2hours($data[0]['total_night_duration']);
		}

	endforeach;

	// Removing incomplete days
	foreach($dates as $date => $data):
		if(count($data)<=2){
			unset($dates[$date]);
		}
	endforeach;

	$html = NULL;

	// Minute numbers on start and end times
	foreach($dates as $date => $data):
		$nights = 0;
		foreach($data as $key => $nap):
			if(array_key_exists('type',$nap) && $nap['type']=="night"){ $nights++; }
			if(array_key_exists('start_time',$nap)){
				$minuteStart = 0;
				$minuteEnd = 0;
				$minuteStart = explode(':',$nap['start_time']);
				$minuteStart = (($minuteStart[0]*60) + $minuteStart[1]);
				$minuteEnd = $minuteStart + $nap['duration'];
				$dates[$date][$key]['start_time_min']=$minuteStart;
				$dates[$date][$key]['start_time_end']=$minuteEnd;
			}
		endforeach;
		
		// Remove the entire day if it's not bookended by night
		if($nights<2){ 
			unset($dates[$date]);
		}
		
	endforeach;

	# Formatting
	$dates = array_reverse($dates);
	if($cut){ $dates = array_slice($dates, 0, $cut); }

	# Averages
	$average = array(
		'night_sleep' => array(),
		'nap_time' => array(),
		'wake_time' => array(),
		'bed_time' => array(),
		'total_naps' => 0
	);
	foreach($dates as $day):
		array_push($average['night_sleep'],$day['total']['night_sleep']['total']);
		array_push($average['bed_time'],timestamptomins($day['total']['bedtime']));
		array_push($average['wake_time'],timestamptomins($day[0]['end_time']));
		foreach($day as $key => $nap):
			if(array_key_exists('type',$nap) && $nap['type']=="nap"){
				$average['total_naps']++;
				array_push($average['nap_time'],$nap['duration']);
			}
		endforeach;
	endforeach;
	
	$average['night_sleep'] = array_sum($average['night_sleep'])/count($average['night_sleep']);
	$average['nap_time'] = array_sum($average['nap_time'])/count($average['nap_time']);
	$average['bed_time'] = round(array_sum($average['bed_time'])/count($average['bed_time']));
	$average['wake_time'] = round(array_sum($average['wake_time'])/count($average['wake_time']));
	$average['total_naps'] = round($average['total_naps'] / count($dates));
	$average['nap_durs'] = array();
	$average['nap_start_time'] = array();
	
	$i=1; 
	while($i <= $average['total_naps']) {
		$average['nap_durs'][$i] = array();
	  $average['nap_start_time'][$i] = array();
		$i++;
	}
	
	foreach($dates as $day):
		$napNo=1;
		foreach($day as $key => $nap):
			if(array_key_exists('type',$nap) && $nap['type']=="nap"){
				if($napNo<=$average['total_naps']){
					array_push($average['nap_durs'][$napNo],$nap['duration']);
					array_push($average['nap_start_time'][$napNo],timestamptomins($nap['start_time']));
				}
				$napNo++;
			}
		endforeach;
	endforeach;
	foreach($average['nap_durs'] as $napNo => $durs):
		$average['nap_durs'][$napNo] = round(array_sum($durs)/count($durs));
	endforeach;
	foreach($average['nap_start_time'] as $napNo => $durs):
		$average['nap_start_time'][$napNo] = round(array_sum($durs)/count($durs));
	endforeach;


	// Adding blank chunks between naps
	foreach($dates as $date => $data):
		foreach($data as $key => $chunk):
			if($key != "total"){
				$dates[$date][$chunk['start_time_min']]=$chunk;
				unset($dates[$date][$key]);
			}
		endforeach;
		ksort($dates[$date]);
	endforeach;
	foreach($dates as $date => $data):
		$dates[$date][$data[0]['start_time_end']]=array(
			'start_time'=>$data[0]['end_time'],
	    'end_time'=>NULL,
			'duration'=>NULL,
			'type'=>'blank',
			'info'=>'',
			'start_time_min'=>timestamptomins($data[0]['end_time']),
			'start_time_end'=>NULL
		);
	endforeach;
	foreach($dates as $date => $data):
		foreach($data as $key => $chunk):
			if($key != "total" && $chunk['type']!="blank" && $chunk['end_time']!="23:59:59"){
				$dates[$date][$chunk['start_time_end']]=array(
					'start_time'=>$chunk['end_time'],
			    'end_time'=>NULL,
					'duration'=>NULL,
					'type'=>'blank',
					'info'=>'',
					'start_time_min'=>timestamptomins($chunk['end_time']),
					'start_time_end'=>NULL
				);
			}
		endforeach;
		ksort($dates[$date]);
	endforeach;
	
	$start_time=0;
	foreach($dates as $date => $data):
		$data = array_reverse($dates[$date],true);
		foreach($data as $key => $chunk):
			if(array_key_exists('type',$chunk) && $chunk['type']=="blank"){
				$dates[$date][$key]['end_time']=$start_time;
				$dates[$date][$key]['start_time_end']=timestamptomins($start_time);
				$dates[$date][$key]['duration']=($dates[$date][$key]['start_time_end'] - $dates[$date][$key]['start_time_min']);
			}
			if(array_key_exists('start_time',$chunk)){
				$start_time = $chunk['start_time'];
			}
		endforeach;
	endforeach;

	// Finished adding blank chunks

	//return("<pre>".print_r($dates,true));

	$htmlDay = '
		<section class="day">
			<h1>%s</h1>
			<ol class="averages">
				<li class="avg day" style="left:%s;"><a href="javascript://" class="explode"><div>Average wake-up<br /><strong>%s</strong></div></a></li>
				<li class="avg night" style="left:%s;"><a href="javascript://" class="explode"><div>Average bedtime<br /><strong>%s</strong></div></a></li>
			</ol>
			<ol class="timeline">
			%s</ol>
			<ol class="key">
				<li class="midnight"><span class="key bullet">•</span><span class="animate">12am</span></li>
				<li class="one"><span class="key bullet">•</span><span class="animate">1am</span></li>
				<li class="two"><span class="key bullet">•</span><span class="animate">2am</span></li>
				<li class="three"><span class="key bullet">•</span><span class="animate">3am</span></li>
				<li class="four"><span class="key bullet">•</span><span class="animate">4am</span></li>
				<li class="five"><span class="key bullet">•</span><span class="animate">5am</span></li>
				<li class="six"><span class="key bullet">•</span><span class="animate">6am</span></li>
				<li class="seven"><span class="key bullet">•</span><span class="animate">7am</span></li>
				<li class="eight"><span class="key bullet">•</span><span class="animate">8am</span></li>
				<li class="nine"><span class="key bullet">•</span><span class="animate">9am</span></li>
				<li class="ten"><span class="key bullet">•</span><span class="animate">10am</span></li>
				<li class="eleven"><span class="key bullet">•</span><span class="animate">11am</span></li>
				<li class="midday"><span class="key bullet">•</span><span class="animate">12pm</span></li>
				<li class="onePM"><span class="key bullet">•</span><span class="animate">1pm</span></li>
				<li class="twoPM"><span class="key bullet">•</span><span class="animate">2pm</span></li>
				<li class="threePM"><span class="key bullet">•</span><span class="animate">3pm</span></li>
				<li class="fourPM"><span class="key bullet">•</span><span class="animate">4pm</span></li>
				<li class="fivePM"><span class="key bullet">•</span><span class="animate">5pm</span></li>
				<li class="sixPM"><span class="key bullet">•</span><span class="animate">6pm</span></li>
				<li class="sevenPM"><span class="key bullet">•</span><span class="animate">7pm</span></li>
				<li class="eightPM"><span class="key bullet">•</span><span class="animate">8pm</span></li>
				<li class="ninePM"><span class="key bullet">•</span><span class="animate">9pm</span></li>
				<li class="tenPM"><span class="key bullet">•</span><span class="animate">10pm</span></li>
				<li class="elevenPM"><span class="key bullet">•</span><span class="animate">11pm</span></li>
				<li class="twelvePM"><span class="key bullet">•</span><span class="animate">12am</span></li>
			</ol>
			<!-- %s -->
		</section>
	';	

	$htmlNap = "	<li class=\"%s\" style=\"left:%s%%;width:%s%%;background-position:%s%% center;\">
					<div class=\"tip\">
						<div class=\"tipInner\">
							<div class=\"inside\">%s</div>
						</div>
					</div>
				</li>
			";

			$words=NULL;
			$words.="On average the ";
			foreach($average['nap_durs'] as $napNo => $duration):
				if($napNo == count($average['nap_durs'])){
					$words.=" and the ";
				}
				if($napNo != count($average['nap_durs']) && $napNo != 1){
					$words.=", the ";
				}
				$words.= ordinal($napNo)." nap begins at <strong>";
				$time=mins2hours($average['nap_start_time'][$napNo]);
				if($time['hours']<12){
					// am
					$words.=$time['hours'].":";
					if($time['mins']<10){ $words.="0".$time['mins']; } else { $words.=$time['mins']; }
					$words.="am";
				} else {
					// pm
					$words.= ($time['hours'] - 12).":";
					if($time['mins']<10){ $words.="0".$time['mins']; } else { $words.=$time['mins']; }
					$words.= "pm";
				}
				$words.="</strong>";
			endforeach;
	
	$html.="
		<section class=\"day summary\">
			<p>".$words."</p>
		</section>
";

	foreach($dates as $date => $naps):
		$items = NULL;
		$total = $naps['total'];
		unset($naps['total']);

		$napMins = 0;
		$totalNaps = 0;
		
		foreach($naps as $key => $nap):
			$startTime = 0;
			$duration = 0;
			$startTime = round($nap['start_time_min']/1440 * 100,1);
			$duration = round($nap['duration']/1440 * 100,1);
	
			$durationWords = $nap['duration'];
			if($durationWords > 59){
				$durationWords = mins2hours($durationWords);
				$output=NULL;
				$output.= $durationWords['hours']."hr ";
				if($durationWords['mins']>=1){
					$output.=$durationWords['mins']."mins";
				}
				$durationWords = $output;
			} else {
				$durationWords = $durationWords." minutes";
			}
	
			$tip = NULL;
			$info = NULL;
			$emoji = NULL;
			$signal = NULL;
	
			if(array_key_exists('info',$nap) && trim($nap['info'])){ $info = "<p>".trim($nap['info'])."</p>"; }
	
			if($nap['type']=="nap"){
				$totalNaps=$totalNaps+1;
			}
			
			if(array_key_exists($totalNaps,$average['nap_durs']) && $nap['duration'] >= $average['nap_durs'][$totalNaps]) {
				$class = "good";
			} else {
				$class = "bad";
			}
			
			$signal = "<span class=\"indicator ".$class."\"";
			if(array_key_exists($totalNaps,$average['nap_durs'])){
				$signal.=" title=\"Average length for ".ordinal($totalNaps)." nap is ".$average['nap_durs'][$totalNaps]."mins\"";
			}
			$signal.="></span>";
			$averageWords = NULL;
			
			if(array_key_exists($totalNaps,$average['nap_durs'])){
				$diff = ($nap['duration'] - $average['nap_durs'][$totalNaps]);
				if($diff < 0){
					$averageWords .= " <span class=\"bad\">-".str_replace('-','',$diff)."mins";
				} elseif($diff > 0){
					$averageWords .= " <span class=\"good\">+".$diff."mins";
				} elseif($diff == 0){
					$averageWords .= " <span class=\"good\">Perfect!";
				}
				$averageWords .= "</span>";
			}
			
			
			// Normal
			$tip="<h3>".$signal.date("g:ia", strtotime($nap['start_time']))." &ndash; ".date("g:ia", strtotime($nap['end_time']))."</h3><time>".$durationWords.$averageWords."</time>".$info;
				
			if($nap['type']=="night"){
		
				$signal = NULL;
				if($total['night_sleep']['total'] > $average['night_sleep']) {
					$signal = "<span class=\"indicator good\" title=\"Above average night sleep\"></span>";
				} elseif($total['night_sleep']['total'] == $average['night_sleep']) {
					$signal = "<span class=\"indicator good\" title=\"Perfect night sleep\"></span>";
				} else {
					$signal = "<span class=\"indicator bad\" title=\"Below average night sleep\"></span>";
				}

				if($key==0){
					// First chunk of day
					$tip = $signal."<h3>".date('g:ia',strtotime($date." ".$nap['bedtime']))." &ndash; ".date('g:ia',strtotime($date." ".$naps[0]['end_time']))."</h3>";
					$tip.="<time>".$total['night_sleep']['hours']."hr ".$total['night_sleep']['mins']."mins</time>".$info;
				} elseif($key==(count($naps) - 1)){
					// Last chunk of day
					$tip="<h3>".date("g:ia", strtotime($nap['start_time']))."</h3><time>Bedtime</time>".$info;
				}
			}
	
			if($nap['type']!="blank"){
				$items.= sprintf(
					$htmlNap,
					$nap['type'],
					$startTime,
					$duration,
					$startTime,
					$tip
				);
			} else {
				$items.= sprintf(
					$htmlNap,
					$nap['type'],
					$startTime,
					$duration,
					$startTime,
					"<h3>Awake</h3><time>".$durationWords."</time></span>"
				);
			}
		endforeach;

		$total['naptime'] = mins2hours($total['naptime']);
		$summary = NULL;

		if(is_array($total['night_sleep'])){
			$summary.= "<strong>Night:</strong> ".$total['night_sleep']['hours']."hr ".$total['night_sleep']['mins']."mins<br />";
		}

		$summary.= "<strong>Wake:</strong> ".date('g:ia',strtotime($date." ".$naps[0]['end_time']))."<br />";
		$summary.= "<strong>Naps:</strong> ".$total['naps']." totalling ".$total['naptime']['hours']."hr ".$total['naptime']['mins']."min<br />";
		$summary.= "<strong>Bed:</strong> ".date('g:ia',strtotime($date." ".$total['bedtime']))."<br />";
		$summary.= "<strong>Total/24hr:</strong> ".$total['hours']." hours ".$total['mins']." mins";
		
		$aveWT = mins2hours($average['wake_time']);
		$aveBT = mins2hours($average['bed_time']);
		$output = NULL;
		
		if(($average['wake_time']) < (1440/2)){
			$output = $aveWT['hours'].":";
			if($aveWT['mins']<10){ $output.="0".$aveWT['mins']; } else { $output.=$aveWT['mins']; }
			$output.= "am";
		} else {
			$output = ($aveWT['hours'] - 12).":";
			if($aveWT['mins']<10){ $output.="0".$aveWT['mins']; } else { $output.=$aveWT['mins']; }
			$output.= "pm";
		}
		$aveWT = $output;
		
		if(($average['bed_time']) < (1440/2)){
			$output = $aveBT['hours'].":";
			if($aveBT['mins']<10){ $output.="0".$aveBT['mins']; } else { $output.=$aveBT['mins']; }
			$output.= "am";
		} else {
			$output = ($aveBT['hours'] - 12).":";
			if($aveBT['mins']<10){ $output.="0".$aveBT['mins']; } else { $output.=$aveBT['mins']; }
			$output.= "pm";
		}
		$aveBT = $output;
		
		$html.= sprintf(
			$htmlDay,
			date('l jS F \'y', strtotime($date)),
			(($average['wake_time']/1440) * 100)."%",
			$aveWT,
			(($average['bed_time']/1440) * 100)."%",
			$aveBT,
			$items,
			$summary
		);	
	endforeach;

	return $html;
}
	
?>