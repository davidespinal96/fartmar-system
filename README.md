# FARTMAR · Gestión farmacéutica

Aplicación de demostración para inventario por bodega y lote, dispensaciones FEFO y traslados con trazabilidad. Los datos de demostración son sintéticos.

## Inicio local

Requisitos: Docker Desktop y Docker Compose. Copia `.env.example` a `.env`, establece una contraseña local para PostgreSQL y agrega una clave de aplicación: `Add-Content .env "APP_KEY=base64:$([Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)))"`. Luego ejecuta `docker compose up --build`; el contenedor aplica migraciones y datos sintéticos automáticamente. Interfaz: `http://localhost:5173`; API: `http://localhost:8001/api`.

Credenciales de demostración (contraseña `FartmarTest2026!`): `auxiliar@fartmar.test`, `regente@fartmar.test`, `medico@fartmar.test`, `auditor@fartmar.test`, `admin@fartmar.test`.

## Funcionalidad existente

- Login con Sanctum y autorización por roles en rutas.
- Inventario consultable por bodega, medicamento y estado, con alertas de vencimiento a 90 días y mínimo.
- Dispensación transaccional, bloqueo de inventario, FEFO, consumo parcial acumulado e idempotencia. Medicamentos controlados requieren autorización previa de un regente y uso por otra cuenta; la aprobación queda ligada a fórmula, bodega y cantidad y se consume una vez.
- El médico puede crear fórmulas para pacientes activos; la búsqueda y consulta de fórmulas registra actor, paciente y acción sin copiar datos personales al log.
- Solicitud, aprobación segregada, despacho, recepción parcial y recepción final de traslados. Las diferencias quedan por línea con motivo y saldo pendiente; se pueden anular solicitudes aún no despachadas.
- Pantallas de inventario, dispensación, traslados y kardex.
- Búsqueda de paciente y fórmula con bitácora de acceso; auditoría recibe campos enmascarados y consulta los eventos de acceso.
- Ajustes de cantidad solo por regente, con motivo obligatorio y movimiento de kardex; los saldos iniciales sembrados también generan entradas.
- Endpoints `/health` y `/ready`, cabecera `X-Correlation-ID`, restricciones de stock y kardex append-only en PostgreSQL.
- OpenAPI inicial en `docs/openapi.yaml`; asistente con proveedor `mock` por defecto y adaptador OpenAI configurable. La clasificación local convierte la pregunta a un intent; al proveedor externo solo se envían intent y días, nunca el texto original, datos de pacientes o texto de observaciones.
- Imágenes multi-stage: API PHP-FPM tras Nginx y frontend estático servido por Nginx sin root. Compose ejecuta migraciones y seeders repetibles sin restablecer cantidades existentes.

## Decisiones y compromisos

1. PostgreSQL 16 se usa para soportar bloqueos de fila y consistencia concurrente; el entorno local es más pesado que SQLite.
2. FEFO selecciona varios lotes para atender una línea; esto conserva la rotación correcta pero crea más movimientos y requiere una transacción.
3. El asistente usa un proveedor mock determinista sin llaves ni envío de información; es seguro para evaluación offline, pero no genera respuestas LLM libres.
4. Sanctum usa tokens personales, una solución simple para el prototipo que requeriría gestión adicional de expiración y revocación en producción.

## Supuestos y pendientes conocidos

Los datos de pacientes son ficticios. Sigue fuera el flujo de administración de usuarios/catálogos. Los traslados recorren borrador, solicitud, aprobación, tránsito, recepción total/parcial y anulación. El asistente externo delega solo una confirmación de intent ya clasificado localmente, así limita inyección y exposición de datos a cambio de no usar el LLM para comprender la pregunta. Las pruebas backend cubren asignación FEFO, recepciones parciales y una carrera concurrente sobre la última unidad; CI la ejecuta contra PostgreSQL. La suite frontend Vitest cubre respuesta acotada y pregunta fuera de alcance. El despliegue de producción en CI es solo una simulación y requiere configurar aprobación del environment `production` en GitHub.

## Comandos de calidad

Frontend: `cd frontend && npm ci && npm run lint && npm run test && npm run build`.
Backend: `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && vendor/bin/pint --test && vendor/bin/phpstan analyse --configuration=phpstan.neon && php artisan test`.
Evaluación del asistente: `php backend/scripts/evaluate_assistant.php`.

La automatización de CI actual ejecuta lint/build del frontend, Pint y PHPUnit. Consulta `DEPLOYMENT.md` para la estrategia de despliegue, rollback y respaldo.
