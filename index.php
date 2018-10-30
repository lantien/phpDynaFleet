<?php

ini_set('max_execution_time', 500);

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
  $ret['week_end'] = $dto->format('Y-m-d H:i:s');

   $ret['week_start'] = str_replace(" ", "T", $ret['week_start']);
   $ret['week_mid'] = str_replace(" ", "T", $ret['week_mid']);
   $ret['week_end'] = str_replace(" ", "T", $ret['week_end']);

  return $ret;
}

// function makeStats($firstRawReport, $secondRawReport, $client) {
//
//   $truckStats = new stdClass();
//
//   for($i = 0; $i < count($firstRawReport); ++$i) {
//     $tempVehicleId = $firstRawReport[$i]->vehicleId->id;
//
//     //$truckStats->{$tempVehicleId} = 'Here we go';
//
//      foreach($firstRawReport[$i]->dataEntries as $value) {
//        foreach($value as $key=>$temp) {
//
//          $truckStats->{$tempVehicleId}->{$key} = $firstRawReport[$i]->dataEntries->{$key};
//                                             //+ $secondRawReport[$i]->dataEntries->{$key};
//        }
//      }
//   }
//
//   var_dump($truckStats);
//
//
// }

function sumStatsChunk($rawReport) {

  $sumStats = new stdClass();

  foreach($rawReport->dataEntries as $iterator=>$data) {
    foreach($data as $key=>$value) {

      if($key != 'driverId' && $key != 'startTime' && $key != 'endTime') {
        if(!property_exists($sumStats, $key)) {

          $sumStats->{$key} = $value->value;
        } else {

          $sumStats->{$key} += $value->value;
        }
      }

    }
  }

  return $sumStats;

}

$week_array = getStartAndEndDate(40,2018);

var_dump($week_array);

$iterator = 0;

$firstReportArray = array();
$secondReportArray = array();

$temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[0]->vehicleId->id, $iterator);
array_push($firstReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[1]->vehicleId->id, $iterator);
array_push($firstReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[2]->vehicleId->id, $iterator);
array_push($firstReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $vehicleList[3]->vehicleId->id, $iterator);
array_push($firstReportArray, $temp);

////////////////SECOND PART OF THE week_end
$temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[0]->vehicleId->id, $iterator);
array_push($secondReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[1]->vehicleId->id, $iterator);
array_push($secondReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[2]->vehicleId->id, $iterator);
array_push($secondReportArray, $temp);

$temp = reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $vehicleList[3]->vehicleId->id, $iterator);
array_push($secondReportArray, $temp);

var_dump(sumStatsChunk($secondReportArray[0]));

// $iterator = 0;
//
// foreach($vehicleList as &$value) {
//
//   if($iterator%$requestByToken==0 && $iterator != 0) {
//
//     $token = loginRequest($username, $password, $client, $iterator);
//   }
//
//   reportVehicleRequest($token, $client, $week_array['week_start'], $week_array['week_mid'], $value->vehicleId->id, $iterator);
//   $iterator++;
// }
//
// sleep(20);
//
// $iterator = 0;
//
// foreach($vehicleList as &$value) {
//
//   if($iterator%$requestByToken==0 && $iterator != 0) {
//
//     $token = loginRequest($username, $password, $client, $iterator);
//   }
//
//   reportVehicleRequest($token, $client, $week_array['week_mid'], $week_array['week_end'], $value->vehicleId->id, $iterator);
//   $iterator++;
// }

?>
