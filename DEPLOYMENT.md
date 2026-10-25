# Despliegue, reversión y respaldos

Para desplegar, construir imágenes desde el commit revisado, respaldar PostgreSQL y ejecutar migraciones como paso controlado antes de habilitar el tráfico. La configuración y secretos se inyectan desde el entorno del despliegue, nunca desde Git.

Si la verificación de salud o una revisión funcional falla, volver a la imagen de la versión anterior. Las migraciones deben ser compatibles con la versión anterior durante el cambio; para cambios destructivos, separar expansión y contracción en despliegues distintos.

Respaldar con `pg_dump -Fc` y restaurar en una base aislada con `pg_restore` antes de reemplazar producción. Validar conteos y relaciones en la restauración. Definir retención y cifrado en el proveedor de almacenamiento de copias.
