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
setlocale(LC_TIME, 'es_ES.UTF-8');
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
$dotenv = Dotenv::createImmutable(__DIR__);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet2 = $spreadsheet->createSheet();
$dotenv->load();

$currenttime = date("Y-m-d H:i:s");
$logfile = './logs/send_email_blackIps_monthly.log'; // Add route for logs
$today = new DateTime();
$month = strtoupper(strftime('%B %Y'));
$send_emails_to = explode(',', $_ENV['EMAILS_SEND_TO']); // Get the emails from the environment variable
$send_emails_cc = explode(',', $_ENV['EMAILS_SEND_CC']); // Get the emails cc from the environment variable
$route_ws_octopus = ($_ENV['ROUTE_WS_OCTOPUS']); // route ws octopus for reports 
$campaign = ($_ENV['CAMPAIGN']); // Get the campaign from the environment variable
$sheet->setTitle('Hoja 1'); // Set the title of the first sheet
$sheet2->setTitle('Hoja 2'); // Set the title of the second sheet
$subject = "REPORTE MENSUAL DE IPS BANEADAS  " . $campaign . " COLOMBIA " . $month . "";
$filename = "" . $subject . ".xlsx"; // name of the file to be created
$path = "" . $route_ws_octopus . "$filename"; // route where the file will be saved
$email = new Email();
$query_ws_ban_logs = "SELECT *FROM ws_ban_logs wbl WHERE 1 AND wbl.ban_start >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                      AND wbl.ban_start <  DATE_FORMAT(DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), '%Y-%m-01')";


function validateDate($logfile, $today, $month)
{

    try {
        $isLastDayOfMonth  = (int)$today->format('d') === (int)$today->format('t');
        if ($isLastDayOfMonth) {
            file_put_contents($logfile, "✅ -> Hoy " . date("Y-m-d") . " Es el último día del mes " . $month . "  -> Ejecutando reporte mensual a las: " . date('H:i:s') . "  \n " . PHP_EOL, FILE_APPEND);
            return true;
        } else {
            file_put_contents($logfile, "🚫 -> Hoy " . date("Y-m-d") . " No es el último día del mes -> Ejecutado a las: " . date('H:i:s') . " \n " . PHP_EOL, FILE_APPEND);
            exit;
        }
    } catch (Exception $e) {
        file_put_contents($logfile, "Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}
validateDate($logfile, $today, $month);

function getBanneIps($send_emails_to, $send_emails_cc, $subject, $month, $db, $query_ws_ban_logs, $logfile)
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
                ->text('Para el mes ' . $month . ', no se detectaron IPs maliciosas ni se realizaron baneos. El sistema reporta un comportamiento normal.');
            $mailer->send($email);
            file_put_contents($logfile, "🚫 -> No encontradas Ips Baneadas para " . $month . " Se envio correo a las las: " . date('H:i:s') . " -> 📨  \n " . PHP_EOL, FILE_APPEND);
            exit;
        }
    } catch (\Throwable $th) {
        echo "Error al conectar a BD. Error : {$th->getMessage()}\n";
        exit;
    }
}
$listips = getBanneIps($send_emails_to, $send_emails_cc, $subject, $month, $db, $query_ws_ban_logs, $logfile);

function getRequetsByIps($db, $listips)
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
        WHERE wl.log_ts >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
	    AND wl.log_ip_client IN ($arraylistip)
	    AND wl.log_ts < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')";
        $resultrequets = $db->query($telsIPS);

        $infoips = "SELECT
	    wbl.ip_client AS Ip_cliente,
	    wbl.ban_start AS Fecha_ini_ban,
	    wbl.ban_end AS Fecha_fin_ban
        FROM ws_ban_logs wbl
        WHERE wbl.ip_client IN ($arraylistip) 
	    AND wbl.ban_start >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
        AND wbl.ban_start < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')";
        $resultipsinfo = $db->query($infoips);

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
list($inforesult, $infoipsresult, $resultrequets, $query) = getRequetsByIps($db, $listips);

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

function sendEmail($path, $filename, $email, $send_emails_to, $send_emails_cc, $subject, $month, $logfile)
{
    try {
        if (!file_exists($path)) {
            echo "El archivo no existe. Verifique la ruta: $path\n";
            exit;
        } else {
            echo "Se genero y se encontro Excel. Procediendo a enviar el correo.\n";
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

            file_put_contents($logfile, "✅ -> 📨 Se envio reporte mensual " .$month. " de Ips Baneadas  a las: " . date('H:i:s') . "  \n " . PHP_EOL, FILE_APPEND);
        }
    } catch (\Throwable $th) {
        echo "Error al enviar el correo. Error : {$th->getMessage()}\n";
        exit;
    }
}
sendEmail($path, $filename, $email, $send_emails_to, $send_emails_cc, $subject, $month, $logfile);


function unbanned($db, $logfile, $currenttime)
{
    try {

        $db->query("DELETE FROM ws_blacklisted_ips WHERE black_list_expired <= NOW()");

        if ($db->affected_rows > 0) {
            file_put_contents($logfile, "✅ -> ==unbanned== Se eliminaron  " .  $db->affected_rows . " Ips Banneadas menores a " . $currenttime . " Ejecutado a las  " . date('H:i:s') . " \n " . PHP_EOL, FILE_APPEND);
        } else {
            echo "No hay Ips para eliminar ";
            file_put_contents($logfile, "🚫 -> No hay ips para para desbanear ejecutado " . $currenttime . " \n " . PHP_EOL, FILE_APPEND);
            exit;
        }
    } catch (\Exception $e) {
        echo "Error en la consulta: " . $e->getMessage();
        exit;
    } finally {
        $db->close();
    }
}
unbanned($db, $logfile, $currenttime);
