<?php
require_once "config.inc.php"; //IMPORT FILE CONFIG.INC.PHP CONFIGURATION
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

try {
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
} catch (Exception $e) {
    echo "🚫 Database connection error: {$e->getMessage()}\n";
    exit;
}


function createDatabasesBlackList($db, $nametable, $sql)
{

    try {
        $check = $db->query("SHOW TABLES LIKE '$nametable'");
        if ($check->num_rows > 0) {
            echo "⚠️  table '$nametable' It already exists. It was not created.\n";
        } else {
            if ($db->query($sql)) {
                echo "✅ Table '$nametable' created correctly.\n";
            } else {
                echo "⚠️ Error creating table'$nametable': " . $db->error . "\n";
            }
        }
    } catch (\Throwable $th) {
        echo "🚫 General error with the table'$nametable': {$th->getMessage()}\n";
    }
}

$tables = [
    'ws_rules' => "CREATE TABLE `ws_rules` (
                                 `rule_id` int(11) NOT NULL AUTO_INCREMENT,
                                 `rule_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                 `rule_description` varchar(255) NOT NULL,
                                 `rule_max_attempts` int(11) NOT NULL,
                                 `rule_interval_minutes` int(11) NOT NULL,
                                 `rule_ban_duration_minutes` bigint(20) NOT NULL,
                                 `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                                 `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                  PRIMARY KEY (`rule_id`)) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_service_rules' => "CREATE TABLE `ws_service_rules` (
                                 `services_rules_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                                 `service_id` int(10) unsigned NOT NULL,
                                 `rule_id` int(10) NOT NULL,
                                 PRIMARY KEY (`services_rules_id`),
                                 KEY `service_id` (`service_id`),                             
                                 KEY `rule_id` (`rule_id`),
                                 CONSTRAINT `service_rules_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `ws_service` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                 CONSTRAINT `service_rules_ibfk_2` FOREIGN KEY (`rule_id`) REFERENCES `ws_rules` (`rule_id`) ON DELETE CASCADE ON UPDATE CASCADE) 
                                 ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_blacklisted_ips' => "CREATE TABLE `ws_blacklisted_ips` (
                                  `black_list_id` int(11) NOT NULL AUTO_INCREMENT,
                                  `black_list_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                  `black_list_ipclient` varchar(100) NOT NULL,
                                  `black_list_expired` datetime NOT NULL,
                                  `rule_id` int(11) NOT NULL,
                                  `service_id` int(11) DEFAULT NULL,
                                  PRIMARY KEY (`black_list_id`),
                                  KEY `rule_id` (`rule_id`),
                                  CONSTRAINT `blacklisted_ips_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `ws_rules` (`rule_id`)) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_ban_logs' => "CREATE TABLE `ws_ban_logs` (
                                 `ban_id` int(11) NOT NULL AUTO_INCREMENT,
                                 `ip_client` varchar(45) NOT NULL,
                                 `rule_id` int(11) NOT NULL,
                                 `ban_start` datetime NOT NULL,
                                 `ban_end` datetime NOT NULL,
                                 `ban_reason` text,
                                 PRIMARY KEY (`ban_id`),
                                 KEY `rule_id` (`rule_id`),
                                 CONSTRAINT `ban_logs_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `ws_rules` (`rule_id`)) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_log_counters_day' => "CREATE TABLE `ws_log_counters_day` (
                                 `log_count_id` int(11) NOT NULL AUTO_INCREMENT,
                                 `log_count_ts` date NOT NULL,
                                 `log_ip_client` varchar(45) DEFAULT NULL,
                                 `log_count` int(11) DEFAULT '0',
                                 `service_id` int(10) unsigned NOT NULL,
                                 PRIMARY KEY (`log_count_id`),
                                 KEY `ws_log_counters_ws_service_FK` (`service_id`),
                                 CONSTRAINT `ws_log_counters_ws_service_FK` FOREIGN KEY (`service_id`) 
                                 REFERENCES `ws_service` (`service_id`)) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_white_list_ips' => "CREATE TABLE `ws_white_list_ips` (
                                 `white_id` int(11) NOT NULL AUTO_INCREMENT,
                                 `white_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                 `white_ip` varchar(45) NOT NULL,
                                 `white_reason` varchar(255) DEFAULT NULL,
                                 PRIMARY KEY (`white_id`),
                                 UNIQUE KEY `white_ip` (`white_ip`),
                                 UNIQUE KEY `ws_white_list_ips_unique` (`white_ip`)) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

    'ws_schedules' => "CREATE TABLE `ws_schedules` (
                                 `schedule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                                 `ws_service_id` int(10) unsigned DEFAULT NULL,
                                 `schedule_days` varchar(100) DEFAULT NULL,
                                 `schedule_hour_start` time NOT NULL,
                                 `schedule_hour_end` time NOT NULL,
                                 `created_up` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                 `updated_up` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                 PRIMARY KEY (`schedule_id`),
                                 KEY `fk_ws_schedules_ws_service` (`ws_service_id`),
                                 CONSTRAINT `fk_ws_schedules_ws_service` FOREIGN KEY (`ws_service_id`) REFERENCES `ws_service` (`service_id`) 
                                 ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;"
];


$trigger_ban_logs = ("CREATE TRIGGER Insert_ipblocked_ws_ban_logs
AFTER INSERT ON ws_blacklisted_ips
FOR EACH ROW
BEGIN
    DECLARE v_description TEXT;
    DECLARE v_max_attempts INT;
    DECLARE v_interval_minutes INT;

    SELECT 
        rule_description,
        rule_max_attempts,
        rule_interval_minutes
    INTO
        v_description,
        v_max_attempts,
        v_interval_minutes
    FROM
        ws_rules
    WHERE
        rule_id = NEW.rule_id;

    INSERT INTO ws_ban_logs (
        ip_client,
        rule_id,
        ban_start,
        ban_end,
        ban_reason
    ) VALUES (
        NEW.black_list_ipclient,
        NEW.rule_id,
        NEW.black_list_ts,
        NEW.black_list_expired,
        CONCAT('IP Baneada por superar ', v_max_attempts, ' intentos en un intervalo de ', v_interval_minutes, ' minutos')
    );
END;");


try {
    foreach ($tables as $nametable => $sql) {
        createDatabasesBlackList($db, $nametable, $sql);
    }
} catch (\Throwable $th) {
    echo "Error creating tables: {$th->getMessage()}\n";
    exit;
}

try {
    if ($db->query($trigger_ban_logs)) {
        echo "✅ Trigger 'Insert_ipblocked_ws_ban_logs' created successfully.\n";
    } else {
        echo "⚠️  Error creating trigger 'Insert_ipblocked_ws_ban_logs': " . $db->error . "\n";
    }
} catch (\Throwable $th) {
    echo "🚫 Error creating log trigger: {$th->getMessage()}\n";
    exit;
}

try {

    $new_column = "ALTER TABLE db_webservices.ws_service ADD service_active_shedules INT DEFAULT 0 NOT NULL";
    $new_column2 = "ALTER TABLE db_webservices.ws_log  ADD log_ip_client varchar(100) DEFAULT 'NULL'";
    if ($db->query("SHOW COLUMNS FROM db_webservices.ws_service LIKE 'service_active_shedules'")->num_rows > 0) {
        echo "⚠️  existing service_active_shedules column" . $db->error . "\n";
    } else {
        $db->query($new_column);
        echo "✅ column service_active_shedules add correctly";
    }
    if ($db->query("SHOW COLUMNS FROM db_webservices.ws_log LIKE 'log_ip_client'")->num_rows > 0) {
        echo "⚠️  existing log_ip_client column  " . $db->error . "\n";
    } else {
        $db->query($new_column2);
        echo "✅ column log_ip_client add correctly";
    }
} catch (\Throwable $th) {
    echo "🚫 Error check columns : {$th->getMessage()}\n";
    exit;
}

echo "=== Validar carpetas /process\n /logs \n /reports \n/  ===\n";


function generateFolders()
{

    try {

        $carpetas = [
            $folderProcess =    "process",
            $folderLogs = "logs",
            $folderReporst =  "reports"
        ];


        $carpetasNoExistentes = [];

        foreach ($carpetas as $carpeta) {
            if (!file_exists($carpeta)) {
                if (mkdir($carpeta, 0700, true)) {
                    echo " La carpeta : " . strtoupper($carpeta) . "  se creo correctamente \n";

                } else {
                    echo "No se pudo crear la " . $carpeta . "";
                }
                $carpetasNoExistentes[] = $carpeta;
            } else {
                echo "la carpeta : " . strtoupper($carpeta) . " : ya existe .\n ";
            }
        }
    } catch (\Throwable $th) {
        echo "error : " . $th->getMessage();
    }
}
generateFolders();




function ask($question, $default = '')
{

    $defaultText = $default !== '' ? " [$default]" : '';
    echo $question . $defaultText . ": ";
    $input = trim(fgets(STDIN));
    return $input !== '' ? $input : $default;

    
}
echo "=== Generar Archivo .env ===\n";

$envVars = [
    'MAILER_DSN'        => ask('Correo del remitente', 'smtp://noresponder.octopus@gmail.com:nqjpbwjrrmbujhlb@smtp.gmail.com:587'),
    'EMAILS_SEND_TO'    => ask('Correo para envio de pruebas (default)', 'soporte.octopus@softtek.com'),
    'EMAILS_SEND_CC'    => ask('Correo para envio de cc pruebas (default)', 'soporte.octopus@softtek.com'),
    'CAMPAIGN'          => ask('campana', 'DEFAULT'),
    'ROUTE_WS_OCTOPUS'  => ask('ruta para guardar reportes', './reports/'),
];

$envContent = "";
foreach ($envVars as $key => $value) {
    $envContent .= "$key=$value\n";
}

$save_env_ = './process/.env';
file_put_contents($save_env_, $envContent);
echo "\n✅ Archivo .env creado con éxito:\n\n$envContent "."  se guardo en  ".$save_env_.".\n";

function move_files () {
    try {
 
        $carpeta_destino = './process/';
        $files = [
            $send_email_blackIps_diaries = 'send_email_blackIps_diaries.php',
            $send_email_blackIps_monthly = 'send_email_blackIps_monthly.php'
        ];


        foreach ($files as $file) {
            $destine = $carpeta_destino. $file;
            if (file_exists($file)) {
                echo " el archivo  ".$file."  existe";
                if (rename($file,$destine)) {
                    echo "se movio";
                }
                else {
                    echo "no se movio";
                }
            }
            else {
                echo " el archivo  ".$file." NO existe";
            }

        }

    } catch (\Throwable $th) {
        //throw $th;
    }

}

move_files ();


function clear_filedeploy () {

    try {

    $deletefile = 'ws_deploy_blackLits.php';
        
    if (file_exists($deletefile)) {
        echo " si existe ";
        unlink($deletefile);
        echo "Se elimino ".$deletefile."";
    }
    else{
        echo " no existe ";
    }
    } catch (\Throwable $th) {
        echo "error : " . $th->getMessage();
    }

}
clear_filedeploy (); 


