
# TecnoStock

Sistema web de gestión de inventario desarrollado con PHP y MySQL.

## Tecnologías utilizadas

- PHP 8.3
- MySQL 8.4
- HTML5
- Bootstrap 5.3
- JavaScript
- Apache (XAMPP)
- phpMyAdmin

## Funcionalidades

- Inicio y cierre de sesión.
- Administración de categorías.
- Registro, búsqueda y edición de productos.
- Activación y desactivación de registros.
- Registro de entradas y salidas de inventario.
- Validación de stock insuficiente.
- Alertas de bajo stock.
- Panel principal con estadísticas.
- Historial de movimientos.
- Precios expresados en pesos chilenos.

## Base de datos

Nombre: tecnostock

Tablas:
- usuarios
- categorias
- productos
- movimientos

El respaldo se encuentra en:

sql/tecnostock.sql

## Requisitos

- Servidor Apache con PHP 8.3
- MySQL 8.4
- Extensión PDO MySQL habilitada
- Navegador web

## Instalación

### Instalación en otro computador

1. Instalar XAMPP con Apache, PHP y MySQL.
2. Copiar la carpeta `tecnostock` dentro de `C:\xampp\htdocs`.
3. Iniciar Apache y MySQL desde XAMPP.
4. Abrir phpMyAdmin y crear una base de datos llamada `tecnostock`.
5. Importar el archivo `sql/tecnostock_demo.sql`.
6. Crear un usuario MySQL con permisos SELECT, INSERT y UPDATE sobre la base `tecnostock`.
7. Crear la carpeta `C:\xampp\tecnostock_private`.
8. Copiar `conexion.example.php` a esa carpeta con el nombre `conexion.php`.
9. Configurar en `conexion.php` el host, puerto, base de datos, usuario y contraseña de MySQL.
10. Crear el administrador mediante el script `scripts/crear_admin.php`.
11. Abrir `http://localhost/tecnostock/login.php`.
12. Iniciar sesión con el administrador creado.

### Crear el administrador

Abrir PowerShell y ejecutar:

```powershell
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\tecnostock\scripts\crear_admin.php"
```

El programa solicitará:

- Nombre del administrador.
- Correo electrónico.
- Contraseña de al menos 12 caracteres.

La contraseña se almacena mediante un hash generado por PHP.

### Consideraciones

- No publicar archivos de conexión con contraseñas reales.
- No importar `tecnostock.sql` para la instalación de demostración.
- Utilizar `tecnostock_demo.sql`, que contiene las tablas y relaciones sin datos personales.
- El archivo `conexion.example.php` funciona como plantilla.
- Ejecutar `crear_admin.php` únicamente desde la terminal.
- El sistema utiliza precios expresados en pesos chilenos.


## Seguridad

- Contraseñas protegidas mediante hash.
- Consultas preparadas con PDO.
- Validación de formularios.
- Control de sesiones.
- Protección CSRF en formularios de administración.
- Restricción de acceso a archivos SQL.

## Estado del proyecto

Sistema funcional en entorno local.

## Declaración de uso de inteligencia artificial

Durante el desarrollo de TecnoStock se utilizó ChatGPT, una herramienta de inteligencia artificial desarrollada por OpenAI, como apoyo en distintas etapas del proyecto.

Su utilización incluyó:

- **Desarrollo del sistema:** revisión de código PHP, HTML, JavaScript y consultas SQL.
- **Base de datos:** comprobar la integridad de los datos.
- **Corrección de errores:** apoyo en la identificación y solución de problemas durante el desarrollo.
- **Validaciones y seguridad:** orientación para implementar controles de acceso, validación de formularios y protección de datos.
- **Pruebas:** apoyo en la planificación y revisión de pruebas funcionales del sistema.

El estudiante realizó la configuración del entorno de desarrollo, implementó y ejecutó el código, efectuó pruebas de funcionamiento y revisó los resultados obtenidos.

La inteligencia artificial se utilizó como herramienta de apoyo técnico y educativo durante el desarrollo del proyecto.