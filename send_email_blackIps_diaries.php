<?php
require '/var/www/ws-octopus/vendor/autoload.php';
require_once '/var/www/ws-octopus/config/config.inc.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Dotenv\Dotenv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
$dotenv = Dotenv::createImmutable(__DIR__);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet2 = $spreadsheet->createSheet();
$dotenv->load();

$date_today = date('Y-m-d');
$currenttime = date("Y-m-d H:i:s");
$logfile = './logs/send_email_blackIps_diaries.log'; // Add route for logs
$start_date = $date_today . ' 00:00:00';
$end_date   = $date_today . ' 23:59:59';
$send_emails_to = explode(',', $_ENV['EMAILS_SEND_TO']); // Get the emails from the environment variable
$send_emails_cc = explode(',', $_ENV['EMAILS_SEND_CC']); // Get the emails cc from the environment variable
$route_ws_octopus = ($_ENV['ROUTE_WS_OCTOPUS']); // route ws octopus for reports 
$campaign = ($_ENV['CAMPAIGN']); // Get the campaign from the environment variable
$sheet->setTitle('Hoja 1'); // Set the title of the first sheet
$sheet2->setTitle('Hoja 2'); // Set the title of the second sheet
$subject = "REPORTE DIARIO DE IPS BANEADAS  " . $campaign . " COLOMBIA " . $date_today . "";
$filename = "" . $subject . ".xlsx"; // name of the file to be created
$path = "" . $route_ws_octopus . "$filename"; // route where the file will be saved
$email = new Email();
$query_ws_ban_logs = "SELECT * FROM ws_ban_logs wbl WHERE 1 AND wbl.ban_start BETWEEN  '$start_date' AND '$end_date'";


function getBanneIps($send_emails_to, $send_emails_cc, $subject, $date_today, $db, $currenttime, $query_ws_ban_logs, $logfile)
{

    try {

        $result = $db->query($query_ws_ban_logs);
        $listips = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $listips[] = $row['ip_client'];
            }

            return $listips;
        } else {

            $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from('noresponder.octopus@gmail.com')
                ->to(...$send_emails_to)
                ->cc(...$send_emails_cc)
                ->subject($subject)
                ->text('Para el día ' . $date_today . ', no se detectaron IPs maliciosas ni se realizaron baneos. El sistema reporta un comportamiento normal.');
            $mailer->send($email);
            file_put_contents($logfile, "✅ -> 📨 No se encontraron Ips Baneadas.. Se envio correo informativo " . $date_today . " a las: " . date('H:i:s') . "  \n " . PHP_EOL, FILE_APPEND);
            exit;
        }
    } catch (\Throwable $th) {
        echo "Error al conectar a BD. Error : {$th->getMessage()}\n";
        exit;
    }
}
$listips = getBanneIps($send_emails_to, $send_emails_cc, $subject, $date_today, $db, $currenttime, $query_ws_ban_logs, $logfile);

function getRequetsByIps($db, $start_date, $end_date, $listips)
{

    try {

        if (empty($listips)) {
            echo "No hay IPs para procesar.\n";
            exit;
        }

        // Convert the list of IPs to a comma-separated string for the SQL query
        $arraylistip = implode(',', array_map(function ($ip) {
            return "'$ip'";
        }, $listips));

        $telsIPS = "SELECT
	    wl.log_ip_client AS Ip_cliente, wl.log_telephone 
        AS Telefono, 
	    wl.log_ts AS Fecha,
        wl.log_returned  AS Ingreso_a_octopus
        FROM ws_log wl
        WHERE wl.log_ip_client IN ($arraylistip) 
        AND wl.log_ts 
        BETWEEN '$start_date' AND '$end_date' 
        ORDER BY wl.log_ts ASC";
        $resultrequets = $db->query($telsIPS);

        $infoips = "SELECT
	    wbl.ip_client AS Ip_cliente,
	    wbl.ban_start AS Fecha_ini_ban,
	    wbl.ban_end AS Fecha_fin_ban
        FROM
	    ws_ban_logs wbl
        WHERE
	    wbl.ip_client IN ($arraylistip) 
	    AND wbl.ban_start BETWEEN '$start_date' AND '$end_date'";

        $resultipsinfo = $db->query($infoips);
        $inforesult = [];
        $infoipsresult = [];

        if ($resultrequets && $resultrequets->num_rows > 0) {
            while ($row = $resultrequets->fetch_assoc()) {

                $jsonData = json_decode($row['Ingreso_a_octopus'], true);
                $descValue = isset($jsonData['desc']) ? $jsonData['desc'] : null;
                $inforesult[] = [
                    'Fecha' => $row['Fecha'],
                    'Telefono' => $row['Telefono'],
                    'Ip_cliente' => $row['Ip_cliente'],
                    'Desc' => $descValue,
                ];
            }
        }

        if ($resultipsinfo && $resultipsinfo->num_rows > 0) {
            while ($row = $resultipsinfo->fetch_assoc()) {
                $infoipsresult[] = [
                    'Ip_cliente' => $row['Ip_cliente'],
                    'Fecha_ini_ban' => $row['Fecha_ini_ban'],
                    'Fecha_fin_ban' => $row['Fecha_fin_ban'],
                ];
            }
        }

        return [$inforesult, $infoipsresult, $resultrequets, $telsIPS];
    } catch (\Throwable $th) {
        echo "Error al conectar a BD. Error : {$th->getMessage()}\n";
        exit;
    }
}
list($inforesult, $infoipsresult, $resultrequets, $query) = getRequetsByIps($db, $start_date, $end_date, $listips);


function arm_excel($path, $db, $resultrequets, $infoipsresult, $query, $sheet, $sheet2, $inforesult, $spreadsheet)
{

    try {

        if (!$resultrequets or !$query) {
            die("Error en la consulta: " . $db->error);
        }
        $sheet->setCellValue('A1', 'Fecha');
        $sheet->setCellValue('B1', 'Teléfono');
        $sheet->setCellValue('C1', 'Ip_cliente');
        $sheet->setCellValue('D1', 'Ingreso a Octopus');

        $sheet2->setCellValue('A1', 'Ip_cliente');
        $sheet2->setCellValue('B1', 'Fecha_ini_ban');
        $sheet2->setCellValue('C1', 'Fecha_fin_ban');


        $fila = 2;
        foreach ($inforesult as $filaDatos) {
            $sheet->setCellValue('A' . $fila, $filaDatos['Fecha']);
            $sheet->setCellValue('B' . $fila, $filaDatos['Telefono']);
            $sheet->setCellValue('C' . $fila, $filaDatos['Ip_cliente']);
            $sheet->setCellValue('D' . $fila, $filaDatos['Desc']);
            $fila++;
        }

        $fila2 = 2;
        foreach ($infoipsresult as $filaDatos2) {
            $sheet2->setCellValue('A' . $fila2, $filaDatos2['Ip_cliente']);
            $sheet2->setCellValue('B' . $fila2, $filaDatos2['Fecha_ini_ban']);
            $sheet2->setCellValue('C' . $fila2, $filaDatos2['Fecha_fin_ban']);
            $fila2++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    } catch (\Throwable $th) {
        echo "Error al generar el archivo Excel. Error : {$th->getMessage()}\n";
        exit;
    }
}
arm_excel($path, $db, $resultrequets, $infoipsresult, $query, $sheet, $sheet2, $inforesult, $spreadsheet);

function sendEmail($path, $filename, $email, $send_emails_to, $send_emails_cc, $subject, $logfile, $date_today)
{
    try {
        if (!file_exists($path)) {
            file_put_contents($logfile, "⚠️ -> El archivo no existe. Verifique la ruta: $path\n" . PHP_EOL, FILE_APPEND);
            exit;
        } else {
            file_put_contents($logfile, "✅ -> El reporte Excel Ips Diarias existe. Procediendo a enviar el correo.\n" . PHP_EOL, FILE_APPEND);
            $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from('noresponder.octopus@gmail.com')
                ->to(...$send_emails_to)
                ->cc(...$send_emails_cc)
                ->subject($subject)
                ->text('SE ABJUNTA ' . $subject . '')
                ->attachFromPath($path, $filename);

            $mailer->send($email);

            /*if (file_exists($path)) {
                unlink($path);
            }*/

            file_put_contents($logfile, "✅ -> 📨 Se envio reporte diario de Ips Baneadas hoy " . $date_today . " a las: " . date('H:i:s') . "  \n " . PHP_EOL, FILE_APPEND);
        }
    } catch (\Throwable $th) {
        echo "Error al enviar el correo. Error : {$th->getMessage()}\n";
        exit;
    }
}
sendEmail($path, $filename, $email, $send_emails_to, $send_emails_cc, $subject, $logfile, $date_today);
