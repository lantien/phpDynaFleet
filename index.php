<?php

ini_set('max_execution_time', 600);
ini_set('memory_limit', '3000M');
error_reporting(0);

$requestByToken = 19;

$username = "FW100MALHERBE";
$password = 'fwmalherbe100';

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
  $usefulData[2] = new stdClass();

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

    $tempTotalTime = $arrayData[0][$i]->drivingSeconds + $arrayData[0][$i]->idleSeconds
                  + $arrayData[0][$i]->PTOseconds;

    $usefulData[0]->{$infos->vehicleId->id}->totalTime =
                                    secondsToHours($tempTotalTime);

    $tempTotalDistance = $arrayData[0][$i]->unitOnMeters/1000;


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
                                    ($arrayData[0][$i]->fleetOverspeedCentiliters/$arrayData[0][$i]->totalCentiliters);


    $usefulData[0]->{$infos->vehicleId->id}->engineOverRev =
                                    ($arrayData[0][$i]->engineOverspeedSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->engineOverload =
                                    ($arrayData[0][$i]->engineOverloadSeconds/$tempTotalTime)*100;

    $usefulData[0]->{$infos->vehicleId->id}->autoMode
                                  = ($arrayData[0][$i]->lovTransmissionAutoModeSeconds)
                                  /(($arrayData[0][$i]->lovTransmissionManualModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionAutoModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionPowerModeSeconds))*100;

    $usefulData[0]->{$infos->vehicleId->id}->manualMode
                                  = ($arrayData[0][$i]->lovTransmissionManualModeSeconds)
                                  /(($arrayData[0][$i]->lovTransmissionManualModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionAutoModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionPowerModeSeconds))*100;

    $usefulData[0]->{$infos->vehicleId->id}->powerMode
                                  = ($arrayData[0][$i]->lovTransmissionPowerModeSeconds)
                                  /(($arrayData[0][$i]->lovTransmissionManualModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionAutoModeSeconds)
                                  +($arrayData[0][$i]->lovTransmissionPowerModeSeconds))*100;

    $usefulData[0]->{$infos->vehicleId->id}->outOfGreenAreaLitre
                                  = (($arrayData[0][$i]->lovEngineOutOfGreenAreaCentiliters/100)/
                                  ($arrayData[0][$i]->lovEngineOutOfGreenAreaMeters/1000))*100;

    $usefulData[0]->{$infos->vehicleId->id}->engineOverloadLitre
                                  = (($arrayData[0][$i]->engineOverloadCentilitres/100)/
                                  ($arrayData[0][$i]->engineOverloadMeters/1000))*100;
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

      $usefulData[1]->{$infos->driverId->id}->engineOverloadLitre =
                    (($arrayData[1]->{$infos->driverId->id}->engineOverloadCentilitres/100)/
                    ($arrayData[1]->{$infos->driverId->id}->engineOverloadMeters/1000))*100;

      $usefulData[1]->{$infos->driverId->id}->outOfGreenAreaLitre =
                    (($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaCentiliters/100)/
                    ($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaMeters/1000))*100;
      //
      $tempTotalTime = $arrayData[1]->{$infos->driverId->id}->drivingSeconds + $arrayData[1]->{$infos->driverId->id}->idleSeconds
                    + $arrayData[1]->{$infos->driverId->id}->PTOseconds;

      $usefulData[1]->{$infos->driverId->id}->totalTime =
                              secondsToHours($tempTotalTime);

      $tempTotalDistance = $arrayData[1]->{$infos->driverId->id}->drivingMeters/1000;

      $usefulData[1]->{$infos->driverId->id}->brakeCount =
                                      ($arrayData[1]->{$infos->driverId->id}->brakeCount/$tempTotalDistance)*100;

      $usefulData[1]->{$infos->driverId->id}->stopCount =
                                      ($arrayData[1]->{$infos->driverId->id}->stopCount/$tempTotalDistance)*100;

      $usefulData[1]->{$infos->driverId->id}->idle =
                                      ($arrayData[1]->{$infos->driverId->id}->idleSeconds/$tempTotalTime)*100;


      $usefulData[1]->{$infos->driverId->id}->ecoMode =
                                      ($arrayData[1]->{$infos->driverId->id}->economySeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->outOfGreenArea =
                                      ($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->coasting =
                                      ($arrayData[1]->{$infos->driverId->id}->coastingSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->cruise =
                                      ($arrayData[1]->{$infos->driverId->id}->cruiseSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->roadOverspeed =
                                      ($arrayData[1]->{$infos->driverId->id}->fleetOverspeedCentiliters/$arrayData[1]->{$infos->driverId->id}->totalCentiliters);


      $usefulData[1]->{$infos->driverId->id}->engineOverRev =
                                      ($arrayData[1]->{$infos->driverId->id}->engineOverspeedSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->engineOverload =
                                      ($arrayData[1]->{$infos->driverId->id}->engineOverloadSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->driverId->id}->autoMode
                                    = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds)
                                    /(($arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds))*100;

      $usefulData[1]->{$infos->driverId->id}->manualMode
                                    = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds)
                                    /(($arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds))*100;

      $usefulData[1]->{$infos->driverId->id}->powerMode
                                    = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds)
                                    /(($arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds)
                                    +($arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds))*100;
    }



  }

  //make stats driver/trucks at $arrayData[2] + $driverInfos + $vehicleInfos

  foreach ($arrayData[2] as $driverId => $allStats) {

    foreach ($allStats as $vehicleId => $stats) {

      $vin = $vehicleInfos[array_search( $vehicleId, array_column(array_column($vehicleInfos, 'vehicleId'), 'id'))]->vin;
      $tacho = $driverInfos[array_search( $driverId, array_column(array_column($driverInfos, 'driverId'), 'id'))]->digitalTachoCardId;


      $usefulData[2]->{$tacho}->{$vin} = new stdClass();

      $tempTotalTime = $stats->drivingSeconds + $stats->idleSeconds
                    + $stats->PTOseconds;

      $tempTotalDistance = $stats->unitOnMeters/1000;

      $usefulData[2]->{$tacho}->{$vin}->totalTime =
                                      secondsToHours($tempTotalTime);
      //
      $usefulData[2]->{$tacho}->{$vin}->brakeCount =
                                      ($stats->brakeCount/$tempTotalDistance)*100;

      $usefulData[2]->{$tacho}->{$vin}->stopCount =
                                      ($stats->stopCount/$tempTotalDistance)*100;

      $usefulData[2]->{$tacho}->{$vin}->idle =
                                      ($stats->idleSeconds/$tempTotalTime)*100;


      $usefulData[2]->{$tacho}->{$vin}->ecoMode =
                                      ($stats->economySeconds/$tempTotalTime)*100;

      $usefulData[2]->{$tacho}->{$vin}->outOfGreenArea =
                                      ($stats->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

      $$usefulData[2]->{$tacho}->{$vin}->coasting =
                                      ($stats->coastingSeconds/$tempTotalTime)*100;

      $usefulData[2]->{$tacho}->{$vin}->cruise =
                                      ($stats->cruiseSeconds/$tempTotalTime)*100;

      $usefulData[2]->{$tacho}->{$vin}->roadOverspeed =
                                      ($stats->fleetOverspeedCentiliters/$stats->totalCentiliters);


      $usefulData[2]->{$tacho}->{$vin}->engineOverRev =
                                      ($stats->engineOverspeedSeconds/$tempTotalTime)*100;

      $usefulData[2]->{$tacho}->{$vin}->engineOverload =
                                      ($stats->engineOverloadSeconds/$tempTotalTime)*100;

      $usefulData[2]->{$tacho}->{$vin}->autoMode
                                    = ($stats->lovTransmissionAutoModeSeconds)
                                    /(($stats->lovTransmissionManualModeSeconds)
                                    +($stats->lovTransmissionAutoModeSeconds)
                                    +($stats->lovTransmissionPowerModeSeconds))*100;

      $usefulData[2]->{$tacho}->{$vin}->manualMode
                                    = ($stats->lovTransmissionManualModeSeconds)
                                    /(($stats->lovTransmissionManualModeSeconds)
                                    +($stats->lovTransmissionAutoModeSeconds)
                                    +($stats->lovTransmissionPowerModeSeconds))*100;

      $usefulData[2]->{$tacho}->{$vin}->powerMode
                                    = ($stats->lovTransmissionPowerModeSeconds)
                                    /(($stats->lovTransmissionManualModeSeconds)
                                    +($stats->lovTransmissionAutoModeSeconds)
                                    +($stats->lovTransmissionPowerModeSeconds))*100;

      $usefulData[2]->{$tacho}->{$vin}->outOfGreenAreaLitre
                                    = (($stats->lovEngineOutOfGreenAreaCentiliters/100)/
                                    ($stats->lovEngineOutOfGreenAreaMeters/1000))*100;

      $usefulData[2]->{$tacho}->{$vin}->engineOverloadLitre
                                    = (($stats->engineOverloadCentilitres/100)/
                                    ($stats->engineOverloadMeters/1000))*100;
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

            }

            if(isset($tempDriverId) && !property_exists($tempDriverStats, $tempDriverId)) {

              $tempDriverStats->{$tempDriverId} = new stdClass();

            }

            //Si on connais le driver
            if(isset($tempDriverId)) {

              //Si le driver n'est pas connue on l'ajoute
              if(!property_exists($tempDrivertTrucksStats, $tempDriverId)) {

                  $tempDrivertTrucksStats->{$tempDriverId}->{$tempVehicleId} = new stdClass();

              } else if (!property_exists($tempDrivertTrucksStats->$tempDriverId, $tempVehicleId)) { //si on ne connait pas le camion

                $tempDrivertTrucksStats->{$tempDriverId}->{$tempVehicleId} = new stdClass();

              }


            }

            if(is_object($data)) {

              foreach($data as $key=>$value) {

                if($key != 'driverId' && $key != 'startTime' && $key != 'endTime' &&
                is_object($value) && property_exists($value, 'value')) {

                  if(isset($tempDriverId)) {


                    if(!property_exists($tempDriverStats->$tempDriverId, $key)) {

                      $tempDriverStats->$tempDriverId->{$key} = $value->value;
                    } else {

                      $tempDriverStats->$tempDriverId->{$key} += $value->value;
                    }

                    if(!property_exists($tempDrivertTrucksStats->$tempDriverId->$tempVehicleId
                    , $key)) {

                      $tempDrivertTrucksStats->$tempDriverId->$tempVehicleId->{$key} = $value->value;
                    } else {

                      $tempDrivertTrucksStats->$tempDriverId->$tempVehicleId->{$key} += $value->value;
                    }

                  }

                  if(!property_exists($sumStats, $key)) {

                    $sumStats->{$key} = $value->value;
                  } else {

                    $sumStats->{$key} += $value->value;
                  }

                }

              }

            }


            unset($tempDriverId);
          }
        }

      $arrayStats[] = $sumStats;
    }

  $arrayBundleStats[] = $arrayStats;
  $arrayBundleStats[] = $tempDriverStats;
  $arrayBundleStats[] = $tempDrivertTrucksStats;


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

function sumBetweenWeeksDriversTrucks($firstWeekObj, $secondWeekObj) {


  foreach ($secondWeekObj as $driverId => $allStats) {

    foreach ($allStats as $vehicleId => $stats) {

      if(!property_exists($firstWeekObj, $driverId)) {

        $firstReport->{$driverId} = $allStats;

      } else if(!property_exists($firstWeekObj->$driverId, $vehicleId)) {

        $firstReport->$driverId->{$vehicleId} = $stats;
      } else {

        foreach ($stats as $key => $value) {

          $firstWeekObj->{$driverId}->{$vehicleId}->{$key} += $value;
        }
      }
    }

  }

  return $firstWeekObj;
}

$week_array = getStartAndEndDate(45,2018);

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
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[4]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[5]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[6]->vehicleId->id, $iterator);
// array_push($firstReportArray, $temp);
// $temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[7]->vehicleId->id, $iterator);
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
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[4]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[5]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[6]->vehicleId->id, $iterator);
// array_push($secondReportArray, $temp);
//
// $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[7]->vehicleId->id, $iterator);
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
//
//
// echo '<pre>';
// sumBetweenWeeksObj(sumStatsChunk($firstReportArray)[1], sumStatsChunk($secondReportArray)[1]);
// echo '</pre>';
//
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

sleep(30);

$iterator = 0;

foreach($vehicleList as &$value) {

  if($iterator%$requestByToken==0 && $iterator != 0) {

    $token = loginRequest($username, $password, $client, $iterator);
  }

  $temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $value->vehicleId->id, $iterator);
  array_push($secondReportArray, $temp);

  $iterator++;
}
//TRUCK STATS
//var_dump(sumBetweenWeeks(sumStatsChunk($firstReportArray), sumStatsChunk($secondReportArray)));

// echo '<pre>';
// var_dump(sumBetweenWeeks(sumStatsChunk($firstReportArray)[0], sumStatsChunk($secondReportArray)[0]));
// echo '</pre>';

// sumStatsChunk($firstReportArray);

$sumFirstChunk = sumStatsChunk($firstReportArray);
$sumSecondChunk = sumStatsChunk($secondReportArray);

$finalDatas = array();

$finalDatas[] = sumBetweenWeeks($sumFirstChunk[0], $sumSecondChunk[0]);

$finalDatas[] = sumBetweenWeeksObj($sumFirstChunk[1], $sumSecondChunk[1]);

$finalDatas[] = sumBetweenWeeksDriversTrucks($sumFirstChunk[2], $sumSecondChunk[2]);

echo '<pre>';
var_dump(extractUsefulData($client, $finalDatas, $token));
echo '</pre>';


?>
