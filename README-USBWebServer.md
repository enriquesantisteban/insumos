# Uso con USBWeb Server

## 1) Copiar los archivos al directorio de USBWeb Server

Coloca los archivos de `web Insumos` dentro de la carpeta web de USBWeb Server.
Por ejemplo:

`E:\USBWebServer\root\insumos`

Debes tener al menos estos archivos:
- `index.php`
- `fabricante.php`
- `producto.php`
- `db_connection.php`
- `schema.sql`

## 2) Iniciar USBWeb Server

Ejecuta el programa `USBWebserver.exe` desde tu memoria USB o carpeta de instalación.
Asegúrate de que los servicios estén activos:
- Apache
- MySQL

## 3) Abrir phpMyAdmin

En tu navegador, abre:

`http://localhost/phpmyadmin`

## 4) Crear la base de datos y las tablas

Selecciona la pestaña `SQL`, pega el contenido de `schema.sql` y ejecuta.

Si prefieres, también puedes ejecutar el archivo SQL desde línea de comandos usando el MySQL que trae USBWeb Server.

## 5) Añadir datos de ejemplo

En phpMyAdmin, selecciona la base de datos `insumos` y ejecuta un SQL como este:

```sql
INSERT INTO fabricantes (nombre, descripcion) VALUES
('Fabricante A', 'Fabricante de productos agrícolas'),
('Fabricante B', 'Fabricante de insumos naturales');

INSERT INTO productos (fabricante_id, nombre, descripcion, precio, stock) VALUES
(1, 'Producto A1', 'Descripción del producto A1', 12.50, 100),
(1, 'Producto A2', 'Descripción del producto A2', 8.75, 50),
(2, 'Producto B1', 'Descripción del producto B1', 15.00, 25);
```

## 6) Abrir la página

En el navegador, usa:

`http://localhost/insumos/index.php`

Si USBWeb Server usa un puerto diferente, usa el puerto en la URL, por ejemplo:

`http://localhost:8080/insumos/index.php`

## 7) Cómo funciona

- `index.php`: lista todos los fabricantes.
- `fabricante.php?id=...`: muestra los productos de ese fabricante.
- `producto.php?id=...`: muestra los detalles de un producto.

## 8) Datos de conexión MySQL

En USBWeb Server normalmente MySQL usa:
- usuario: `root`
- contraseña: usbw
- host: `localhost`

> Poner la carpeta `insumos` directamente en la tarjeta USB para llevarla a otro PC.

> Si no tienes acceso a la carpeta `insumos`, puedes copiar los archivos a otra carpeta y cambiar la ruta en `index.php` y `db_connection.php`.

## 9) Actualizar la página

Si necesitas actualizar la página, puedes hacerlo manualmente o usando GitHub Desktop.

### Manualmente

1. Descarga el archivo `index.php` desde GitHub.
2. Sube el archivo a la carpeta `insumos` en la tarjeta USB.
3. Abre la página en tu navegador.

### Usando GitHub Desktop

1. Abre GitHub Desktop.
2. Selecciona la carpeta `insumos` en la lista de repositorios.
3. Haz clic en el botón "Commit changes".
4. Escribe un mensaje de commit y haz clic en "Commit".
5. Haz clic en "Push".
6. Espera a que se actualice la página en la tarjeta USB.