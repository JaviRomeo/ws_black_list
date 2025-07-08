# Despliegue: Bloqueo de IPs

**WS Octopus Latam**

Este proyecto permite implementar un sistema automatizado para el bloqueo y reporte de direcciones IP malintencionadas dentro de los servicios web de Octopus Latam.

---

## 🔧 Instalación

### 1. Validar que existan en el `index.php` las siguientes variables

de lo contrario, agregarlas

```php
$currenttime= date('H:i:s'); // FECHA ACTUAL
$currentday  = date('N');  // DIA ACTUAL
$IpCplient = $aData['ip']; //IP DEL USUARIO
```

---

### 2. Validar que exista `log_ip_client` en `function saveDbLog()` archivo /lib/Function.php

De lo contrario, agregar esta línea

```php
log_ip_client = '" . $aData['ip'] . "',
```

---

### 3. Buscar las siguientes funciones en el archivo `function_blacklist.php`

### ⚠️Ojo⚠️

Estas son de ilustración, deberá copiarlas con su contenido y pegarlas en el archivo `/lib/function.php`

```php
#Validate shedules for service
function validate_schedules_ws($currenttime, $currentday, $service_id, $execID, $db) {/*function content*/ }

#Validate ip blacklisted
function check_ip_blacklist($IpCplient, $execID, $service_id, $db){ /*function content*/}

#Validate requests for ips
function validate_and_ban_ip($aData, $db, $service_id){ /*function content*/}

#Insert count by ip
function insert_attempts_counter_ip($sUrl, $aData, $code, $db, $service_id){ /*function content*/}

```

---

### 4. Agregar nueva varible `index_blacklist.php`

Esta variable debera ser agregada debajo de getService

```php
//Get service
$service_id = $service['service_id']; //Obligatoria para obtener el service_id
```

### ⚠️Ojo⚠️

Estas son de ilustración, deberá copiarlas con su contenido y pegarlas en el archivo `/index.php` debajo de getService

```php
//Get service
$service_id = $service['service_id']; //Obligatoria para obtener el service_id

if (!validate_schedules_ws($currenttime, $currentday, $service_id, $execID, $db)) { /*function content*/  }

if (!check_ip_blacklist($IpCplient, $execID, $service_id, $db)) { /*function content*/  }

if (!Validate_and_ban_ip($aData, $db, $service_id)) { /*function content*/  }

if (!insert_attempts_counter_ip($sUrl, $aData, $code, $db, $service_id)) { /*function content*/ }


```

### 5. Importar el archivo de configuración.  

Asegúrate de que `ws_deploy_blackLits.php` importe correctamente el archivo config.inc.php, ya que contiene la configuración necesaria para conectarse a la base de datos de WS.

**Ejemplo path:**

```php
 require_once "config/config.inc.php";
```

---

### 6. Ejecutar script de despliegue.  

Ejecuta el siguiente archivo php para iniciar el despliegue del sistema:

```php
php ws_deploy_blackLits.php
```

---

### 7. Ejecutar sh para instalación de librerías para envío y generación de reporteria

```sh
sh composer_require.sh
```

---

## Errores de instalación de librerías, por falta de extensiones⚠️

Ejecutar la instalación de estas extensiones en caso de que falle las instalación de alguna libreria.

error `gd extension`

```sh
yum install php-gd
```

---

error `zip extension`

```sh
yum install php-zip
```

---

error `mbstring extension`

```sh
yum install php-mbstring
```

---

### Programar reporteria automática

```
crontab -e
```

---

## 8. Programacion de reporteria 

```php
Cambiar ruta_ws a la carpeta correpondiente ubicada en 'www/var/ws...'
```
## Reporteria diaria 📨
#SEND EMAIL REPORTS BLACK IPS DIARIES

59 23 \* \* \* cd /var/www/`ruta_ws`/ &&  php /var/www/`ruta_ws`/process/send_email_blackIps_diaries.php >> /var/www/`ruta_ws`/logs/send_email_blackIps.log 2>&1

---

## Reporteria mensual 📨

#SEND EMAIL REPORTS BLACK IPS MONTHLY AND UNBANNED IPS

59 23 \* \* \* cd /var/www/`ruta_ws`/ &&  php /var/www/`ruta_ws`/process/send_email_blackIps_diaries.php >> /var/www/`ruta_ws`/logs/send_email_blackIps.log 2>&1

---

Equipo Octopus.
