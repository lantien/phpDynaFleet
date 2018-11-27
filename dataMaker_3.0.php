<?php
ini_set('max_execution_time', 900);
ini_set('memory_limit', '2048M');

function loginRequest($username, $password, $client) {
  $params = array('Api_LoginLoginTO_1' => array('gmtOffset' => array('value' => '1'), 'password' => $password,
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

function getStartAndEndDate($week, $year) {
  $dto = new DateTime();
  $dto->setISODate($year, $week);
  $dto->setTime(0, 0, 0);
  $ret['week_start'] = $dto->format('Y-m-d H:i:s');
  $dto->modify('+3 days');
  $dto->setTime(22, 59, 59);
  $ret['week_mid'] = $dto->format('Y-m-d H:i:s');

  $dto->setTime(23, 00, 00);
  $ret['week_midForEnd'] = $dto->format('Y-m-d H:i:s');
  $dto->modify('+3 days');
  $dto->setTime(22, 59, 59);
  $ret['week_end'] = $dto->format('Y-m-d H:i:s');

   $ret['week_start'] = str_replace(" ", "T", $ret['week_start']);
   $ret['week_mid'] = str_replace(" ", "T", $ret['week_mid']);
   $ret['week_midForEnd'] = str_replace(" ", "T", $ret['week_midForEnd']);
   $ret['week_end'] = str_replace(" ", "T", $ret['week_end']);

  return $ret;
}

function extractUsefulData($client, $arrayData, $token) {

  $params = array('Api_SessionId_1' => array(
    'id' => $token
  ));

  $usefulData = array();

  $usefulData[] = new stdClass();
  $usefulData[] = new stdClass();
  $usefulData[] = new stdClass();

  $vehicleIds = $client->getVehiclesV2($params);

  $vehicleInfos = $vehicleIds->result->vehicleInfos;


  $i = 0;

  foreach ($vehicleInfos as $vehicle => $infos) {

    $usefulData[0]->{$infos->vin} = new stdClass();

    $tempTotalTime = 0;

    if(property_exists($arrayData[0][$i], 'drivingSeconds')) {

            $tempTotalTime += $arrayData[0][$i]->drivingSeconds;
    }

    if(property_exists($arrayData[0][$i], 'idleSeconds')) {

            $tempTotalTime += $arrayData[0][$i]->idleSeconds;
    }

    if(property_exists($arrayData[0][$i], 'PTOseconds')) {

            $tempTotalTime += $arrayData[0][$i]->PTOseconds;
    }

    $tempTotalDistance = 0;

    if(property_exists($arrayData[0][$i], 'unitOnMeters')) {

            $tempTotalDistance = $arrayData[0][$i]->unitOnMeters/1000;
    }

    if($tempTotalDistance != 0) {

      $usefulData[0]->{$infos->vin}->brakeCount =
                                      ($arrayData[0][$i]->brakeCount/$tempTotalDistance)*100;

      $usefulData[0]->{$infos->vin}->stopCount =
                                      ($arrayData[0][$i]->stopCount/$tempTotalDistance)*100;
    }

    if(property_exists($arrayData[0][$i], 'lovEngineOutOfGreenAreaMeters')
      && $arrayData[0][$i]->lovEngineOutOfGreenAreaMeters != 0
      && property_exists($arrayData[0][$i], 'lovEngineOutOfGreenAreaCentiliters')) {

      $usefulData[0]->{$infos->vin}->outOfGreenAreaLitre
                                    = (($arrayData[0][$i]->lovEngineOutOfGreenAreaCentiliters/100)/
                                    ($arrayData[0][$i]->lovEngineOutOfGreenAreaMeters/1000))*100;
    }

    if(property_exists($arrayData[0][$i], 'engineOverloadMeters')
      && $arrayData[0][$i]->engineOverloadMeters != 0
      && property_exists($arrayData[0][$i], 'engineOverloadCentilitres')) {

      $usefulData[0]->{$infos->vin}->engineOverloadLitre
                                    = (($arrayData[0][$i]->engineOverloadCentilitres/100)/
                                    ($arrayData[0][$i]->engineOverloadMeters/1000))*100;
    }

    $tempTotalTransmission = 0;

    //
    if(property_exists($arrayData[0][$i], 'lovTransmissionManualModeSeconds')) {

        $tempTotalTransmission += $arrayData[0][$i]->lovTransmissionManualModeSeconds;
    }

    if(property_exists($arrayData[0][$i], 'lovTransmissionAutoModeSeconds')) {

        $tempTotalTransmission += $arrayData[0][$i]->lovTransmissionAutoModeSeconds;
    }

    if(property_exists($arrayData[0][$i], 'lovTransmissionPowerModeSeconds')) {

        $tempTotalTransmission += $arrayData[0][$i]->lovTransmissionPowerModeSeconds;
    }


    if($tempTotalTransmission != 0) {


      if(property_exists($arrayData[0][$i], 'lovTransmissionAutoModeSeconds')) {

        $usefulData[0]->{$infos->vin}->autoMode
                                      = ($arrayData[0][$i]->lovTransmissionAutoModeSeconds
                                      /$tempTotalTransmission)*100;
      }

      if(property_exists($arrayData[0][$i], 'lovTransmissionManualModeSeconds')) {

        $usefulData[0]->{$infos->vin}->manualMode
                                      = ($arrayData[0][$i]->lovTransmissionManualModeSeconds
                                      /$tempTotalTransmission)*100;
      }

      if(property_exists($arrayData[0][$i], 'lovTransmissionPowerModeSeconds')) {

        $usefulData[0]->{$infos->vin}->powerMode
                                      = ($arrayData[0][$i]->lovTransmissionPowerModeSeconds
                                      /$tempTotalTransmission)*100;
      }
    }

    if($tempTotalTime != 0) {

      $usefulData[0]->{$infos->vin}->totalTime =
                                      secondsToHours($tempTotalTime);

      $usefulData[0]->{$infos->vin}->idle =
                                      ($arrayData[0][$i]->idleSeconds/$tempTotalTime)*100;


      $usefulData[0]->{$infos->vin}->ecoMode =
                                      ($arrayData[0][$i]->economySeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->outOfGreenArea =
                                      ($arrayData[0][$i]->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->coasting =
                                      ($arrayData[0][$i]->coastingSeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->cruise =
                                      ($arrayData[0][$i]->cruiseSeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->roadOverspeed =
                                      ($arrayData[0][$i]->roadOverspeedSeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->engineOverRev =
                                      ($arrayData[0][$i]->topGearSeconds/$tempTotalTime)*100;

      $usefulData[0]->{$infos->vin}->engineOverload =
                                      ($arrayData[0][$i]->engineOverloadSeconds/$tempTotalTime)*100;

    }

    $i++;
  }

  $driverIds = $client->getDriversV2($params);

  $driverInfos = $driverIds->result->driverInfos;

  foreach ($driverInfos as $driver => $infos) {

    $usefulData[1]->{$infos->digitalTachoCardId}
                                = new stdClass();

    if(property_exists($arrayData[1], $infos->driverId->id)) {

    $tempTotalTime = 0;

    if(property_exists($arrayData[1], $infos->driverId->id)) {

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'drivingSeconds')) {

          $tempTotalTime += $arrayData[1]->{$infos->driverId->id}->drivingSeconds;
      }

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'idleSeconds')) {

          $tempTotalTime += $arrayData[1]->{$infos->driverId->id}->idleSeconds;
      }

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'PTOseconds')) {

          $tempTotalTime += $arrayData[1]->{$infos->driverId->id}->PTOseconds;
      }
    }

    $tempTotalDistance = 0;

    if(  property_exists($arrayData[1], $infos->driverId->id)
      && property_exists($arrayData[1]->{$infos->driverId->id}, 'drivingMeters')) {

        $tempTotalDistance = $arrayData[1]->{$infos->driverId->id}->drivingMeters/1000;
    }


    if($tempTotalDistance != 0) {

      $usefulData[1]->{$infos->digitalTachoCardId}->brakeCount =
                                      ($arrayData[1]->{$infos->driverId->id}->brakeCount/$tempTotalDistance)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->stopCount =
                                      ($arrayData[1]->{$infos->driverId->id}->stopCount/$tempTotalDistance)*100;
    }

    if(property_exists($arrayData[1]->{$infos->driverId->id}, 'engineOverloadMeters')
      && $arrayData[1]->{$infos->driverId->id}->engineOverloadMeters != 0
      && property_exists($arrayData[1]->{$infos->driverId->id}, 'engineOverloadCentilitres')) {

      $usefulData[1]->{$infos->digitalTachoCardId}->engineOverloadLitre =
                    (($arrayData[1]->{$infos->driverId->id}->engineOverloadCentilitres/100)/
                    ($arrayData[1]->{$infos->driverId->id}->engineOverloadMeters/1000))*100;
    }

    if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovEngineOutOfGreenAreaMeters')
      && $arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaMeters != 0
      && property_exists($arrayData[1]->{$infos->driverId->id}, 'lovEngineOutOfGreenAreaCentiliters')) {

      $usefulData[1]->{$infos->digitalTachoCardId}->outOfGreenAreaLitre =
                    (($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaCentiliters/100)/
                    ($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaMeters/1000))*100;
    }

    $totalTimeTransmission = 0;
    //

    if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionManualModeSeconds')) {

        $totalTimeTransmission += $arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds;
    }

    if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionAutoModeSeconds')) {

        $totalTimeTransmission += $arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds;
    }

    if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionPowerModeSeconds')) {

        $totalTimeTransmission += $arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds;
    }

    if($totalTimeTransmission != 0) {

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionManualModeSeconds')) {

        $usefulData[1]->{$infos->digitalTachoCardId}->autoMode
                                      = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionAutoModeSeconds
                                      /$totalTimeTransmission)*100;
      }

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionAutoModeSeconds')) {

        $usefulData[1]->{$infos->digitalTachoCardId}->manualMode
                                      = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionManualModeSeconds
                                      /$totalTimeTransmission)*100;
      }

      if(property_exists($arrayData[1]->{$infos->driverId->id}, 'lovTransmissionPowerModeSeconds')) {

        $usefulData[1]->{$infos->digitalTachoCardId}->powerMode
                                      = ($arrayData[1]->{$infos->driverId->id}->lovTransmissionPowerModeSeconds
                                      /$totalTimeTransmission)*100;
      }

    }

    if(property_exists($arrayData[1], $infos->driverId->id) && $tempTotalTime != 0) {


      $usefulData[1]->{$infos->digitalTachoCardId}->totalTime =
                              secondsToHours($tempTotalTime);

      $usefulData[1]->{$infos->digitalTachoCardId}->idle =
                                      ($arrayData[1]->{$infos->driverId->id}->idleSeconds/$tempTotalTime)*100;


      $usefulData[1]->{$infos->digitalTachoCardId}->ecoMode =
                                      ($arrayData[1]->{$infos->driverId->id}->economySeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->outOfGreenArea =
                                      ($arrayData[1]->{$infos->driverId->id}->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->coasting =
                                      ($arrayData[1]->{$infos->driverId->id}->coastingSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->cruise =
                                      ($arrayData[1]->{$infos->driverId->id}->cruiseSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->roadOverspeed =
                                      ($arrayData[1]->{$infos->driverId->id}->roadOverspeedSeconds/$tempTotalTime)*100;


      $usefulData[1]->{$infos->digitalTachoCardId}->engineOverRev =
                                      ($arrayData[1]->{$infos->driverId->id}->topGearSeconds/$tempTotalTime)*100;

      $usefulData[1]->{$infos->digitalTachoCardId}->engineOverload =
                                      ($arrayData[1]->{$infos->driverId->id}->engineOverloadSeconds/$tempTotalTime)*100;


    }


   }
  }

  foreach ($driverInfos as $driver => $driverData) {

    foreach ($vehicleInfos as $vehicle => $infos) {

      $tempDriverId = $driverData->driverId->id;
      $tempTruckId = $infos->vehicleId->id;

      if(   property_exists( $arrayData[2], $tempDriverId) &&
            property_exists( $arrayData[2]->$tempDriverId, $tempTruckId)
        && (!property_exists( $usefulData[2], $tempDriverId)
        || !property_exists( $usefulData[2]->$tempDriverId, $tempTruckId))
        ) {
            $tacho = $driverData->digitalTachoCardId;
            $vin = $infos->vin;


            if(!property_exists($usefulData[2], $tacho)) {

              $usefulData[2]->{$tacho} = new stdClass();
            }

            $usefulData[2]->$tacho->{$vin} = new stdClass();

            $tempTotalTime = 0;

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'drivingSeconds')) {

              $tempTotalTime += $arrayData[2]->$tempDriverId->$tempTruckId->drivingSeconds;
            }

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'idleSeconds')) {

              $tempTotalTime += $arrayData[2]->$tempDriverId->$tempTruckId->idleSeconds;
            }

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'PTOseconds')) {

              $tempTotalTime += $arrayData[2]->$tempDriverId->$tempTruckId->PTOseconds;
            }

            $tempTotalDistance = 0;

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'unitOnMeters')) {

              $tempTotalDistance = $arrayData[2]->$tempDriverId->$tempTruckId->unitOnMeters/1000;
            }

            if($tempTotalDistance != 0) {

              $usefulData[2]->$tacho->$vin->brakeCount =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->brakeCount/$tempTotalDistance)*100;

              $usefulData[2]->$tacho->$vin->stopCount =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->stopCount/$tempTotalDistance)*100;
            }

            if($tempTotalTime != 0) {

              $usefulData[2]->$tacho->$vin->totalTime =
                                              secondsToHours($tempTotalTime);


              $usefulData[2]->$tacho->$vin->idle =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->idleSeconds/$tempTotalTime)*100;


              $usefulData[2]->$tacho->$vin->ecoMode =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->economySeconds/$tempTotalTime)*100;

              $usefulData[2]->$tacho->$vin->outOfGreenArea =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->lovEngineOutOfGreenAreaSeconds/$tempTotalTime)*100;

              $usefulData[2]->$tacho->$vin->coasting =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->coastingSeconds/$tempTotalTime)*100;

              $usefulData[2]->$tacho->$vin->cruise =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->cruiseSeconds/$tempTotalTime)*100;

              $usefulData[2]->$tacho->$vin->roadOverspeed =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->roadOverspeedSeconds/$tempTotalTime)*100;


              $usefulData[2]->$tacho->$vin->engineOverRev =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->topGearSeconds/$tempTotalTime)*100;

              $usefulData[2]->$tacho->$vin->engineOverload =
                                              ($arrayData[2]->$tempDriverId->$tempTruckId->engineOverloadSeconds/$tempTotalTime)*100;
            }

            $tempTotalTransmission = 0;

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionManualModeSeconds')) {

              $tempTotalTransmission += $arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionManualModeSeconds;
            }

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionAutoModeSeconds')) {

              $tempTotalTransmission += $arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionAutoModeSeconds;
            }

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionPowerModeSeconds')) {

              $tempTotalTransmission += $arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionPowerModeSeconds;
            }


            if($tempTotalTransmission != 0) {
              if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionAutoModeSeconds')) {

                $usefulData[2]->$tacho->$vin->autoMode
                                              = ($arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionAutoModeSeconds)
                                              /($tempTotalTransmission)*100;
              }

              if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionManualModeSeconds')) {

                $usefulData[2]->$tacho->$vin->manualMode
                                              = ($arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionManualModeSeconds)
                                              /($tempTotalTransmission)*100;
              }

              if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovTransmissionPowerModeSeconds')) {

                $usefulData[2]->$tacho->$vin->powerMode
                                              = ($arrayData[2]->$tempDriverId->$tempTruckId->lovTransmissionPowerModeSeconds)
                                              /($tempTotalTransmission)*100;
              }
            }


            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovEngineOutOfGreenAreaMeters')
              && $arrayData[2]->$tempDriverId->$tempTruckId->lovEngineOutOfGreenAreaMeters != 0
              && property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'lovEngineOutOfGreenAreaCentiliters')) {

              $usefulData[2]->$tacho->$vin->outOfGreenAreaLitre
                                            = (($arrayData[2]->$tempDriverId->$tempTruckId->lovEngineOutOfGreenAreaCentiliters/100)/
                                            ($arrayData[2]->$tempDriverId->$tempTruckId->lovEngineOutOfGreenAreaMeters/1000))*100;
            }

            if(property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'engineOverloadMeters')
              && $arrayData[2]->$tempDriverId->$tempTruckId->engineOverloadMeters != 0
              && property_exists($arrayData[2]->$tempDriverId->$tempTruckId, 'engineOverloadCentilitres')) {

              $usefulData[2]->$tacho->$vin->engineOverloadLitre
                                            = (($arrayData[2]->$tempDriverId->$tempTruckId->engineOverloadCentilitres/100)/
                                            ($arrayData[2]->$tempDriverId->$tempTruckId->engineOverloadMeters/1000))*100;
            }
      }
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

            if(is_object($data) && property_exists($data, 'driverId') && is_object($data->driverId)) {

              $tempDriverId = $data->driverId->id;
            }

            if(isset($tempDriverId) && !property_exists($tempDriverStats, $tempDriverId)) {

              $tempDriverStats->{$tempDriverId} = new stdClass();

            }

            //Si on connais le driver
            if(isset($tempDriverId)) {

              //Si le driver n'est pas connue on l'ajoute
              if(!property_exists($tempDrivertTrucksStats, $tempDriverId)) {

                $tempDrivertTrucksStats->{$tempDriverId} = new stdClass();
                $tempDrivertTrucksStats->{$tempDriverId}->{$tempVehicleId} = new stdClass();

              } else if (isset($tempVehicleId) && !property_exists($tempDrivertTrucksStats->$tempDriverId, $tempVehicleId)) { //si on ne connait pas le camion

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

            unset($tempVehicleId);
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

      if(!property_exists($weekStatsObj, $key)) {

        $weekStatsObj->{$key} = new stdClass();
      }

      if(!property_exists($weekStatsObj->$key, $data)) {

        $weekStatsObj->$key->{$data} = $value;
      } else {

        $weekStatsObj->$key->{$data} += $value;
      }

    }
  }

  return $weekStatsObj;
}

function sumBetweenWeeksDriversTrucks($firstWeekObj, $secondWeekObj) {


  foreach ($secondWeekObj as $driverId => $allStats) {

    if(!property_exists($firstWeekObj, $driverId)) {

      $firstWeekObj->{$driverId} = $allStats;

    } else {

      foreach ($allStats as $vehicleId => $stats) {

        if(!property_exists($firstWeekObj->$driverId, $vehicleId)) {

          $firstWeekObj->{$driverId}->{$vehicleId} = $stats;
        } else {

          foreach ($stats as $key => $value) {

            $firstWeekObj->{$driverId}->{$vehicleId}->{$key} += $value;
          }
        }
      }
    }

  }

  return $firstWeekObj;
}

function getWeekData($username, $password, $week, $year) {

  $client = new SoapClient("https://api2.dynafleetonline.com/wsdl",
                                              array('proxy_host'     => "pxlyon2.srv.volvo.com",
                                              'proxy_port'     => 8080));

  $token = loginRequest($username, $password, $client);
  $vehicleList = vehiclesRequest($token, $client);

  $week_array = getStartAndEndDate($week,$year);

  $firstReportArray = array();
  $secondReportArray = array();

  $requestByToken = 18;
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
      sleep(2);
    }

    $temp = reportVehicleRequest($token, $client, $week_array['week_midForEnd'], $week_array['week_end'], $value->vehicleId->id, $iterator);
    array_push($secondReportArray, $temp);

    $iterator++;
  }

  $week_array = null;
  $vehicleList = null;

  $sumFirstChunk = sumStatsChunk($firstReportArray);
  $firstReportArray = null;

  $sumSecondChunk = sumStatsChunk($secondReportArray);
  $secondReportArray = null;

  $finalDatas = array();

  $finalDatas[] = sumBetweenWeeks($sumFirstChunk[0], $sumSecondChunk[0]);
  $sumFirstChunk[0] = null;
  $sumSecondChunk[0] = null;

  $finalDatas[] = sumBetweenWeeksObj($sumFirstChunk[1], $sumSecondChunk[1]);
  $sumFirstChunk[1] = null;
  $sumSecondChunk[1] = null;

  $finalDatas[] = sumBetweenWeeksDriversTrucks($sumFirstChunk[2], $sumSecondChunk[2]);
  $sumFirstChunk = null;
  $sumSecondChunk = null;

  return extractUsefulData($client, $finalDatas, loginRequest($username, $password, $client, 0));
}

?>
