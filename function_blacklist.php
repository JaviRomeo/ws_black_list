<?php
# -- Validate shedules for service
function validate_schedules_ws($currenttime, $currentday, $service_id, $execID, $db)
{

    $sql = "SELECT service_active_shedules 
			FROM ws_service 
			WHERE service_id = $service_id";
    if (!($qService = $db->query($sql))) {
        aLog("ERROR|EXEC_ID:$execID|FUNCTION:" . __FUNCTION__ . "|DESCRIPTION:" . $db->error);
        return false;
    }

    if ($qService->fetch_assoc()['service_active_shedules'] == 0) return true;

    $sql = "SELECT 1 FROM ws_schedules ws
		WHERE FIND_IN_SET('$currentday', ws.schedule_days) > 0
  		AND ws_service_id = '$service_id'
  		AND TIME('$currenttime') BETWEEN schedule_hour_start AND schedule_hour_end";

    if (!($qSchedule = $db->query($sql))) {
        aLog("ERROR|EXEC_ID:$execID|FUNCTION:" . __FUNCTION__ . "|DESCRIPTION:" . $db->error);
        return false;
    }

    return $qSchedule->num_rows > 0;
}

#Validate ip blacklisted
function check_ip_blacklist($IpCplient, $execID, $service_id, $db)
{
    $sql = "SELECT * FROM ws_blacklisted_ips wbi 
				WHERE  black_list_ipclient = '$IpCplient' 
				AND black_list_expired  >= NOW()";

    $qIP = $db->query($sql);
    if (!$qIP) {
        aLog("ERROR|EXEC_ID:$execID|FUNCTION:" . __FUNCTION__ . "|FIND_BY_IP|DESCRIPTION:" . $db->error);
        return true;
    }
    if ($qIP->num_rows > 0) return false;

    return true;
}

#Validate requests for ips
function validate_and_ban_ip($aData, $db, $service_id)
{
    $ipnew = $aData['ip'];

    $Validate_ip_white_list = "SELECT COUNT(*) AS unbanned FROM ws_white_list_ips WHERE white_ip = '$ipnew'";
    $checkiptresult = $db->query($Validate_ip_white_list);

    if (!$checkiptresult) {
        aLog("Error en whitelist query: " . $db->error);
        return false;
    }

    $whiteData = $checkiptresult->fetch_assoc();

    if ($whiteData['unbanned'] > 0) {
        return true;
    }

    $getRules  = "SELECT wr.rule_id, wr.rule_max_attempts, wr.rule_interval_minutes, wr.rule_ban_duration_minutes
                  FROM ws_service_rules wsr 
                  JOIN ws_rules wr ON wr.rule_id = wsr.rule_id 
                  WHERE wsr.service_id = $service_id";

    $rulesResult = $db->query($getRules);
    if (!$rulesResult) {
        aLog("Error al obtener reglas: " . $db->error);
        return false;
    }

    while ($row = $rulesResult->fetch_assoc()) {
        $ruleId = $row['rule_id'];
        $maxAttempts = $row['rule_max_attempts'];
        $intervalMinutes = $row['rule_interval_minutes'];
        $banDuration = $row['rule_ban_duration_minutes'];
        $daysBack = ceil($intervalMinutes / 1440);


        if ($intervalMinutes >= 1440) {
            $queryAttempts = "SELECT SUM(log_count) AS attempts
                  			FROM ws_log_counters_day
                  			WHERE log_ip_client = '$ipnew'
                    			AND service_id = $service_id
                    			AND log_count_ts >= CURDATE() - INTERVAL $daysBack DAY";
        } else {
            $queryAttempts = "SELECT COUNT(*) AS attempts
                            FROM ws_log
                            WHERE log_ip_client = '$ipnew'
                                AND log_service = $service_id
								/*AND log_return_code = 200*/
                                AND log_ts >= NOW() - INTERVAL $intervalMinutes MINUTE";
        }

        $attemptsResult = $db->query($queryAttempts);
        if (!$attemptsResult) {
            aLog("Error al obtener intentos: " . $db->error);
            return false;
        }

        $attemptsRow = $attemptsResult->fetch_assoc();
        $attempts = $attemptsRow ? (int)$attemptsRow['attempts'] : 0;

        if ($attempts >= $maxAttempts) {

            $checkBan = "SELECT COUNT(*) AS banned FROM ws_blacklisted_ips 
                         WHERE black_list_ipclient = '$ipnew' 
						 AND service_id = $service_id 
						 AND black_list_expired > NOW()";

            $banResult = $db->query($checkBan);
            if (!$banResult) {
                aLog("Error al verificar baneo: " . $db->error);
                return false;
            }

            $banRow = $banResult->fetch_assoc();
            if ($banRow['banned'] == 0) {
                $banUntil = date('Y-m-d H:i:s', strtotime("+$banDuration minutes"));

                $insertblackListed = "INSERT INTO ws_blacklisted_ips 
                                      (black_list_ts, black_list_ipclient, black_list_expired, rule_id, service_id)
                                      VALUES (NOW(), '$ipnew', '$banUntil', $ruleId, $service_id)";

                if (!$db->query($insertblackListed)) {
                    aLog("Error al insertar en blacklist: " . $db->error);
                    return false;
                }
            }
            return false;
        }
    }

    return true;
}

#Insert count by ip
function insert_attempts_counter_ip($sUrl, $aData, $code, $db, $service_id)
{

    $date = date('Y-m-d');
    $ipnew = $aData['ip'];

    $validateip = "SELECT log_count_id FROM ws_log_counters_day 
				   WHERE log_ip_client = '$ipnew' 
				   AND log_count_ts = '$date' 
				   AND service_id = '$service_id'";

    $qIP = $db->query($validateip);

    if ($qIP->num_rows === 0) {
        $INSERT = "INSERT INTO ws_log_counters_day 
				   SET log_count_ts = '$date', 
					   log_ip_client = '$ipnew', 
					   log_count = 1, 
					   service_id = '$service_id'";
        $db->query($INSERT);
    } else {
        $row = $qIP->fetch_assoc();
        $id = intval($row['log_count_id']);
        $UPDATE = "UPDATE ws_log_counters_day 
				   SET log_count = log_count + 1 
				   WHERE log_count_id = $id";
        $db->query($UPDATE);
    }

    if (!$db) {
        $code   = "E01";
        $status = "ERROR";
        $desc   = "WRITING_LOG";
        $data['EXECID']  = $execID;
        $data['DBERROR'] = $db->error;
        $data['REQUEST'] = $sUrl;
        $response = getResponse($code, $status, $desc, $data);
        $sDebugMsg = "EXEC_ID:$execID|$status|$desc|RESPONSE:$response|query:$sql";
        aLog($sDebugMsg);
        $return = false;
    } else {
        $return = true;
    }
    return $return;
}
