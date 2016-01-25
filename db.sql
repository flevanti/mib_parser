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

*/