# Despliegue: Bloqueo de IPs 
**WS Octopus Latam**

Este proyecto permite implementar un sistema automatizado para el bloqueo y reporte de direcciones IP malintencionadas dentro de los servicios web de Octopus Latam.

---

## 🔧 Instalación ##

### . Validar que exista en el index.php las siguientes varibles

de lo contrario agregarlas
```php
$currenttime= date('H:i:s'); // FECHA ACTUAL
$currentday  = date('N');  // DIA ACTUAL
$IpCplient = $aData['ip']; //IP DEL USUARIO
```


### . Validar que exista `log_ip_client` en `function saveDbLog()` archivo Function.php
De lo contrario agregar esta linea
```php
log_ip_client = '" . $aData['ip'] . "',
```


### 1. Importar el archivo de configuración  

Importa el archivo `config.inc.php` dentro del script `ws_deploy_blackLits.php`. Asegúrate de contar con el archivo correcto para conectarte a la base de datos de WS.

**Ejemplo:**
```php
require_once "config/config.inc.php"; 
```
---

### 2. Ejecutar script de despliegue  
Ejecuta el siguiente archivo php para iniciar el despliegue del sistema:  

```php
php ws_deploy_blackLits.php
```
---

### 3. Ejecutar sh para instalacion de librerias para envio y generacion de reporteria

```sh
sh composer_require.sh
```
---
## Erores de instalacion de librerias, por falta de extenciones⚠️

error `gd extension`

```
yum install php-gd 
```
---

error `zip extension`

```
sudo yum install php-zip
```
---

error `mbstring extension`

```
sudo yum install php-mbstring
```
---

### Programar reporteria automatica

```
crontab -e
```

----
## Reporteria diaria 📨

#SEND EMAIL REPORTS BLACK IPS DIARIES


59 23 * * * cd /var/www/ `ruta ws`/ &&  php /var/www/ `ruta ws`/process/send_email_blackIps_diaries.php >> /var/www/ `ruta ws`/logs/send_email_blackIps.log 2>&1


----
## Reporteria mensual📨

#SEND EMAIL REPORTS BLACK IPS MONTHLY AND UNBANNED IPS


59 23 * * * cd /var/www/ `ruta ws`/ &&  php /var/www/ `ruta ws`/process/send_email_blackIps_diaries.php >> /var/www/ `ruta ws`/logs/send_email_blackIps.log 2>&1

---

Equipo Octopus. 