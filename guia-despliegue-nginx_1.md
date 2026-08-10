# Guía de despliegue: sitio "insumos" en servidor Nginx

Dominio: `insumos-agricultura-regenerativa.com`, en la **raíz** del dominio (no
en una subcarpeta `/insumos/`).

**Cómo leer esta guía:** cada bloque de comandos indica si se ejecuta en **tu
ordenador** o **dentro del servidor** (ya conectado por SSH). Es la parte que
más confunde la primera vez: una vez haces `ssh`, tu terminal "se convierte"
en la terminal del servidor hasta que escribes `exit` o cierras la ventana.
Todo lo que escribas mientras tanto se ejecuta allí, no en tu máquina.

---

## Paso 0. Conéctate al servidor por SSH

**En tu ordenador**, abre una terminal (Mac/Linux: la app Terminal; Windows:
PowerShell, Windows Terminal o PuTTY) y escribe:

```bash
ssh tu_usuario@ip_o_dominio_del_servidor
```

- `tu_usuario` es el usuario que tienes en el servidor (te lo habrán dado a ti
  o lo sabes ya si administras la máquina).
- `ip_o_dominio_del_servidor` es la IP del servidor (ej. `85.12.34.56`) o un
  nombre que ya resuelva a él.
- Te pedirá una contraseña (o usará tu clave SSH si la tienes configurada).
- Si es la primera vez que te conectas a esa máquina, te preguntará si
  confías en la huella del servidor — escribe `yes` y pulsa Enter.

Cuando el prompt cambie (normalmente se ve como `tu_usuario@nombre-servidor:~$`)
ya estás **dentro** del servidor. A partir de aquí, salvo que la guía diga
explícitamente "en tu ordenador", todos los comandos van en esta misma sesión.

Para reconocer el terreno antes de tocar nada (todavía dentro del servidor):

```bash
whoami; sudo -l                        # confirma que tienes permisos sudo
sudo nginx -v                          # versión de Nginx instalada
ls /etc/nginx/sites-available/         # sitios que ya existen (no los toques)
sudo cat /etc/nginx/sites-available/*  | grep server_name   # dominios ya usados
php -v                                 # versión de PHP ya instalada (si hay)
sudo systemctl status php*-fpm         # nombre exacto del servicio PHP-FPM (si existe)
```

Cuando termines de trabajar en el servidor (al final de toda la guía), sales
con:

```bash
exit
```

---

## Paso previo. Compra el dominio y configura el DNS

**En tu ordenador**, en el navegador, en el panel del registrador (no en el
servidor):

1. Compra `insumos-agricultura-regenerativa.com` en un registrador (Namecheap,
   OVH, IONOS, Cloudflare Registrar, etc.).
2. En la gestión de DNS de ese dominio, crea:
   ```
   Tipo: A
   Nombre: @
   Valor:  IP_pública_de_tu_servidor
   TTL:    automático o 3600

   Tipo: A  (o CNAME apuntando a @)
   Nombre: www
   Valor:  IP_pública_de_tu_servidor
   TTL:    automático o 3600
   ```
3. La propagación tarda de minutos a un par de horas. Compruébala **en tu
   ordenador**:
   ```bash
   dig insumos-agricultura-regenerativa.com
   # o
   nslookup insumos-agricultura-regenerativa.com
   ```
4. Mientras el DNS no esté propagado puedes seguir con el resto de la guía
   sin problema (solo hace falta que esté propagado de verdad para el Paso 9,
   HTTPS). Si quieres ver el sitio con el dominio real antes de que sea
   público, añade esta línea al `hosts` **de tu propio ordenador** (no del
   servidor):
   ```
   IP_del_servidor   insumos-agricultura-regenerativa.com
   ```
   En Linux/Mac es `/etc/hosts`, 
   En Windows es `C:\Windows\System32\drivers\etc\hosts`.

---

## Paso 0-bis. Cambio de código necesario (antes de subir nada)

**En tu ordenador**, en el proyecto local, edita `i18n.php`. Cambia:

```php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/insumos');
}
```

por:

```php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}
```

Con esto, todas las URLs generadas por `__url()`, `__lang_switch_url()` y
`__asset_url()` dejan de llevar el prefijo `/insumos` y pasan a ser del tipo
`/es/`, `/es/fabricante/kenogard`, etc., directamente desde la raíz. No hace
falta tocar nada más en el código: `nav.php`, `fabricante.php`, `producto.php`
ya usan esas funciones, así que heredan el cambio automáticamente.

---

## Paso 1. Prepara los archivos localmente

**En tu ordenador:**

1. Revisa que `db_connection.php` no vaya a producción con las credenciales
   de tu USBWebServer local (`root` / `usbw`). Las cambiarás en el Paso 5.
2. Quita cualquier `.sql`, `.zip`, `.bak` o archivo de prueba de la carpeta —
   todo lo que subas será potencialmente accesible por URL.
3. La carpeta a subir debe quedar así (sin subcarpeta `insumos/` de por
   medio, estos archivos van a ser la raíz del sitio):
   ```
   index.php
   fabricante.php
   producto.php
   nav.php
   i18n.php
   db_connection.php
   style.css
   media/ ...
   lang/ ...
   ```

---

## Paso 2. Crea la carpeta de destino en el servidor

**Dentro del servidor** (si cerraste la sesión SSH, reconéctate con el
comando del Paso 0):

```bash
sudo mkdir -p /var/www/insumos-agricultura-regenerativa.com
sudo chown $USER:$USER /var/www/insumos-agricultura-regenerativa.com
```

Qué hace cada línea:

- `sudo mkdir -p ...` crea la carpeta vacía donde vivirá el sitio. `sudo`
  porque `/var/www/` normalmente solo lo puede escribir el administrador.
- `sudo chown $USER:$USER ...` te hace propietario temporal de esa carpeta
  (`$USER` ya vale tu usuario actual, no hace falta escribirlo a mano), para
  poder subir archivos sin `sudo` en cada comando. Al final del Paso 3 se la
  devuelves a `www-data`, el usuario con el que corre Nginx.

---

## Paso 3. Sube los archivos al servidor

**En tu ordenador**, desde la carpeta del proyecto (con `rsync`, recomendado
porque es repetible sin duplicar archivos si vuelves a subir tras un cambio):

**SUBIR TODOS LOS ARCHIVOS EXCEPTO schema.sql.**

```bash
rsync -avz --progress ./ tu_usuario@ip_o_dominio_del_servidor:/var/www/insumos-agricultura-regenerativa.com/
```

o con `scp`:

```bash
scp -r * tu_usuario@ip_o_dominio_del_servidor:/var/www/insumos-agricultura-regenerativa.com/
```

Cuando termine, vuelve a **entrar por SSH al servidor** (Paso 0) y ajusta
permisos definitivos:

```bash
cd /var/www/insumos-agricultura-regenerativa.com
sudo chown -R www-data:www-data /var/www/insumos-agricultura-regenerativa.com
sudo find /var/www/insumos-agricultura-regenerativa.com -type d -exec chmod 755 {} \;
sudo find /var/www/insumos-agricultura-regenerativa.com -type f -exec chmod 644 {} \;
```

---

## Paso 4. Comprueba si hay motor de base de datos instalado, e instálalo si no

**Dentro del servidor.** Si la web anterior era HTML puro, es probable que no
haya ningún motor de base de datos instalado — no es "la misma base de
datos", es el motor (MySQL/MariaDB) donde luego crearás la base `insumos`
como una base independiente.

Comprueba primero si ya existe algo:

```bash
which mysql mariadb 2>/dev/null
sudo systemctl status mysql 2>/dev/null
sudo systemctl status mariadb 2>/dev/null
dpkg -l | grep -Ei 'mysql-server|mariadb-server'
```

Si no hay nada, instálalo (MariaDB es 100% compatible con `mysqli` de PHP, y
es lo habitual hoy en Debian/Ubuntu; si tu compañero ya usa MySQL en otro
sitio del servidor, usa ese en su lugar para no mezclar dos motores):

```bash
sudo apt update
sudo apt install mariadb-server -y
sudo systemctl enable --now mariadb
```

(Opcional pero recomendable en una instalación nueva:
`sudo mysql_secure_installation`, para fijar la contraseña de root y quitar
usuarios/bases de prueba por defecto.)

---

## Paso 5. Crea la base de datos y el usuario para el sitio

**Dentro del servidor**, entra a la consola de MySQL como administrador:

```bash
mysql -u root -p
```

Esto no crea nada todavía — solo te mete en la consola de MySQL (te pedirá
la contraseña de root que fijaste al instalar). El prompt cambiará a algo
como `MariaDB [(none)]>`. A partir de ahí escribes órdenes SQL, una por una:

```sql
CREATE DATABASE insumos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'insumos_user'@'localhost' IDENTIFIED BY 'PON_AQUI_UNA_CONTRASEÑA_FUERTE';

GRANT ALL PRIVILEGES ON insumos.* TO 'insumos_user'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```


(Si usas Windows, puedes usar `nano` o `notepad` en lugar de `nano`.)

Aclarando `'insumos_user'@'localhost'`:

- **`insumos_user`** es un nombre de usuario que te inventas para MySQL —
  puedes dejarlo tal cual o cambiarlo (ej. `insumos_web`). No tiene relación
  con tu usuario de Linux/SSH, es un usuario que solo existe dentro de MySQL.
- **`@'localhost'`** significa "solo puede conectarse desde el propio
  servidor, no desde internet". Como tu web (PHP) y la base de datos van a
  vivir en la misma máquina, **déjalo tal cual, no lo cambies**.
- Lo único que tienes que cambiar de verdad es `PON_AQUI_UNA_CONTRASEÑA_FUERTE`
  por una contraseña real que te inventes — guárdala, la necesitas en el
  siguiente bloque.

`EXIT;` te saca de la consola de MySQL y te devuelve a la terminal normal del
servidor.

Ahora sube `schema.sql` desde **tu ordenador** (en otra pestaña/terminal, sin
cerrar la sesión SSH que ya tienes abierta):

```bash
scp schema.sql tu_usuario@ip_o_dominio_del_servidor:/tmp/
```

Y de vuelta **dentro del servidor**, impórtalo en la base recién creada:

```bash
mysql -u insumos_user -p insumos < /tmp/schema.sql
```

(te pedirá la contraseña que pusiste para `insumos_user`, no la de root).

Por último, edita `db_connection.php` **dentro del servidor** con las
credenciales nuevas:

```bash
nano /var/www/insumos-agricultura-regenerativa.com/db_connection.php
```

```php
$host     = 'localhost';
$user     = 'insumos_user';
$password = 'PON_AQUI_LA_MISMA_CONTRASEÑA_FUERTE';
$database = 'insumos';
```

Y desactiva los errores visibles en producción (mismas primeras líneas del
archivo):

```php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
```

Guarda con `Ctrl+O`, Enter, y sal con `Ctrl+X` (si usas `nano`).

---

## Paso 6. Instala/verifica PHP y sus extensiones

**Dentro del servidor.** Si en el Paso 0 ya viste que PHP-FPM está corriendo,
solo confirma que tiene las extensiones que necesita el sitio:

```bash
php -m | grep -Ei 'mysqli|mbstring|xml'
```

Si falta alguna, o si el servidor no tenía PHP instalado:

```bash
sudo apt update
sudo apt install php-fpm php-mysqli php-mbstring php-xml -y
```

Confirma el nombre exacto del socket que vas a usar en el `fastcgi_pass` del
siguiente paso:

```bash
ls /run/php/
```

---

## Paso 7. Configura el "server block" de Nginx

**Dentro del servidor:**

```bash
sudo nano /etc/nginx/sites-available/insumos-agricultura-regenerativa.com
```

Pega esto (ajusta el `.sock` de PHP-FPM del final si es distinto al que viste
en el Paso 6):

```nginx
server {
    listen 80;
    server_name insumos-agricultura-regenerativa.com www.insumos-agricultura-regenerativa.com;

    root /var/www/insumos-agricultura-regenerativa.com;
    index index.php;

    # --- Redirección raíz -> idioma por defecto ---
    location = / {
        return 302 /es/;
    }

    # --- Fabricantes: /es/fabricante/slug, /en/manufacturer/slug, etc. ---
    location ~ ^/(es|en|pt|fr|ca)/(fabricante|manufacturer|fabricant)/([A-Za-z0-9_-]+)/?$ {
        try_files /nonexistent @fabricante;
    }
    location @fabricante {
        rewrite ^/(es|en|pt|fr|ca)/(fabricante|manufacturer|fabricant)/([A-Za-z0-9_-]+)/?$
                /fabricante.php?lang=$1&slug=$3 last;
    }

    # --- Productos: /es/producto/slug, /en/product/slug, etc. ---
    location ~ ^/(es|en|pt|fr|ca)/(producto|product|produto|produit|producte)/([A-Za-z0-9_-]+)/?$ {
        try_files /nonexistent @producto;
    }
    location @producto {
        rewrite ^/(es|en|pt|fr|ca)/(producto|product|produto|produit|producte)/([A-Za-z0-9_-]+)/?$
                /producto.php?lang=$1&slug=$3 last;
    }

    # --- Páginas normales con prefijo de idioma: /es/index.php, etc. ---
    location ~ ^/(es|en|pt|fr|ca)/([A-Za-z0-9_-]+\.php)$ {
        rewrite ^/(es|en|pt|fr|ca)/([A-Za-z0-9_-]+\.php)$ /$2?lang=$1 last;
    }

    # --- Inicio de cada idioma: /es/, /en/, etc. ---
    location ~ ^/(es|en|pt|fr|ca)/?$ {
        rewrite ^/(es|en|pt|fr|ca)/?$ /index.php?lang=$1 last;
    }

    # --- Ejecución de PHP ---
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;   # <-- ajusta a tu versión real
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # --- Archivos estáticos (css, imágenes, etc.) ---
    location / {
        try_files $uri $uri/ =404;
    }

    # Bloquea el acceso directo a archivos sensibles
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Guarda (`Ctrl+O`, Enter, `Ctrl+X`), activa el sitio y comprueba la sintaxis:

```bash
sudo ln -s /etc/nginx/sites-available/insumos-agricultura-regenerativa.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Paso 8. Prueba que todo funciona

**En tu navegador** (en tu ordenador):

- `http://insumos-agricultura-regenerativa.com/` → redirige a `/es/`
- `http://insumos-agricultura-regenerativa.com/es/` → portada en español
- `http://insumos-agricultura-regenerativa.com/es/fabricante/kenogard` → ficha de Kenogard
- Cambia idioma con el selector → la URL pasa a `/en/manufacturer/kenogard`, etc.

Si algo falla, revisa los logs **dentro del servidor**:

```bash
sudo tail -n 50 /var/log/nginx/error.log
sudo tail -n 50 /var/log/php8.1-fpm.log
```

Errores típicos:
- **502 Bad Gateway** → el `.sock` de `fastcgi_pass` no coincide con el real.
- **"Error de conexión MySQL"** → credenciales mal en `db_connection.php` o la
  base `insumos` no se importó.
- **Página en blanco** → con `display_errors` apagado, mira el log de PHP-FPM.

---

## Paso 9. Activa HTTPS

**Dentro del servidor**, con el DNS ya propagado (confírmalo con `dig` desde
tu ordenador, ver Paso previo):

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d insumos-agricultura-regenerativa.com -d www.insumos-agricultura-regenerativa.com
```

Certbot ajusta el `server {}` para servir por HTTPS y redirigir HTTP → HTTPS.

---

## Checklist final

- [ ] Dominio comprado y DNS (registros A para `@` y `www`) apuntando al servidor
- [ ] MariaDB/MySQL instalado y corriendo en el servidor (no existía antes)
- [ ] `BASE_PATH` cambiado a `''` en `i18n.php`
- [ ] Credenciales de `db_connection.php` cambiadas (usuario no-root, no las de local)
- [ ] `display_errors` desactivado
- [ ] `schema.sql` importado en la base `insumos` del servidor
- [ ] Permisos de archivos correctos (`www-data`)
- [ ] `nginx -t` sin errores y Nginx recargado
- [ ] Las 4 rutas de prueba del Paso 8 funcionan
- [ ] HTTPS activado
- [ ] Backup de la base de datos programado

---

## Nota final

No necesitas pedir permiso para ejecutar estos pasos si ya tienes acceso
sudo y SSH al servidor — es justo el tipo de tarea que se espera que resuelvas
de forma autónoma. Lo que sí conviene, una vez esté todo funcionando, es dejar
constancia por escrito (un mensaje al equipo, un ticket cerrado, o una entrada
en la wiki interna si la tienen) con: el dominio final, la ruta en el
servidor, el nombre de la base de datos y del usuario MySQL creado, y que el
certificado HTTPS quedó activo. Eso es lo que distingue "hice el despliegue"
de "hice el despliegue y cualquiera del equipo puede mantenerlo sin
preguntarme a mí".


