<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// https://darksky.net/dev/docs#time-machine-request
global $api_secret; 

$api_secret = "a8611156776a022a38cedc99342075c7"; // this was mine, it's now reset, get your own at https://darksky.net/dev
$api_url = "https://api.darksky.net/forecast/";

// handle arguments
if( $argc>1 ) {
  parse_str(implode('&',array_slice($argv, 1)), $_GET);
}
  
// Set up Dark Sky API
require_once('vendor/forecast-php/src/Forecast/Forecast.php');
use Forecast\Forecast;
$i = 0;

// Pull new tweets from DB
global $db;
$db = new SQLite3('data/collected-tweets.db');
echo "starting\r\n";

if ( isset( $_GET['id'] ) && $_GET['id'] ) {
  echo "doing special id: " . $_GET['id'] . "\r\n";
  // icky! don't ever make this public!
  $tweet = $db->querySingle( 'SELECT * FROM tweets WHERE id = ' . $_GET['id'], true );
  save_weather($tweet);
  
  print_r($weather);
  exit();
}

while ( $tweet = $db->querySingle( 'SELECT * FROM tweets WHERE temperature IS null LIMIT 1', true ) ): 
  
  save_weather($tweet);
  
  $i++;
  
endwhile;
 

function save_weather($tweet) {
  global $api_secret; 
  global $db;
  
  $forecast = new Forecast($api_secret);

  try {
    $weather = $forecast->get( $tweet['lat'], $tweet['long'], $tweet['unixtime'],
      array('exclude' => 'flags,hourly')
      );
  } catch (Exception $e) {
    echo "Sorry, there was an error: ".$e->getMessage();
    exit();
  }

  
  if ( !isset($weather->currently) ) {
    echo "Sorry, there was an error. \r\n";
    print_r($tweet);
    print_r($weather);
    exit();
  }
  
  if ( isset($weather->currently) && !isset( $weather->currently->temperature ) ) {
  // there's a problem with this one -- update it as a false record.
    echo "no weather: " . $tweet['id'] . "\r\n";
    print_r($tweet);
    print_r($weather);
    $insert = $db->prepare('DELETE FROM tweets WHERE id = :id;');
    $insert->bindValue(':id', $tweet['id'], SQLITE3_TEXT);
    $insert->execute();
    return;
  }


  $insert = $db->prepare('UPDATE tweets SET
                                          temperature = :temperature,
                                          apparentTemperature = :apparentTemperature,
                                          windSpeed = :windSpeed,
                                          precipAccumulation = :precipAccumulation,
                                          precipIntensity = :precipIntensity,
                                          temperatureHigh = :temperatureHigh,
                                          temperatureLow = :temperatureLow,
                                          windSpeedHigh = :windSpeedHigh,
                                          apparentTemperatureHigh = :apparentTemperatureHigh,
                                          apparentTemperatureLow = :apparentTemperatureLow,
                                          precipIntensityMax = :precipIntensityMax
                                        WHERE id = :id
                          ;');

  
  $values = array( 
    ':id'                   => $tweet['id'],
    ':temperature'          => $weather->currently->temperature,
    ':apparentTemperature'  => $weather->currently->apparentTemperature,
    ':windSpeed'            => $weather->currently->windSpeed,
    ':precipAccumulation'   => isset( $weather->daily->data[0]->precipAccumulation ) ? $weather->daily->data[0]->precipAccumulation : 0,
    ':precipIntensity'      => $weather->daily->data[0]->precipIntensity,
    ':temperatureHigh'      => $weather->daily->data[0]->temperatureHigh,
    ':temperatureLow'       => $weather->daily->data[0]->temperatureLow,
    ':apparentTemperatureHigh' => $weather->daily->data[0]->apparentTemperatureHigh,
    ':apparentTemperatureLow' => $weather->daily->data[0]->apparentTemperatureLow,
    ':precipIntensityMax'   => $weather->daily->data[0]->precipIntensityMax,
    ':windSpeedHigh'        => $weather->daily->data[0]->windSpeed
  );
  
  sqlite_insert_parameters($insert, $values);

  $insert->execute();

  echo "did " . $tweet['id'] . " ok!\r\n";

}

function sqlite_insert_parameters(&$insert, $values) {
  
  foreach ($values as $key => $value) {
    $insert->bindValue( $key, $value, sqlite_get_arg_type( $value ));
  }
  return;
  
}

function sqlite_get_arg_type($arg)
{
    switch (gettype($arg))
    {
        case 'double': return SQLITE3_FLOAT;
        case 'integer': return SQLITE3_INTEGER;
        case 'boolean': return SQLITE3_INTEGER;
        case 'NULL': return SQLITE3_NULL;
        case 'string': return SQLITE3_TEXT;
        default:
            throw new \InvalidArgumentException('Argument is of invalid type '.gettype($arg));
    }
}