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



