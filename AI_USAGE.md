# Uso de inteligencia artificial

Se usó OpenAI Codex en el entorno local para revisar la prueba técnica y apoyar la implementación de endpoints, pantallas, documentación y configuración. El trabajo fue revisado y adaptado al código existente.

El adaptador opcional OpenAI usa function calling, recibe solo la intención que clasificó el servidor local y sus parámetros limitados; nunca recibe la consulta literal, observaciones de traslados ni registros de pacientes. La búsqueda/interpretación natural del texto permanece local en esta versión.

- Aceptado: conservar transacciones, bloqueos de filas e idempotencia que ya estaban implementados para las operaciones de stock.
- Corregido: los datos semilla ya tenían pacientes sintéticos, pero el seeder principal no ejecutaba el seeder de farmacia; se conectó para que la base cargue datos útiles desde cero.
- No se envían pacientes, prescripciones, observaciones ni el texto original de consulta al proveedor. El proveedor predeterminado es local/mock; el adaptador opcional OpenAI recibe únicamente la clasificación local permitida y el número de días.

La persona que entrega debe revisar cada cambio y poder explicarlo durante la sustentación.
