CREATE TABLE `ryan_raw` (
  `id`                      INT(11) NOT NULL AUTO_INCREMENT,
  `origin`                  VARCHAR(45)      DEFAULT NULL,
  `destination`             VARCHAR(45)      DEFAULT NULL,
  `trip`                    VARCHAR(45)      DEFAULT NULL,
  `flight_number`           VARCHAR(45)      DEFAULT NULL,
  `departure`               VARCHAR(45)      DEFAULT NULL,
  `arrival`                 VARCHAR(45)      DEFAULT NULL,
  `duration`                VARCHAR(45)      DEFAULT NULL,
  `flight_key`              VARCHAR(150)     DEFAULT NULL,
  `fares_left`              VARCHAR(45)      DEFAULT NULL,
  `fare_currency`           VARCHAR(45)      DEFAULT NULL,
  `fare_eco`                VARCHAR(45)      DEFAULT NULL,
  `fare_eco_published`      VARCHAR(45)      DEFAULT NULL,
  `fare_business`           VARCHAR(45)      DEFAULT NULL,
  `fare_business_published` VARCHAR(45)      DEFAULT NULL,
  `ts_retrieved`            INT(11)          DEFAULT NULL,
  `raw_record`              MEDIUMTEXT,
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;


ALTER TABLE ryan_raw ADD import_session_id VARCHAR(100) NULL;
ALTER TABLE ryan_raw ADD departure_yyyymmdd CHAR(8) NULL;
ALTER TABLE ryan_raw ADD departure_mm CHAR(2) NULL;
ALTER TABLE ryan_raw ADD departure_dd CHAR(2) NULL;
ALTER TABLE ryan_raw ADD departure_yyyy CHAR(4) NULL;


ALTER TABLE `ryan_raw` ADD INDEX `idx_session_id` (`import_session_id`);
ALTER TABLE `ryan_raw` ADD INDEX `idx_departure_yyyymmdd` (`departure_yyyymmdd`);

ALTER TABLE ryan_raw ADD fare_eco_ DECIMAL(7, 3) NULL;
ALTER TABLE ryan_raw ADD fare_eco_published_ DECIMAL(7, 3) NULL;
ALTER TABLE ryan_raw ADD fare_business_ DECIMAL(7, 3) NULL;
ALTER TABLE ryan_raw ADD fare_business_published_ DECIMAL(7, 3) NULL;


ALTER TABLE ryan_raw ADD departure_ts INT NULL;
ALTER TABLE ryan_raw ADD arrival_ts INT NULL;
ALTER TABLE ryan_raw ADD departure_secs_midnight INT NULL;

CREATE INDEX idx_departure_ts ON ryan_raw (departure_ts);

CREATE INDEX idx_trip ON ryan_raw (trip);


CREATE TABLE ryan_data
(
  id                      INT(11) DEFAULT '0' NOT NULL,
  flight_number           VARCHAR(45),
  trip                    VARCHAR(45),
  fare_currency           VARCHAR(45),
  max_eco                 DECIMAL(7, 4),
  min_eco                 DECIMAL(7, 4),
  fare_eco_               DECIMAL(7, 4),
  max_business            DECIMAL(7, 4),
  min_business            DECIMAL(7, 4),
  fare_business_          DECIMAL(7, 4),
  departure_yyyymmdd      CHAR(8),
  departure_mm            CHAR(2),
  departure_dd            CHAR(2),
  departure_secs_midnight INT(11),
  ts_retrieved            INT(11),
  import_session_id       VARCHAR(100)

)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;
CREATE INDEX idx_trip ON ryan_data (trip);
CREATE INDEX idx_fligth_number ON ryan_data (flight_number);


/*

SCRIPT USED TO UPDATE DB - NO NEED TO RUN THESE ON NEW DEPLOYMENT

UPDATE ryan_raw
SET fare_eco_              = fare_eco,
  fare_eco_published_      = fare_eco_published,
  fare_business_           = fare_business,
  fare_business_published_ = fare_business_published;


UPDATE ryan_raw
SET departure_yyyymmdd = replace(substring(departure, 1, 10), '-', ''),
  departure_yyyy       = substring(departure_yyyymmdd, 1, 4),
  departure_mm         = substring(departure_yyyymmdd, 5, 2),
  departure_dd         = substring(departure_yyyymmdd, 7, 2);


UPDATE ryan_raw
SET departure_ts          = unix_timestamp(STR_TO_DATE(`departure`, "%Y-%m-%dT%H:%i:%s.000")),
  arrival_ts              = unix_timestamp(STR_TO_DATE(`arrival`, "%Y-%m-%dT%H:%i:%s.000")),
  departure_secs_midnight = (FROM_UNIXTIME(departure_ts, '%H') * 60 * 60) + (FROM_UNIXTIME(departure_ts, '%i') * 60) +
                            (FROM_UNIXTIME(departure_ts, '%s'))
WHERE departure_ts IS NULL;



*/