# Nexo Ventas

Sistema de ventas multiempresa en Laravel 12 con stancl/tenancy. La base central `sistema_ventas` registra los negocios; cada negocio usa su propia base MySQL para usuarios, productos y operaciones. El acceso se realiza desde una sola URL con código de tienda, usuario o correo y contraseña.

## Iniciar en este equipo

1. Inicia **MySQL** desde el panel de XAMPP.
2. Abre `C:\xampp\htdocs\sistema_ventas\iniciar.bat` y mantén la ventana abierta.
3. Visita http://127.0.0.1:8000/login para ingresar a una tienda.
4. Visita http://127.0.0.1:8000/plataforma/login para administrar negocios.

También puedes iniciar desde PowerShell:

```powershell
Set-Location C:\xampp\htdocs\sistema_ventas
& C:\xampp\php82\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Se usa PHP 8.2.35 de `C:\xampp\php82`. El PHP predeterminado de XAMPP es 8.0 y no ejecuta este Laravel. Por eso el acceso preparado usa el servidor de Laravel en el puerto 8000. No necesita npm, Vite, Internet ni Apache para ejecutar la interfaz local.

## Flujo de uso

- **Propietario del software:** ingresa a la plataforma y registra el negocio, código de tienda, administrador, plan y fecha de vencimiento. El sistema crea su base, migra las tablas y crea al administrador. Puedes suspender o reactivar el acceso y extender la suscripción.
- **Administrador del negocio:** crea categorías, marcas, productos, clientes, proveedores y usuarios. Registra el inventario inicial desde Inventario o mediante una compra.
- **Caja:** abre un turno indicando el efectivo inicial. Existe un turno compartido por negocio; cada operación conserva su usuario. Puedes registrar ingresos/egresos y cerrar con el efectivo contado. El sistema calcula la diferencia.
- **Ventas:** selecciona productos, cliente, descuento y método de pago. Al confirmar se verifica el stock, se guarda la venta y se descuentan las existencias en una transacción. El mismo identificador de formulario no puede registrar dos veces la venta.
- **Compras:** registra proveedor, referencia, cantidades y costos. Actualiza existencias y costo actual del producto. Los costos de ventas anteriores permanecen guardados.
- **Tickets:** elige 58 u 80 mm y pulsa Imprimir. Configura el mismo papel en el controlador de tu impresora. No se ha realizado una impresión física.
- **Devoluciones:** devuelve una venta completa con un motivo. Requiere caja abierta; devuelve el stock y registra el reembolso en el turno actual. Una devolución no modifica los cierres de caja anteriores.
- **Reportes:** filtra por fechas y descarga PDF, Excel (.xlsx) o CSV. La ganancia mostrada es bruta: ventas menos costo de productos, sin gastos operativos. Las ventas devueltas se excluyen de los totales netos según su estado actual.
- **Respaldos:** en Configuración descarga el SQL del negocio. Incluye las tablas y datos del negocio. Para restaurarlo, un técnico debe importarlo en una base vacía del mismo negocio; la base central debe respaldarse aparte.

## Roles y permisos

| Rol | Acceso |
| --- | --- |
| Administrador | Todos los módulos de su negocio |
| Vendedor | Resumen, consulta de productos, clientes y ventas |
| Cajero | Resumen, consulta de productos, clientes, ventas, caja y devoluciones |
| Propietario de la plataforma | Negocios, estados, planes y vencimientos; acceso separado |

Los permisos se validan en el servidor. Se configuran en `app/Services/Access.php`. No se puede desactivar la propia cuenta de administrador ni dejar el negocio sin un administrador activo. Las cuentas de diferentes negocios pueden tener el mismo usuario y correo.

## Datos de demostración

Se creó la tienda **DEMO**, con ocho productos, categorías, marcas, un cliente y un proveedor de ejemplo. Durante la verificación se abrió caja con S/ 100.00 y se registró la venta V-000001: dos unidades de arroz, subtotal S/ 9.00, descuento S/ 1.00, total S/ 8.00, recibido S/ 10.00 y vuelto S/ 2.00. El efectivo esperado de ese turno es S/ 108.00. Estos registros sirven para probar el sistema.

Las credenciales generadas se entregan en la guía privada del chat, fuera de la carpeta pública del proyecto.

## Administración técnica

La instalación actual ya tiene las migraciones aplicadas y las cuentas iniciales creadas. Para una instalación nueva, después de configurar `.env` y crear una base central vacía:

```powershell
& C:\xampp\php82\php.exe artisan migrate --force
& C:\xampp\php82\php.exe artisan ventas:administrador propietario@ejemplo.com
& C:\xampp\php82\php.exe artisan ventas:demo
```

Los comandos de cuentas generan contraseñas aleatorias y las muestran una sola vez. No sustituyen cuentas ya existentes. El usuario MySQL de la aplicación necesita crear bases y tablas para aprovisionar nuevos negocios. La configuración de desarrollo actual usa el MySQL local de XAMPP.

En futuras actualizaciones de tablas tenant:

```powershell
& C:\xampp\php82\php.exe artisan migrate --force
& C:\xampp\php82\php.exe artisan tenants:migrate
```

Las migraciones centrales se encuentran en `database/migrations` y las de los negocios en `database/migrations/tenant`. Se usa una sesión de archivo para mantener el contexto independiente de la conexión tenant. Los controladores inicializan tenancy únicamente después de resolver la tienda desde la sesión autenticada.

## Verificación

Pruebas en `tests/Feature/SalesSystemTest.php`: aprovisionamiento, aislamiento entre negocios, acceso con el mismo usuario en empresas distintas, suspensión/vencimiento, permisos, ventas sin stock, pagos y descuentos inválidos, protección ante doble envío, compras, devolución en otro turno y carga de pantallas/exportaciones.

```powershell
& C:\xampp\php82\php.exe -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

Las pruebas usan SQLite en memoria para la base central y archivos temporales independientes para las tiendas de prueba. No deben configurarse contra una base con datos reales. En este entorno, PHP devuelve incorrectamente `is_readable=false` para los archivos del proyecto aunque los puede leer. Por ello se verificó la suite con un lanzador temporal que carga el autoloader antes de iniciar PHPUnit; no se modificó vendor.

Además se verificó el flujo HTTP con MySQL, los importes en el archivo Excel, la lectura/renderización del PDF y una restauración completa del respaldo en una base temporal.

## Alcance de esta versión local

Es una primera versión funcional para desarrollar y probar el proyecto. Incluye los diez grupos de módulos del documento con operaciones básicas. Usa unidades enteras y soles (PEN), un turno de caja compartido por negocio y devoluciones totales. Los planes y vencimientos se administran manualmente; no se ha integrado un proveedor de cobros recurrentes. Tarjeta, Yape/Plin y transferencia registran el método de pago, pero no procesan ni verifican el pago en servicios externos. Las compras no descuentan automáticamente la caja.

Los tickets son de control interno: no se ha implementado facturación electrónica SUNAT ni numeración fiscal. La impresión usa el navegador y requiere comprobar el papel y el controlador con la impresora real. No incluye devoluciones parciales, unidades fraccionadas, roles personalizados desde la interfaz, recuperación de contraseña por correo ni restauración de respaldos desde la web. El catálogo conserva los productos históricos y permite desactivarlos.

Antes de usarlo con negocios reales quedan la configuración de hosting/HTTPS, el usuario MySQL y permisos de producción, la política de respaldos, el ajuste de las reglas comerciales y la validación con los equipos reales. La instalación local mantiene APP_DEBUG para desarrollo; en producción debe desactivarse y el servidor debe apuntar exclusivamente a `public/`.
