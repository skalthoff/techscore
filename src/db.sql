SET FOREIGN_KEY_CHECKS=0; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `account` ( `first_name` varchar(30) NOT NULL, `last_name` varchar(30) NOT NULL, `username` varchar(40) NOT NULL, `school` varchar(10) NOT NULL, `role` enum('student','coach','staff') NOT NULL DEFAULT 'coach', `password` varchar(48) DEFAULT NULL, `status` enum('requested','pending','accepted','rejected','active','inactive') DEFAULT 'pending', `is_admin` tinyint(1) NOT NULL DEFAULT '0', PRIMARY KEY (`username`), KEY `school` (`school`), CONSTRAINT `account_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `account_school` ( `id` int(11) NOT NULL AUTO_INCREMENT, `account` varchar(40) NOT NULL, `school` varchar(10) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `account` (`account`,`school`), KEY `school` (`school`), CONSTRAINT `account_school_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `account_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `boat` ( `id` int(2) NOT NULL AUTO_INCREMENT, `name` varchar(15) NOT NULL, `occupants` int(1) NOT NULL DEFAULT '2', PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `burgee` ( `school` varchar(10) NOT NULL, `filedata` mediumblob NOT NULL, `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_by` varchar(40) DEFAULT NULL, PRIMARY KEY (`school`), KEY `updated_by` (`updated_by`), CONSTRAINT `burgee_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `burgee_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `conference` ( `id` varchar(8) NOT NULL, `name` varchar(60) NOT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `daily_summary` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `summary_date` date NOT NULL, `summary` text, PRIMARY KEY (`id`), UNIQUE KEY `regatta` (`regatta`,`summary_date`), CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `dt_regatta` ( `id` int(11) NOT NULL, `name` varchar(35) NOT NULL, `nick` varchar(30) DEFAULT NULL, `start_time` datetime DEFAULT NULL COMMENT 'Date and time when regatta started', `end_date` date DEFAULT NULL, `venue` int(4) DEFAULT NULL, `type` enum('conference','intersectional','championship','personal') NOT NULL DEFAULT 'conference', `finalized` datetime DEFAULT NULL, `scoring` enum('standard','combined') NOT NULL DEFAULT 'standard', `num_divisions` tinyint(4) NOT NULL DEFAULT '1', `num_races` tinyint(3) unsigned NOT NULL DEFAULT '1', `hosts` varchar(255) DEFAULT NULL COMMENT 'Comma-delimited list of school ID', `confs` varchar(255) DEFAULT NULL COMMENT 'Comma-delimited list of conference IDs', `boats` varchar(255) DEFAULT NULL COMMENT 'Comma-delimited list of boat names', `singlehanded` tinyint(4) DEFAULT NULL, `season` varchar(3) NOT NULL, `status` varchar(10) DEFAULT NULL, `participant` enum('women','coed') NOT NULL DEFAULT 'coed', PRIMARY KEY (`id`), KEY `venue` (`venue`) ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `dt_team` ( `id` int(7) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `school` varchar(10) DEFAULT NULL, `name` varchar(20) NOT NULL, `rank` tinyint(3) unsigned DEFAULT NULL, `rank_explanation` varchar(100) DEFAULT NULL, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), KEY `school` (`school`), CONSTRAINT `dt_team_ibfk_1` FOREIGN KEY (`id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `dt_team_division` ( `id` int(11) NOT NULL AUTO_INCREMENT, `team` int(11) NOT NULL, `division` enum('A','B','C','D') DEFAULT NULL, `rank` tinyint(3) unsigned NOT NULL, `explanation` tinytext, `penalty` enum('MRP','PFD','LOP','GDQ') DEFAULT NULL, `comments` text, PRIMARY KEY (`id`), UNIQUE KEY `team` (`team`,`division`), CONSTRAINT `dt_team_division_ibfk_3` FOREIGN KEY (`team`) REFERENCES `dt_team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=367 DEFAULT CHARSET=latin1 COMMENT='Rank teams within divisions, and account for possible penalt'; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `finish` ( `id` int(9) NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `team` int(7) NOT NULL, `entered` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `penalty` enum('DSQ','RAF','OCS','DNF','DNS','BKD','RDG','BYE') DEFAULT NULL, `amount` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Non-positive for assigned, otherwise as appropriate for the penalty', `earned` tinyint(3) unsigned DEFAULT NULL COMMENT 'Minimum that an average score can earn.', `displace` bit(1) DEFAULT NULL, `comments` text, `score` int(3) DEFAULT NULL, `explanation` text, PRIMARY KEY (`id`), UNIQUE KEY `race` (`race`,`team`), KEY `team` (`team`), CONSTRAINT `finish_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `finish_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=379 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `host` ( `account` varchar(40) NOT NULL, `regatta` int(5) NOT NULL, `principal` tinyint(1) DEFAULT '0', PRIMARY KEY (`account`,`regatta`), KEY `regatta` (`regatta`), CONSTRAINT `host_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `host_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `host_school` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(11) NOT NULL, `school` varchar(10) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `regatta` (`regatta`,`school`), KEY `school` (`school`), CONSTRAINT `host_school_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `host_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `message` ( `id` int(11) NOT NULL AUTO_INCREMENT, `account` varchar(40) NOT NULL, `created` datetime NOT NULL, `read_time` datetime DEFAULT NULL, `subject` varchar(100) DEFAULT '', `content` text, `active` tinyint(4) DEFAULT '1', PRIMARY KEY (`id`), KEY `account` (`account`), CONSTRAINT `message_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `observation` ( `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `observation` text NOT NULL, `observer` varchar(50) DEFAULT NULL, `noted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `race` (`race`), CONSTRAINT `observation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `penalty_team` ( `team` int(7) NOT NULL, `division` enum('A','B','C','D') NOT NULL DEFAULT 'A', `type` enum('MRP','PFD','LOP','GDQ') NOT NULL DEFAULT 'GDQ', `comments` text, PRIMARY KEY (`team`,`division`), CONSTRAINT `penalty_team_ibfk_1` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `pub_update_log` ( `id` int(11) NOT NULL AUTO_INCREMENT, `request` int(11) NOT NULL, `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `return_code` tinyint(4) DEFAULT '0', `return_mess` varchar(255) DEFAULT '', PRIMARY KEY (`id`), KEY `request` (`request`), CONSTRAINT `pub_update_log_ibfk_1` FOREIGN KEY (`request`) REFERENCES `pub_update_request` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=187 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `pub_update_request` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(11) NOT NULL, `activity` enum('rotation','score','rp','details','summary') DEFAULT 'score' COMMENT 'What changed and needs to be updated?', `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=175 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `pub_update_season` ( `id` int(11) NOT NULL AUTO_INCREMENT, `season` varchar(3) NOT NULL, `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `season` (`season`) ) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `race` ( `id` int(7) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `division` enum('A','B','C','D') NOT NULL DEFAULT 'A', `number` tinyint(3) unsigned NOT NULL, `boat` int(2) DEFAULT NULL, `scored_by` varchar(40) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `regatta_2` (`regatta`,`division`,`number`), KEY `regatta` (`regatta`), KEY `boat` (`boat`), KEY `scored_by` (`scored_by`), CONSTRAINT `race_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `race_ibfk_2` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE, CONSTRAINT `race_ibfk_3` FOREIGN KEY (`scored_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=587 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `regatta` ( `id` int(5) NOT NULL AUTO_INCREMENT, `name` varchar(35) NOT NULL, `nick` varchar(30) DEFAULT NULL, `start_time` datetime DEFAULT NULL COMMENT 'Date and time when regatta started', `end_date` date DEFAULT NULL, `venue` int(4) DEFAULT NULL, `type` enum('conference','intersectional','championship','personal') NOT NULL DEFAULT 'conference', `finalized` datetime DEFAULT NULL, `scoring` enum('standard','combined') NOT NULL DEFAULT 'standard', `participant` enum('women','coed') NOT NULL DEFAULT 'coed', `creator` varchar(40) DEFAULT NULL, PRIMARY KEY (`id`), KEY `venue` (`venue`), CONSTRAINT `regatta_ibfk_1` FOREIGN KEY (`venue`) REFERENCES `venue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `representative` ( `team` int(7) NOT NULL, `sailor` mediumint(9) NOT NULL, UNIQUE KEY `team` (`team`,`sailor`), KEY `sailor` (`sailor`), CONSTRAINT `representative_ibfk_1` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `representative_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `rotation` ( `race` int(7) NOT NULL, `team` int(7) NOT NULL, `sail` varchar(8) NOT NULL, UNIQUE KEY `race` (`race`,`team`), KEY `team` (`team`), CONSTRAINT `rotation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rotation_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `rotation_setup` ( `id` int(11) NOT NULL, `type` enum('standard','swap','static') NOT NULL DEFAULT 'standard', `style` enum('navy','franny','copy') NOT NULL DEFAULT 'copy', `set_size` tinyint(3) unsigned DEFAULT '2', `team_order` enum('none','num','alpha') NOT NULL DEFAULT 'num' ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `rp` ( `id` int(11) NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `team` int(7) NOT NULL, `sailor` mediumint(9) DEFAULT NULL, `boat_role` enum('skipper','crew') NOT NULL DEFAULT 'skipper', PRIMARY KEY (`id`), KEY `race` (`race`), KEY `team` (`team`), KEY `sailor` (`sailor`), CONSTRAINT `rp_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rp_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rp_ibfk_4` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `rp_form` ( `regatta` int(11) NOT NULL, `filedata` mediumblob NOT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`regatta`), CONSTRAINT `rp_form_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `rp_log` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(11) NOT NULL, `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), CONSTRAINT `rp_log_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `sailor` ( `id` mediumint(9) NOT NULL AUTO_INCREMENT, `icsa_id` mediumint(9) DEFAULT NULL, `school` varchar(10) NOT NULL, `last_name` text NOT NULL, `first_name` text NOT NULL, `year` char(4) DEFAULT NULL, `role` enum('student','coach') NOT NULL DEFAULT 'student', `gender` enum('M','F') NOT NULL DEFAULT 'M', `regatta_added` int(11) DEFAULT NULL COMMENT 'For temp sailors, regatta when it was added.', PRIMARY KEY (`id`), UNIQUE KEY `icsa_id` (`icsa_id`), KEY `school` (`school`), KEY `regatta_added` (`regatta_added`), CONSTRAINT `sailor_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `sailor_ibfk_2` FOREIGN KEY (`regatta_added`) REFERENCES `regatta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=7234 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `sailor_update` ( `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=MyISAM DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `school` ( `id` varchar(10) NOT NULL, `name` varchar(50) NOT NULL, `nick_name` varchar(20) DEFAULT NULL, `conference` varchar(8) NOT NULL, `city` varchar(30) DEFAULT NULL, `state` varchar(30) DEFAULT NULL, `burgee` text, PRIMARY KEY (`id`), KEY `conference` (`conference`), CONSTRAINT `school_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `season` ( `id` int(11) NOT NULL AUTO_INCREMENT, `season` enum('fall','winter','spring','summer') DEFAULT 'fall', `start_date` date NOT NULL, `end_date` date NOT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `team` ( `id` int(7) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `school` varchar(10) DEFAULT NULL, `name` varchar(20) NOT NULL, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), KEY `school` (`school`), CONSTRAINT `team_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `team_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `team_name_prefs` ( `school` varchar(10) DEFAULT NULL, `name` varchar(20) DEFAULT NULL, `rank` int(5) DEFAULT NULL, KEY `school` (`school`), CONSTRAINT `team_name_prefs_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `temp_regatta` ( `regatta` int(5) NOT NULL, `original` int(5) NOT NULL, `expires` datetime NOT NULL, KEY `regatta` (`regatta`), KEY `original` (`original`), CONSTRAINT `temp_regatta_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`), CONSTRAINT `temp_regatta_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `temp_regatta_ibfk_3` FOREIGN KEY (`original`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; /*!40101 SET @saved_cs_client = @@character_set_client */; /*!40101 SET character_set_client = utf8 */; CREATE TABLE IF NOT EXISTS `venue` ( `id` int(4) NOT NULL AUTO_INCREMENT, `name` varchar(40) NOT NULL, `address` varchar(40) DEFAULT NULL, `city` varchar(20) DEFAULT NULL, `state` varchar(2) DEFAULT NULL, `zipcode` char(5) DEFAULT NULL, `weather_station_id` varchar(30) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1; /*!40101 SET character_set_client = @saved_cs_client */; SET FOREIGN_KEY_CHECKS=1;