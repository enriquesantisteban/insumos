# Guía de despliegue: sitio "insumos" en servidor Nginx

Dominio final: TU-DOMINIO.COM (a definir al comprarlo), en la raíz del dominio (sin subcarpetas /insumos/).

Cómo leer esta guía: cada bloque de comandos especifica si se ejecuta en tu ordenador (PowerShell/CMD o navegador) o dentro del servidor (consola iDRAC / SSH). 
Esta guía recopila el flujo depurado y limpio, omitiendo bloqueos de red locales y errores intermedios de sintaxis.

## PASO 0. AJUSTES DE CONSTANTES DE RUTA

En el proyecto, en el archivo i18n.php, la constante debe quedar apuntando a la raíz vacía:

``` php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

```

Con esto, todas las URLs generadas por `__url()`, `__lang_switch_url()` y `__asset_url()` dejan de llevar el prefijo `/insumos` y pasan a ser del tipo `/es/`, `/es/fabricante/kenogard`, etc.,directamente desde la raíz. No hace falta tocar nada más en el código: `nav.php`, `fabricante.php`, `producto.php` ya usan esas funciones, así que heredan el cambio automáticamente.

## PASO 1. AVERIGUAR LA IP DEL SISTEMA PARA TRABAJAR POR SSH (RECOMENDADO)

1. Abre la Consola virtual y haz login con tu usuario.

2. Ejecuta este comando para ver la IP que tiene asignada el sistema operativo:

```bash

ip a

```

3. Busca una interfaz de red (como eno1, ens... o eth0) donde aparezca algo como inet 192.168.1.X/24. Esa IP es la del sistema operativo (que puede ser distinta a la .10 del iDRAC).
4. Ver qué puertos de red ya están en uso (especialmente si Apache o Nginx ya están usando el puerto 80):

```bash
    
sudo ss -tulpn | grep -E ':80|:443|:3306|:8080'

```

5. Ver versión PHP instalada

```bash

php -v

```

6. Ver qué sitios web tiene configurados Nginx ahora mismo:

```bash

sudo ls -l /etc/nginx/sites-enabled/

```

## PASO 2. INSTALAR PHP-FPM Y SOPORTE MySQL

```bash

sudo apt update
sudo apt install php-fpm php-mysql php-mbstring php-xml php-curl -y

```

Cuando termine, averigua la versión exacta de PHP que se instaló escribiendo: 

```bash

php -v

```

## PASO 3. CREAR EL DIRECTORIO PARA LA WEB

Vamos a crear la carpeta donde irá el proyecto:

```bash

sudo mkdir -p /var/www/regenerative-agro-platform
sudo chown -R enrique:www-data /var/www/regenerative-agro-platform
sudo chmod -R 775 /var/www/regenerative-agro-platform

```

## PASO 4. INSTALAR BASE DE DATOS

Ahora vamos a comprobar si el servidor ya tiene un motor de base de datos MySQL o MariaDB instalado y activo.

```bash

sudo systemctl status mysql

```

Si aparece este mensaje (Unit mysql .service could not be found) significa que no está instalado. 

Vamos a instalar MariaDB (que es 100% compatible con MySQL, la misma que usa PHPMyAdmin, y es más ligera y recomendada en Linux).

Ejecuta este comando en la terminal:

```bash

sudo apt install mariadb-server -y

```

Ahora vamos a asegurarnos de que el servicio esté iniciado y configurado para arrancar automáticamente si el servidor se reinicia.

Ejecuta este comando:

```bash

sudo systemctl enable --now mariadb

```

## PASO 5. CREAR BASE DE DATOS

1. Ahora vamos a entrar en la consola de MariaDB para crear la base de datos y un usuario exclusivo con permisos para la web.

Ejecuta este comando:

```bash

sudo mysql -u root

```

2. Ahora, dentro de MariaDB [(none)]>, escribe este primer comando para crear la base de datos (con soporte completo para acentos y caracteres especiales):

```SQL

CREATE DATABASE insumos CHARACTER SET utf8;

```

3. Ahora se crea un usuario para la aplicación web y se le asigna una contraseña segura.

Elige una contraseña segura (reemplaza TuPasswordSeguro123! por la que quieras) y ejecuta:

```SQL

CREATE USER 'enrique_insumos'@'localhost' IDENTIFIED BY 'TuPasswordSeguro123!';

```

::: Apunta esa contraseña, ya que será la que pondrás luego en define('DB_PASS', '...'); dentro de tu config.php.

4. Ahora vamos a darle a enrique_insumos control total sobre la base de datos insumos.

Ejecuta este comando:

```SQL

GRANT ALL PRIVILEGES ON insumos.* TO 'enrique_insumos'@'localhost';

```

5. Ahora aplica los cambios en los permisos y sal de MariaDB ejecutando estos dos comandos:

```SQL

FLUSH PRIVILEGES;
EXIT;

```

## PASO 6. IMPORTAR BASE DE DATOS

1. Ahora el siguiente paso es traer los archivos de tu web y el archivo .sql de tu base de datos al servidor.
2. Una forma sencilla, es subirlo directamente desde el repositorio de GitHub
3. Vamos a clonar el repositorio dentro del servidor.

Ejecuta este comando en la terminal:

```bash

cd /var/www/regenerative-agro-platform && git clone https://github.com/enriquesantisteban/insumos.git .

```

4. Ahora vamos a importar la estructura y los datos de tu base de datos desde el archivo schema.sql que se acaba de descargar.   

Ejecuta este comando en la terminal:


```bash

mysql -u enrique_insumos -p insumos < /var/www/regenerative-agro-platform/schema.sql

```

::: Te pedirá la contraseña que le asignaste a enrique_insumos. Escríbela y pulsa Enter.


5. Copiar la plantilla config.example.php para crear el archivo config.php:

```bash

cd /var/www/regenerative-agro-platform
cp config.example.php config.php
nano config.php

```

Dentro del editor nano, busca el bloque de la base de datos y déjalo así:

```bash

define('DB_HOST', 'localhost');
define('DB_USER', 'enrique_insumos');
define('DB_PASS', 'TuPasswordAqui');
define('DB_NAME', 'insumos');

```

Para guardar en nano:

    - Presiona Ctrl + O y luego Enter.
    - Presiona Ctrl + X para salir.

## PASO 7. AJUSTAR PERMISOS DEL PROYECTO

Ejecuta este comando para que el servidor web (www-data) tenga acceso de lectura y ejecución a los archivos:

```bash

sudo chown -R www-data:www-data /var/www/regenerative-agro-platform
sudo chmod -R 755 /var/www/regenerative-agro-platform

```

1. Comprobar la versión y el socket de PHP-FPM:

Ejecuta este comando para ver el archivo de socket que tiene tu PHP:

```bash

ls /run/php/

```

## SI NO TENEMOS DOMINIO AÚN, CONTINUAMOS CON LA CONFIGURACIÓN ##

## PASO 8. COMPROBAR EXTENSIONES NECESARIAS DE PHP 7.2

```bash

php -m | grep -E 'mysqli|pdo_mysql|curl|mbstring'

```

Si falta alguna de ellas, se instala ejecutando:

```bash

sudo apt install php7.2-mysql php7.2-curl php7.2-mbstring -y
sudo systemctl restart php7.2-fpm

```

## PASO 9. PROBAR LA CONEXIÓN PHP <-> MariaDB DESDE CONSOLA

Para confirmar que config.php y las credenciales funcionan al 100%, ejecuta un test rápido ejecutando el script de conexión:

```bash

php /var/www/regenerative-agro-platform/db_connection.php

```

## PASO 10. CONFIGURAR NGINX PARA PROBAR LA WEB

Para poder acceder a la web desde el navegador mientras se consigue el dominio definitivo, vamos a configurar Nginx en un puerto alternativo.

Cuando tengas el dominio, solo habrá que cambiar una línea.

```bash

sudo nano /etc/nginx/sites-available/regenerative-agro

```

1. Pega la siguiente configuración:

```bash

server {
    listen 8085;
    server_name _;
 
    root /var/www/regenerative-agro-platform;
    index index.php;
 
    port_in_redirect off;
 
    access_log /var/log/nginx/regenerative_access.log;
    error_log /var/log/nginx/regenerative_error.log;
 
    location = / {
        return 302 /es/;
    }
 
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|webp)$ {
        try_files $uri /media/$uri /media/fabricantes/$uri =404;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/?$ {
        rewrite ^/(es|en|pt|fr|ca)/?$ /index.php?lang=$1 last;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/([A-Za-z0-9_-]+\.php)$ {
        rewrite ^/(es|en|pt|fr|ca)/([A-Za-z0-9_-]+\.php)$ /$2?lang=$1 last;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/(fabricante|manufacturer|fabricant)/([A-Za-z0-9_-]+)/?$ {
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/fabricante.php;
        fastcgi_param QUERY_STRING lang=$1&slug=$3;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/(producto|product|produto|produit|producte)/([A-Za-z0-9_-]+)/?$ {
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/producto.php;
        fastcgi_param QUERY_STRING lang=$1&slug=$3;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/(blog|news|noticias|nouvelles|noticies)(\.php)?/?$ {
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/blog.php;
        fastcgi_param QUERY_STRING lang=$1;
    }
 
    location ~ ^/(es|en|pt|fr|ca)/(blog|news|noticias|nouvelles|noticies)/([^/]+)/?$ {
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/blog.php;
        fastcgi_param QUERY_STRING lang=$1&slug=$3;
    }
 
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
 
    location / {
        try_files $uri $uri/ =404;
    }
 
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

```

Para guardar: Ctrl + O, luego Enter, y Ctrl + X para salir.


###### ###### IMPORTANTE ###### ######
###### ###### CAMBIAR LA CABECERA ###### ######

# Antes:
listen 8085;
server_name _;

# Después:
listen 80;
server_name tu-nuevo-dominio.com www.tu-nuevo-dominio.com;


2. Activar el sitio y validar la configuración:

Ejecuta estos tres comandos:

```bash

sudo ln -s /etc/nginx/sites-available/regenerative-agro /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

```

## PASO 11. VER WEB EN INTERNET

cloudflared tunnel --protocol http2 --url http://localhost:8085 2>&1 | grep -o 'https://.*\.trycloudflare\.com'


# PASO 12. ASIGNAR PUERTO 80 A LA WEB

1. Abres la configuración de la nueva plataforma:

```bash

sudo nano /etc/nginx/sites-available/regenerative-agro

```

2. Cambiar las primeras líneas:

```bash

# Antes:
listen 8085;
server_name _;

# Después:
listen 80;
server_name tu-nuevo-dominio.com www.tu-nuevo-dominio.com;

```

3. Guardar (Ctrl + O, Enter, Ctrl + X) y validar:

```bash

sudo nginx -t && sudo systemctl reload nginx

```

Y si usas Cloudflare Tunnel, simplemente le dices que apunte a http://localhost:80 en lugar de http://localhost:8085


## PASO 13. ASIGNAR DOMINIO A LA WEB

En tu ordenador (Navegador):

1. Registra tu dominio (TU-DOMINIO.COM) en un registrador (Cloudflare Registrar, Namecheap, etc.).

2. En la zona de gestión DNS del dominio:

    - Si usas IP pública directa:

    ``` bash

    Tipo: A      | Nombre: @   | Valor: IP_PUBLICA_DEL_SERVIDOR
    Tipo: CNAME  | Nombre: www | Valor: TU-DOMINIO.COM

    ```

    - Si mantienes la infraestructura protegida tras Cloudflare Tunnel (Recomendado para servidores locales/iDRAC):
      Configura un túnel persistente en el panel de Cloudflare Zero Trust apuntando al servicio http://localhost:80.


## PASO 14. ACTIVAR HTTPS


Dentro del servidor:

## 14.1. Si el dominio apunta por DNS directo (Registros A)

Instala Certbot y emite el certificado SSL gratuito:   

```bash

sudo apt update
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d TU-DOMINIO.COM -d www.TU-DOMINIO.COM

```

(Certbot modificará automáticamente el bloque de Nginx para redirigir todo el tráfico HTTP a HTTPS).

## 14.2. Si el dominio usa Cloudflare Tunnel

No necesitas Certbot: Cloudflare gestiona el certificado SSL en el borde. Solo debes dejar el túnel corriendo como servicio de sistema:

```bash

sudo cloudflared service install TU_TOKEN_DE_CLOUDFLARE

```



###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

#                                                                            ASIGNAR DE NUEVO PUERTOS                                                                                      #

###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

```bash

sudo sed -i 's/listen 80;/listen 8085;/' /etc/nginx/sites-available/regenerative-agro

```

###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

#                                                                           MATAR PROCESO CLOUDFLARED                                                                                      #

###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

```bash

sudo pkill -f cloudflared

```
###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

#                                                                           CAMBIOS EN GIT --> CÓMO ACTUALIZAR                                                                             #

###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######


1. En tu ordenador (donde editaste los archivos):
Sube tus cambios al repositorio remoto:

```bash

git add .
git commit -m "Traduccion de anclas y soporte blog"
git push origin main

```

2. En el servidor (por la terminal/iDRAC):

Añade la carpeta como excepción segura en la configuración global de Git:

```bash

sudo git config --global --add safe.directory /var/www/regenerative-agro-platform

```

Ve a la carpeta del proyecto y descarga los cambios:

``` bash

cd /var/www/regenerative-agro-platform
sudo git pull origin main

```

Si aparece algún error --> Forzar la actualización desde Git.
Ejecuta en la terminal:  

```bash

sudo git reset --hard origin/main
sudo git pull origin main

```

3. Asegura los permisos correctos:
Para que el servidor web siga pudiendo leer los archivos modificados:

```bash

sudo chown -R www-data:www-data /var/www/regenerative-agro-platform
sudo chmod -R 755 /var/www/regenerative-agro-platform

```


###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

#                                                                           BASE DE DATOS --> ACTUALIZAR                                                                                   #

###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ###### ######

1. Sube la base de datos a Git
2. En la terminal del servidor Linux:

```bash

cd /var/www/regenerative-agro-platform
sudo git pull origin main
sudo mysql -e "DROP DATABASE IF EXISTS insumos; CREATE DATABASE insumos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql insumos < insumos.sql
sudo chown -R www-data:www-data /var/www/regenerative-agro-platform
sudo chmod -R 755 /var/www/regenerative-agro-platform

```

