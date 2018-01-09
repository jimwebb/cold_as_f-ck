<?php

$db = new SQLite3('data/collected-tweets.db');

$results = $db->query('CREATE TABLE IF NOT EXISTS tweets (
                          id INTEGER PRIMARY KEY,
                          phrase VARCHAR, 
                          type VARCHAR,
                          text VARCHAR,
                          lat FLOAT,
                          long FLOAT,
                          url VARCHAR,
                          datetime TEXT,
                          unixtime INTEGER,
                          temperature FLOAT,
                          apparentTemperature FLOAT,
                          windSpeed FLOAT,
                          precipAccumulation FLOAT,
                          precipIntensity FLOAT,
                          temperatureHigh FLOAT,
                          temperatureLow FLOAT,
                          windSpeedHigh FLOAT,
                          apparentTemperatureHigh FLOAT,
                          apparentTemperatureLow FLOAT,
                          precipIntensityMax FLOAT
                        );'
                      );
$results = $db->query('CREATE UNIQUE INDEX id ON tweets (id);'
                      );