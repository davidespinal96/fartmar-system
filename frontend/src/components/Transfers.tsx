import { useCallback, useEffect, useState } from "react";
import api from "../services/api";
import { getApiErrorMessage, getApiErrorStatus } from "../services/errors";

interface Warehouse {
  id: number;
  code: string;
  name: string;
}

interface TransferItem {
  id: number;
  inventory_id: number;
  quantity: number;
  received_quantity: number;
  inventory?: {
    quantity: number;
    lot?: {
      lot_number: string;
      expiration_date: string;
      medication?: { name: string };
    };
  };
}

interface Transfer {
  id: number;
  status: string;
  observations?: string;
  created_at: string;
  source_warehouse?: Warehouse;
  destination_warehouse?: Warehouse;
  items: TransferItem[];
}

interface InventoryOption {
  id: number;
  quantity: number;
  warehouse_id: number;
  warehouse?: Warehouse;
  lot?: {
    lot_number: string;
    expiration_date: string;
    medication?: { name: string };
  };
}

export default function Transfers() {
  const [transfers, setTransfers] = useState<Transfer[]>([]);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [inventory, setInventory] = useState<InventoryOption[]>([]);
  const [source, setSource] = useState("");
  const [destination, setDestination] = useState("");
  const [inventoryId, setInventoryId] = useState("");
  const [quantity, setQuantity] = useState(1);
  const [observations, setObservations] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [receiptQuantities, setReceiptQuantities] = useState<Record<number, Record<number, number>>>({});
  const [receiptReasons, setReceiptReasons] = useState<Record<number, Record<number, string>>>({});

  const loadTransfers = useCallback(async () => {
    try {
      const response = await api.get("/transfers", {
        params: { per_page: 100 },
      });

      setTransfers(response.data.data ?? []);
    } catch {
      setError("No fue posible consultar las transferencias.");
    }
  }, []);

  const loadInventory = useCallback(async () => {
    try {
    const response = await api.get("/inventory", {
    params: { per_page: 100 },
    });

    const result = response.data?.data ?? response.data;
    const records = Array.isArray(result) ? result : [];

    setInventory(
      records.filter(
        (item: InventoryOption) =>
          Number(item.quantity) > 0 &&
          item.warehouse_id != null &&
          item.lot != null
      )
    );

    } catch (err) {
    console.error("Error cargando inventario:", err);
    setInventory([]);
    setError("No fue posible cargar las existencias.");
    }
    }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadTransfers();
      void loadInventory();
      api.get("/warehouses")
        .then((response) => {
          const result = response.data.data ?? response.data;
          setWarehouses(Array.isArray(result) ? result : []);
        })
        .catch(() => {
          setWarehouses([
            { id: 1, code: "BOD-CALI", name: "Bodega Cali" },
            { id: 2, code: "BOD-NORTE", name: "Bodega Norte" },
            { id: 3, code: "BOD-SUR", name: "Bodega Sur" },
          ]);
        });
    }, 0);
    return () => window.clearTimeout(timer);
  }, [loadTransfers, loadInventory]);

  const availableInventory = inventory.filter(
    (item) =>
      String(item.warehouse_id) === source &&
      item.quantity > 0 &&
      Boolean(item.lot?.expiration_date && item.lot.expiration_date >= new Date().toISOString().slice(0, 10))
  );

  async function createTransfer(event: React.FormEvent) {
    event.preventDefault();
    setError("");
    setSuccess("");

    if (!source || !destination || source === destination || !inventoryId) {
      setError("Selecciona bodegas diferentes y un lote disponible.");
      return;
    }

    const selectedInventory = inventory.find(
      (item) => item.id === Number(inventoryId)
    );

    if (!selectedInventory || quantity > selectedInventory.quantity) {
      setError("La cantidad supera las existencias disponibles.");
      return;
    }

    setBusy(true);

    try {
      await api.post("/transfers", {
        source_warehouse_id: Number(source),
        destination_warehouse_id: Number(destination),
        idempotency_key: crypto.randomUUID(),
        observations,
        items: [
          {
            inventory_id: Number(inventoryId),
            quantity,
          },
        ],
      });

      setSuccess("Borrador creado. Envíalo para aprobación desde el historial.");
      setObservations("");
      setQuantity(1);
      await loadTransfers();
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, "No fue posible crear la transferencia."));
    } finally {
      setBusy(false);
    }
  }

  async function performAction(
    transferId: number,
    action: "submit" | "approve" | "dispatch" | "receive" | "cancel"
    ) {
    setBusy(true);
    setError("");
    setSuccess("");

    try {
      const response = await api.post(
        `/transfers/${transferId}/${action}`
      );

      setSuccess(
        response.data.message ?? "Operación completada correctamente."
      );

      await Promise.all([loadTransfers(), loadInventory()]);
    } catch (err: unknown) {
      const status = getApiErrorStatus(err);
      const backendMessage = getApiErrorMessage(err, "");

      let message: string;

      if (status === 401) {
        message = "Tu sesión ha expirado. Inicia sesión nuevamente.";
      } else if (status === 403) {
        message =
          "No tienes permisos para realizar esta acción. " +
          "Verifica que tu usuario tenga el rol requerido.";
      } else if (status === 404) {
        message = "No se encontró la transferencia solicitada.";
      } else if (status === 422) {
        message =
          backendMessage ||
          "No se pudo completar la operación por un error de validación.";
      } else if (status !== undefined && status >= 500) {
        message =
          "Ocurrió un error interno del servidor. Inténtalo nuevamente.";
      } else if (status === undefined) {
        message =
          "No fue posible conectar con el servidor. Comprueba tu conexión.";
      } else {
        message =
          backendMessage || "No fue posible completar la operación.";
      }

      setError(message);
    } finally {
      setBusy(false);
    }

    }

  async function receiveTransfer(transfer: Transfer) {
    setBusy(true); setError(""); setSuccess("");
    const items = transfer.items.map(item => ({
      item_id: item.id,
      received_quantity: receiptQuantities[transfer.id]?.[item.id] ?? (item.quantity - item.received_quantity),
      discrepancy_reason: receiptReasons[transfer.id]?.[item.id] ?? "",
    }));
    try {
      const response = await api.post(`/transfers/${transfer.id}/receive`, { items });
      setSuccess(response.data.message ?? "Recepción registrada.");
      await Promise.all([loadTransfers(), loadInventory()]);
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, "No se pudo registrar la recepción."));
    } finally { setBusy(false); }
  }

  return (
    <div className="transfer-layout">
      <section className="panel">
        <div className="panel-heading">
          <div>
            <h3>Nueva transferencia</h3>
            <p>Traslada existencias entre bodegas conservando la trazabilidad del lote.</p>
          </div>
        </div>

        <form onSubmit={createTransfer} className="transfer-form">
          <label>Bodega de origen</label>
          <select
            value={source}
            onChange={(e) => {
              setSource(e.target.value);
              setInventoryId("");
            }}
            required
          >
            <option value="">Selecciona el origen</option>
            {warehouses.map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.name} ({warehouse.code})
              </option>
            ))}
          </select>

          <label>Bodega de destino</label>
          <select
            value={destination}
            onChange={(e) => setDestination(e.target.value)}
            required
          >
            <option value="">Selecciona el destino</option>
            {warehouses
              .filter((warehouse) => String(warehouse.id) !== source)
              .map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.name} ({warehouse.code})
                </option>
              ))}
          </select>

          <label>Lote e inventario de origen</label>
          <select
            value={inventoryId}
            onChange={(e) => setInventoryId(e.target.value)}
            required
          >
            <option value="">Selecciona el lote</option>
            {availableInventory.map((item) => (
              <option key={item.id} value={item.id}>
                {item.lot?.medication?.name ?? "Medicamento"} —{" "}
                {item.lot?.lot_number ?? `Inventario ${item.id}`} —{" "}
                {item.quantity} unidades
              </option>
            ))}
          </select>

          <label>Cantidad por transferir</label>
          <input
            type="number"
            min="1"
            max={
              inventory.find((item) => item.id === Number(inventoryId))
                ?.quantity ?? undefined
            }
            value={quantity}
            onChange={(e) => setQuantity(Number(e.target.value))}
            required
          />

          <label>Observaciones</label>
          <textarea
            value={observations}
            onChange={(e) => setObservations(e.target.value)}
            maxLength={1000}
            placeholder="Motivo de la transferencia..."
          />

          {error && <div className="alert error">{error}</div>}
          {success && <div className="alert success">{success}</div>}

          <button className="primary-button" disabled={busy}>
            {busy ? "Procesando..." : "Crear solicitud"}
          </button>
        </form>
      </section>

      <section className="panel">
        <div className="panel-heading">
          <div>
            <h3>Historial de transferencias</h3>
            <p>Seguimiento del estado de cada solicitud.</p>
          </div>
          <button className="secondary-button" onClick={loadTransfers}>
            Actualizar
          </button>
        </div>

        <div className="transfer-list">
          {transfers.length === 0 ? (
            <div className="table-message">
              No hay transferencias registradas.
            </div>
          ) : (
            transfers.map((transfer) => (
              <article className="transfer-card" key={transfer.id}>
                <div className="transfer-card-top">
                  <strong>Transferencia #{transfer.id}</strong>
                  <span className={`badge ${
                    transfer.status === "RECIBIDO"
                      ? "badge-green"
                      : transfer.status === "ANULADO"
                      ? "badge-red"
                      : "badge-orange"
                  }`}>
                    {transfer.status}
                  </span>
                </div>

                <p>
                  {transfer.source_warehouse?.name ?? "Origen"} →{" "}
                  {transfer.destination_warehouse?.name ?? "Destino"}
                </p>

                <small>
                  {new Date(transfer.created_at).toLocaleString("es-CO")}
                </small>

                <div className="transfer-card-items">
                  {transfer.items?.map((item) => (
                    <div key={item.id}>
                      <span>{item.inventory?.lot?.medication?.name ?? "Medicamento"}: {item.quantity} enviadas, {item.received_quantity} recibidas, {item.quantity - item.received_quantity} pendientes</span>
                      {(transfer.status === "EN_TRANSITO" || transfer.status === "RECIBIDO_PARCIAL") && <div className="receipt-line">
                        <label>Recibir ahora
                          <input type="number" min="0" max={item.quantity - item.received_quantity}
                            value={receiptQuantities[transfer.id]?.[item.id] ?? (item.quantity - item.received_quantity)}
                            onChange={e => setReceiptQuantities(prev => ({ ...prev, [transfer.id]: { ...prev[transfer.id], [item.id]: Number(e.target.value) } }))} />
                        </label>
                        <label>Motivo si queda pendiente
                          <input value={receiptReasons[transfer.id]?.[item.id] ?? ""} maxLength={500}
                            onChange={e => setReceiptReasons(prev => ({ ...prev, [transfer.id]: { ...prev[transfer.id], [item.id]: e.target.value } }))} />
                        </label>
                      </div>}
                    </div>
                  ))}
                </div>

                <div className="transfer-actions">
                  {transfer.status === "SOLICITADO" && (
                    <button
                      disabled={busy}
                      onClick={() => performAction(transfer.id, "approve")}
                    >
                      Aprobar
                    </button>
                  )}

                  {transfer.status === "BORRADOR" && <button disabled={busy} onClick={() => performAction(transfer.id, "submit")}>Enviar solicitud</button>}

                  {transfer.status === "APROBADO" && (
                    <button
                      disabled={busy}
                      onClick={() => performAction(transfer.id, "dispatch")}
                    >
                      Despachar
                    </button>
                  )}

                  {(transfer.status === "EN_TRANSITO" || transfer.status === "RECIBIDO_PARCIAL") && (
                    <button
                      disabled={busy}
                      onClick={() => receiveTransfer(transfer)}
                    >
                      Recibir
                    </button>
                  )}
                  {(transfer.status === "BORRADOR" || transfer.status === "SOLICITADO" || transfer.status === "APROBADO") && <button disabled={busy} onClick={() => performAction(transfer.id, "cancel")}>Anular</button>}
                </div>
              </article>
            ))
          )}
        </div>
      </section>
    </div>
  );
}
