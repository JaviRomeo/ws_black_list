<?php

if (!validate_schedules_ws($currenttime, $currentday, $service_id, $execID, $db)) {
    $code   = 408;
    $status = "ERROR";
    $desc   = "OUT_OF_SCHEDULE_WS";
    $data['ip'] = $userIP;
    $data['telefono'] = $telefono;
    $data['ID']      = '';
    $data['execID']  = $execID;
    $data['Hora']  = $currenttime;
    $data['Dia']  = $currentday;
    $data['service']  = $serviceName;
    $response = getResponse($code, $status, $desc, $data);
    $sDebugMsg = "EXEC_ID:$execID|$status|$desc|RESPONSE:$response|URL:$sURL";
    # -- Save DB LOG
    saveDbLog($sURL, $data['ID'], $aData, $code, $response, $t_proceso, $service, $isDuplicado, $clientIP, $db);
    aLog($sDebugMsg);
    exit($response);
}

if (!check_ip_blacklist($IpCplient, $execID, $service_id, $db)) {
    $code   = 410;
    $status = "ERROR";
    $desc   = "IP_BANNED";
    $data['ip'] = $userIP;
    $data['telefono'] = $telefono;
    $data['ID']      = '';
    $data['execID']  = $execID;
    $data['service']  = $serviceName;
    $response = getResponse($code, $status, $desc, $data);
    $sDebugMsg = "EXEC_ID:$execID|$status|$desc|RESPONSE:$response|URL:$sURL";
    # -- Save DB LOG
    saveDbLog($sURL, $data['ID'], $aData, $code, $response, $t_proceso, $service, $isDuplicado, $clientIP, $db);
    aLog($sDebugMsg);
    exit($response);
}


if (!Validate_and_ban_ip($aData, $db, $service_id)) {
    $code   = 411;
    $status = "ERROR";
    $desc   = "IP_HAS_BANNED";
    $data['ip'] = $userIP;
    $data['telefono'] = $telefono;
    $data['ID']      = '';
    $data['execID']  = $execID;
    $data['service']  = $serviceName;
    $response = getResponse($code, $status, $desc, $data);
    $sDebugMsg = "EXEC_ID:$execID|$status|$desc|RESPONSE:$response|URL:$sURL";
    # -- Save DB LOG
    saveDbLog($sURL, $data['ID'], $aData, $code, $response, $t_proceso, $service, $isDuplicado, $clientIP, $db);
    aLog($sDebugMsg);
    exit($response);
}

if (!insert_attempts_counter_ip($sUrl, $aData, $code, $db, $service_id)) {
    $code   = 409;
    $status = "ERROR";
    $desc   = "FATAL_ERROR_INSERT_COUNT_IP";
    $data['ip'] = $userIP;
    $data['telefono'] = $telefono;
    $data['ID']      = '';
    $data['execID']  = $execID;
    $data['service']  = $serviceName;
    $response = getResponse($code, $status, $desc, $data);
    $sDebugMsg = "EXEC_ID:$execID|$status|$desc|RESPONSE:$response|URL:$sURL";
    # -- Save DB LOG
    saveDbLog($sURL, $data['ID'], $aData, $code, $response, $t_proceso, $service, $isDuplicado, $clientIP, $db);
    aLog($sDebugMsg);
    exit($response);
}
