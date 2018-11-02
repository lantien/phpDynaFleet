<?php

ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M'); // or you could use 1G

$requestByToken = 19;

$username = "FW100MALHERBE";
$password = 'fwmalherbe100';
$from = '2018-10-02T00:00:00';
$to = '2018-10-04T23:59:59';

$client = new SoapClient("https://api2.dynafleetonline.com/wsdl",
                                            array('proxy_host'     => "pxlyon2.srv.volvo.com",
                                            'proxy_port'     => 8080));


function loginRequest($username, $password, $client) {
  $params = array('Api_LoginLoginTO_1' => array('gmtOffset' => array('value' => '0'), 'password' => $password,
                            'username' => $username));

  $res = $client->login($params);
  return $res->result->id;
}

function vehiclesRequest($token, $client) {

  $params = array('Api_SessionId_1' => array('id' => $token));

  $res = $client->getVehiclesV2($params);
  return $res->result->vehicleInfos;

}

function reportVehicleRequest($token, $client, $from, $to, $vehicleId, $iterator) {

  $params = array('Api_ReportGetVehicleReportDataTO_1' => array(
    'from' => array('value' => $from),
    'sessionId' => array('id' => $token),
    'to' => array('value' => $to),
    'vehicleId' => array('id' => $vehicleId)
  ));

  $res = $client->getVehicleReportDataExtended($params);
  return $res->result;
}

$token = loginRequest($username, $password, $client);
$vehicleList = vehiclesRequest($token, $client);

function getStartAndEndDate($week, $year) {
  $dto = new DateTime();
  $dto->setISODate($year, $week);
  $dto->setTime(0, 0, 0);
  $ret['week_start'] = $dto->format('Y-m-d H:i:s');
  $dto->modify('+4 days');
  $ret['week_mid'] = $dto->format('Y-m-d H:i:s');
  $dto->modify('+2 days');
  $dto->setTime(23, 59, 59);
  $ret['week_end'] = $dto->format('Y-m-d H:i:s');

   $ret['week_start'] = str_replace(" ", "T", $ret['week_start']);
   $ret['week_mid'] = str_replace(" ", "T", $ret['week_mid']);
   $ret['week_end'] = str_replace(" ", "T", $ret['week_end']);

  return $ret;
}

function extractUsefulData($client, $arrayData, $token) {

  $params = array('Api_SessionId_1' => array(
    'id' => $token
  ));

  $usefulData = array();

  $usefulData[0] = new stdClass();
  $usefulData[1] = new stdClass();

  //at 0 truck STATS
  $vehicleIds = $client->getVehiclesV2($params);

  $vehicleInfos = $vehicleIds->result->vehicleInfos;


  $i = 0;

  foreach ($vehicleInfos as $vehicle => $infos) {

    $usefulData[0]->{$infos->vehicleId->id}
                                = new stdClass();

    $usefulData[0]->{$infos->vehicleId->id}->vin =
                            $infos->vin;

    $usefulData[0]->{$infos->vehicleId->id}->displayName =
                            $infos->displayName;

    $usefulData[0]->{$infos->vehicleId->id}->totalTime =
                                    secondsToHours($arrayData[0][$i]->unitOnSeconds);

    $tempTotalDistance = $arrayData[0][$i]->unitOnMeters/1000;
    $tempTotalTime = $arrayData[0][$i]->unitOnSeconds;

    $usefulData[0]->{$infos->vehicleId->id}->brakeCount =
                                    ($arrayData[0][$i]->brakeCount/$tempTotalDistance)*100;

    $usefulData[0]->{$infos->vehicleId->id}->stopCount =
                                    ($arrayData[0][$i]->stopCount/$tempTotalDistance)*100;

    $usefulData[0]->{$infos->vehicleId->id}->idle =
                                    ($arrayData[0][$i]->idleSeconds/$tempTotalTime)*100;


    $usefulData[0]->{$infos->vehicleId->id}->ecoMode =
                                    ($arrayData[0][$i]->economySeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->outOfGreenArea =
                                    ($arrayData[0][$i]->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->coasting =
                                    ($arrayData[0][$i]->coastingSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->cruise =
                                    ($arrayData[0][$i]->cruiseSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->roadOverspeed =
                                    ($arrayData[0][$i]->roadOverspeedSeconds)*1;


    $usefulData[0]->{$infos->vehicleId->id}->engineOverRev =
                                    ($arrayData[0][$i]->engineOverRevSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->engineOverload =
                                    ($arrayData[0][$i]->engineOverloadSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->autoMode
                                  = ($arrayData[0][$i]->lovTransmissionAutoModeSeconds);

    $usefulData[0]->{$infos->vehicleId->id}->manualMode
                                  = ($arrayData[0][$i]->lovTransmissionManualModeSeconds);

    $usefulData[0]->{$infos->vehicleId->id}->powerMode
                                  = ($arrayData[0][$i]->lovTransmissionPowerModeSeconds);
    $i++;
  }

  //at 1 driver stats
  $driverIds = $client->getDriversV2($params);

  $driverInfos = $driverIds->result->driverInfos;

  foreach ($driverInfos as $driver => $infos) {

    $usefulData[1]->{$infos->driverId->id}
                                = new stdClass();

    $usefulData[1]->{$infos->driverId->id}->digitalTachoCardId =
                            $infos->digitalTachoCardId;

    $usefulData[1]->{$infos->driverId->id}->displayName =
                            $infos->displayName;

    if(property_exists($arrayData[1], $infos->driverId->id)) {

      $usefulData[1]->{$infos->driverId->id}->engineOverload =
                    (($arrayData[1]->{$infos->driverId->id}->engineOverloadCentilitres/100)/
                    ($arrayData[1]->{$infos->driverId->id}->engineOverloadMeters/1000))*100;

      $usefulData[1]->{$infos->driverId->id}->outOfGreenArea =
                    (($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaCentiliters/100)/
                    ($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaMeters/1000))*100;
    }



  }


  return $usefulData;
}

function secondsToHours($seconds) {
  $t = round($seconds);
  return sprintf('%02d:%02d', ($t/3600),($t/60%60));
}

function sumBetweenWeeks($firstReport, $secondReport) {

  $arrayStats = array();

  for($i = 0; $i < count($firstReport); ++$i) {

    $sumStats = new stdClass();

    foreach ($firstReport[$i] as $key => $value) {
      if(property_exists($firstReport[$i], $key)
          && property_exists($secondReport[$i], $key)) {

        $sumStats->{$key} = $firstReport[$i]->$key + $secondReport[$i]->$key;
      }
    }

    $arrayStats[] = $sumStats;
  }

  return $arrayStats;
}

function sumStatsChunk($rawReport) {

  $arrayBundleStats = array();


  $tempDrivertTrucksStats = new stdClass();
  $tempDriverStats = new stdClass();

  $arrayStats = array();

    foreach($rawReport as $rawValues) {

      $sumStats = new stdClass();

        if(property_exists($rawValues, 'dataEntries')) {

          foreach($rawValues->dataEntries as $iterator=>$data) {

            $tempVehicleId = $rawValues->vehicleId->id;

            if(!is_null($data->driverId)) {

              $tempDriverId = $data->driverId->id;
            }

            if(isset($tempDriverId) && !property_exists($tempDriverStats, $tempDriverId)) {

              $tempDriverStats->{$tempDriverId} = new stdClass();
              $tempDrivertTrucksStats->{$tempDriverId} = new stdClass();
            }

            if(is_object($data)) {

              foreach($data as $key=>$value) {

                if($key != 'driverId' && $key != 'startTime' && $key != 'endTime' &&
                is_object($value) && property_exists($value, 'value')) {

                  if(isset($tempDriverId)) {

                    $tempDrivertTrucksStats->{$tempDriverId}->{$tempVehicleId}
                        = new stdClass();

                    if(!property_exists($tempDriverStats->$tempDriverId, $key)) {

                      $tempDriverStats->$tempDriverId->{$key} = $value->value;
                    } else {

                      $tempDriverStats->$tempDriverId->{$key} += $value->value;
                    }

                  }

                  if(!property_exists($sumStats, $key)) {

                    $sumStats->{$key} = $value->value;
                  } else {

                    $sumStats->{$key} += $value->value;
                  }

                }

              }

              $tempDrivertTrucksStats->{$tempDriverId}->{$tempVehicleId}
                  = $tempDriverStats->{$tempDriverId};
            }

          }
        }

      $arrayStats[] = $sumStats;
    }

    echo '<pre>';
    var_dump($tempDrivertTrucksStats);
    echo '</pre>';

  $arrayBundleStats[] = $arrayStats;
  $arrayBundleStats[] = $tempDriverStats;


  return $arrayBundleStats;

}

function sumBetweenWeeksObj( $firstWeekObj, $secondWeekObj) {

  $weekStatsObj = $firstWeekObj;

  foreach ($secondWeekObj as $key => $obj) {

    foreach ($obj as $data => $value) {

      $weekStatsObj->{$key}->{$data} += $value;
    }
  }

  return $weekStatsObj;
}

$week_array = getStartAndEndDate(43,2018);

$firstReportArray = array();
$secondReportArray = array();



// $iterator = 0;
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[0]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[1]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[2]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[3]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
//
// //////////////SECOND PART OF THE week_end
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[0]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[1]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[2]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[3]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);



// $finalDatas = array();
//
// $finalDatas[] = sumBetweenWeeks(sumStatsChunk($firstReportArray)[0], sumStatsChunk($secondReportArray)[0]);
//
// $finalDatas[] = sumBetweenWeeksObj(sumStatsChunk($firstReportArray)[1], sumStatsChunk($secondReportArray)[1]);
//
// echo '<pre>';
// var_dump(extractUsefulData($client, $finalDatas, $token));
// echo '</pre>';


// echo '<pre>';
// sumBetweenWeeksObj(sumStatsChunk($firstReportArray)[1], sumStatsChunk($secondReportArray)[1]);
// echo '</pre>';

// echo '<pre>';
// var_dump(sumBetweenWeeks(sumStatsChunk($firstReportArray)[0], sumStatsChunk($secondReportArray)[0]));
// echo '</pre>';


$iterator = 0;

foreach($vehicleList as &$value) {

  if($iterator%$requestByToken==0 && $iterator != 0) {

    $token = loginRequest($username, $password, $client, $iterator);
  }

  $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $value->vehicleId->id, $iterator);
  array_push($firstReportArray, $temp);
  $iterator++;
}

sleep(20);

// $iterator = 0;
//
// foreach($vehicleList as &$value) {
//
//   if($iterator%$requestByToken==0 && $iterator != 0) {
//
//     $token = loginRequest($username, $password, $client, $iterator);
//   }
//
//   $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $value->vehicleId->id, $iterator);
//   array_push($secondReportArray, $temp);
//
//   $iterator++;
// }
//TRUCK STATS
//var_dump(sumBetweenWeeks(sumStatsChunk($firstReportArray), sumStatsChunk($secondReportArray)));

sumStatsChunk($firstReportArray);

sumStatsChunk($secondReportArray);


?>
