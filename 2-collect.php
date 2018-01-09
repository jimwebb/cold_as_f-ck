<?php

$directory = "./raw_firehose/";
$phrases = array(); 
$collected_tweets = array();
$total = 0;

function remove_emoji($text){
return preg_replace('/([0-9#][\x{20E3}])|[\x{00ae}\x{00a9}\x{203C}\x{2047}\x{2048}\x{2049}\x{3030}\x{303D}\x{2139}\x{2122}\x{3297}\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?/u', '', $text);
}


foreach ( glob( $directory . "*.txt" ) as $file) {
if( $file == '.' || $file == '..' ) continue;

$handle = fopen( $file, "r" );

if ( $handle ) {
    while (($line = fgets($handle)) !== false) {
        $tweet = json_decode( $line );
        
        if ( preg_match("/(it[']?s|it is) (cold|colder|windy|windier|hot|hotter|rainy|rainier) (as|than) (\S+[’']s \S*|2 .+|two .+|it .+|the .+|our \S+|your \S+|my \S+|a .+|satan\S* \S+|\S+)/i", strtolower( $tweet->text ), $matches ) !== 0 ) {
          
          // remove "http" links from the end
          $matches[4] = preg_replace("/http.*/", "", $matches[4]);
        
          $phrase = "it's" . " " . $matches[2] . " " . $matches[3] . " " . $matches[4];
          
          // trim punctuation from the end
          $phrase = rtrim($phrase, ".,!?'~` \"\.");
          
          // trim emoji
          $phrase = remove_emoji($phrase);
              
          // switch ($matches[2]):
          // 
          //   case 'cold':
          //   case 'colder':
          //     $type = "cold";
          //     break;
          //   case 'hot':
          //   case 'hotter':
          //     $type = "hot";
          //     break;
          //   case 'windy':
          //   case 'windier':
          //     $type = "wind";
          //     break;
          //   case 'rainy':
          //   case 'rainier':
          //     $type = "rain";
          //     break;
          // 
          // endswitch;
          
          $type = $matches[2];
          
          if ( !isset( $phrases[$phrase] ) ) {
            $phrases[$phrase] = 1;
          } else {
            $phrases[$phrase]++;
          }
          
          $total++;
        
          $coordinates = average_center($tweet->place->bounding_box->coordinates[0]);
          
          $result = array(  'phrase' => utf8_encode( $phrase ), 
                            'type' => utf8_encode( $type ),
                            'text' => utf8_encode( $tweet->text ),
                            'lat' => $coordinates[0],
                            'long' => $coordinates[1],
                            'url' => "https://twitter.com/" . $tweet->user->screen_name  . "/status/" . $tweet->id,
                            'id' => $tweet->id 
                         );
                         
           // some timedates are missing, don't write them
           if ( isset( $tweet->created_at ) ) {
             $result['unixtime'] = strtotime( $tweet->created_at );
             $result['datetime'] = date( 'c',  $result['unixtime'] );
           } else {
             // pull from the file created time
             $result['unixtime'] = filemtime( $file );
             $result['datetime'] = date( 'c',  filemtime( $file ) );             
           }
           
          // place into an array for use later
          $collected_tweets[$tweet->id] = $result;                 
          
        }
    }

    fclose($handle);
} else {
    // error opening the file.
} }

arsort( $phrases );
print_r( $phrases ); 
echo "Total: " . $total;


// --------------- Save all tweets ------------------
// check for existing tweets -- if they have data set (e.g., weather data), merge it with our data here
$existing_tweets = file_exists('data/collected-tweets.json') ? json_decode( file_get_contents('data/collected-tweets.json'), true ) : false;

if ( $existing_tweets ) {
    foreach ( $collected_tweets as $id=>$tweet ) {
      if ( isset( $existing_tweets->$id ) ) {
        $collected_tweets[$id] = $collected_tweets[$id] + $existing_tweets[$id];
      }
    }
}

// write the tweets to the output file
file_put_contents('data/collected-tweets.json', json_encode( $collected_tweets ) );

// --------------- Save tweets to DB ------------------

$db = new SQLite3('data/collected-tweets.db');
foreach ( $collected_tweets as $id => $tweet ) {
  
  if ( $db->querySingle( 'SELECT id FROM tweets WHERE id = ' . $id, true ) ) {
    continue;
  }
  
  $insert = $db->prepare('REPLACE INTO tweets (id, phrase, type, text, lat, long, url, unixtime, datetime) values (:id, :phrase, :type, :text, :lat, :long, :url, :unixtime, :datetime);');
  $insert->bindValue(':id', $id, SQLITE3_INTEGER);
  $insert->bindValue(':phrase', $tweet['phrase'], SQLITE3_TEXT);
  $insert->bindValue(':type', $tweet['type'], SQLITE3_TEXT);
  $insert->bindValue(':text', $tweet['text'], SQLITE3_TEXT);
  $insert->bindValue(':lat', $tweet['lat'], SQLITE3_FLOAT);
  $insert->bindValue(':long', $tweet['long'], SQLITE3_FLOAT);
  $insert->bindValue(':url', $tweet['url'], SQLITE3_TEXT);
  $insert->bindValue(':unixtime', $tweet['unixtime'], SQLITE3_INTEGER);
  $insert->bindValue(':datetime', $tweet['datetime'], SQLITE3_TEXT);
  $insert->execute();
}

// https://stackoverflow.com/questions/6671183/calculate-the-center-point-of-multiple-latitude-longitude-coordinate-pairs
function average_center($data) {
    $lat = 0;
    $long = 0;
    $num = 0;
    
    foreach ( $data as $pair ) {
      $long += $pair[0];
      $lat += $pair[1];
      $num++;
    }
        
    return array($lat / $num, $long / $num);
}


