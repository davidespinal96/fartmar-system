import { useEffect, useState } from "react";
import api from "./services/api";

interface Dispensation {
  id: number;
  prescription_id: number;
  patient_id: number;
  status: string;
  dispensed_at: string;
  observations?: string;
  items?: {
    id: number;
    quantity: number;
    inventory_id: number;
  }[];
}

interface InventoryItem {
  id: number;
  quantity: number;
  lot?: {
    lot_number: string;
    expiration_date: string;
    medication?: {
      id: number;
      name: string;
      code: string;
    };
  };
  warehouse?: {
    id: number;
    name: string;
  };
}

interface WarehouseOption { id: number; name: string }
interface PatientOption { id: number; display_name: string; document_number: string }
interface PrescriptionLine { id: number; medication_id: number; medication_name: string; is_controlled: boolean; remaining_quantity: number }
interface PrescriptionOption { id: number; prescription_number: string; status: string; valid_until: string | null; items: PrescriptionLine[] }

interface ApiErrorResponse {
  message?: string;
  errors?: Record<string, string | string[]>;
}

function getErrorMessage(error: unknown): string {
  const err = error as {
    response?: {
      data?: ApiErrorResponse;
    };
    message?: string;
  };

  const data = err.response?.data;
  const validationMessages = Object.values(data?.errors ?? {})
    .flatMap((value) => (Array.isArray(value) ? value : [value]))
    .filter(Boolean);

  if (validationMessages.length > 0) {
    return validationMessages.join(" ");
  }

  return (
    data?.message ||
    err.message ||
    "Ocurrió un error inesperado. Intenta nuevamente."
  );
}

export default function Dispensations({ userRole }: { userRole?: string }) {
  const [dispensations, setDispensations] = useState<Dispensation[]>([]);
  const [inventory, setInventory] = useState<InventoryItem[]>([]);
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([]);
  const [prescriptionId, setPrescriptionId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [prescriptionItemId, setPrescriptionItemId] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [observations, setObservations] = useState("");
  const [patientSearch, setPatientSearch] = useState("");
  const [patientResults, setPatientResults] = useState<PatientOption[]>([]);
  const [selectedPatient, setSelectedPatient] = useState<number | null>(null);
  const [prescriptions, setPrescriptions] = useState<PrescriptionOption[]>([]);
  const [selectedPrescription, setSelectedPrescription] = useState<PrescriptionOption | null>(null);
  const [authorizationId, setAuthorizationId] = useState("");
  const [authorizationReason, setAuthorizationReason] = useState("");
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  async function loadData() {
    setLoadingData(true);

    let inventoryLoaded = false;

    try {
      const response = await api.get("/inventory", {
        params: { per_page: 100 },
      });

      const data =
        response.data?.data?.data ??
        response.data?.data ??
        response.data;

      setInventory(Array.isArray(data) ? data : []);
      inventoryLoaded = true;
    } catch (err: unknown) {
      setError(
        `No fue posible cargar el inventario. ${getErrorMessage(err)}`,
      );
    }

    try {
      const { data } = await api.get("/warehouses");
      setWarehouses(Array.isArray(data?.data) ? data.data : []);
    } catch (err: unknown) {
      setError(`No fue posible cargar las bodegas. ${getErrorMessage(err)}`);
    }

    try {
      const response = await api.get("/dispensations", {
        params: { per_page: 10 },
      });

      const data =
        response.data?.data?.data ??
        response.data?.data ??
        response.data;

      setDispensations(Array.isArray(data) ? data : []);
    } catch {
      // La consulta puede no estar implementada todavía.
      // No ocultamos ni sobrescribimos los errores del formulario.
      setDispensations([]);
    }

    if (inventoryLoaded) {
      setError((currentError) =>
        currentError.startsWith("No fue posible cargar el inventario.")
          ? ""
          : currentError,
      );
    }

    setLoadingData(false);
  }

  useEffect(() => {
    const timer = window.setTimeout(() => void loadData(), 0);
    return () => window.clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (patientSearch.trim().length < 2) return;
    const timer = window.setTimeout(() => {
      api.get("/patients", { params: { search: patientSearch.trim() } })
        .then(({ data }) => setPatientResults(data.data ?? []))
        .catch(() => setError("No fue posible buscar pacientes."));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [patientSearch]);

  async function choosePatient(patient: PatientOption) {
    setPrescriptionId("");
    setSelectedPatient(patient.id); setPatientSearch(`${patient.display_name} (${patient.document_number})`);
    setPatientResults([]); setSelectedPrescription(null); setPrescriptionItemId("");
    try {
      const { data } = await api.get(`/patients/${patient.id}/prescriptions`);
      setPrescriptions(data.data ?? []);
    } catch { setPrescriptions([]); setError("No fue posible cargar las fórmulas vigentes."); }
  }

  const selectedLine = selectedPrescription?.items.find(item => String(item.id) === prescriptionItemId);
  const selectablePrescriptions = prescriptions.filter(
    prescription => prescription.status === "ACTIVA"
      && prescription.items.some(item => item.remaining_quantity > 0),
  );
  const fefoLots = inventory
    .filter(item => String(item.warehouse?.id) === warehouseId && item.lot?.medication?.id === selectedLine?.medication_id && item.quantity > 0 && (item.lot?.expiration_date ?? "") >= new Date().toISOString().slice(0, 10))
    .sort((a, b) => a.lot!.expiration_date.localeCompare(b.lot!.expiration_date));

  async function authorizeControlledMedication() {
    if (!selectedLine || !selectedPrescription || !warehouseId || !authorizationReason.trim()) return;
    setLoading(true); setError(""); setSuccess("");
    try {
      const { data } = await api.post("/controlled-authorizations", {
        prescription_item_id: selectedLine.id, warehouse_id: Number(warehouseId),
        quantity: Number(quantity), authorization_key: crypto.randomUUID(), reason: authorizationReason.trim(),
      });
      setAuthorizationId(String(data.data.id));
      setSuccess(`Autorización aprobada. Comparte el ID ${data.data.id} con el usuario que realizará la dispensación.`);
    } catch (err: unknown) { setError(getErrorMessage(err)); }
    finally { setLoading(false); }
  }

  async function submitDispensation(
    event: React.FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();
    setError("");
    setSuccess("");

    const prescription = Number(prescriptionId);
    const warehouse = Number(warehouseId);
    const prescriptionItem = Number(prescriptionItemId);
    const requestedQuantity = Number(quantity);

    if (
      !Number.isInteger(prescription) ||
      prescription <= 0 ||
      !Number.isInteger(warehouse) ||
      warehouse <= 0 ||
      !Number.isInteger(prescriptionItem) ||
      prescriptionItem <= 0 ||
      !Number.isInteger(requestedQuantity) ||
      requestedQuantity <= 0
    ) {
      setError(
        "Verifica los datos: los identificadores y la cantidad deben ser números enteros positivos.",
      );
      return;
    }

    setLoading(true);

    try {
      const idempotencyKey = crypto.randomUUID();

      const response = await api.post("/dispensations", {
        prescription_id: prescription,
        warehouse_id: warehouse,
        idempotency_key: idempotencyKey,
        observations: observations.trim() || null,
        items: [
          {
            prescription_item_id: prescriptionItem,
            quantity: requestedQuantity,
            ...(authorizationId ? { controlled_authorization_id: Number(authorizationId) } : {}),
          },
        ],
      });

      const dispensationId =
        response.data?.data?.id ?? response.data?.id;

      setSuccess(
        `Dispensación registrada correctamente.${
          dispensationId ? ` Número: ${dispensationId}.` : ""
        }`,
      );

      setPrescriptionId("");
      setWarehouseId("");
      setPrescriptionItemId("");
      setSelectedPrescription(null); setSelectedPatient(null); setPrescriptions([]);
      setPatientSearch(""); setAuthorizationId("");
      setQuantity("1");
      setObservations("");

      await loadData();
    } catch (err: unknown) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }

  return (
    <section className="page-section">
      <div className="page-heading">
        <div>
          <h1>Dispensación de medicamentos</h1>
          <p>
            Registra entregas vinculadas a una fórmula médica y consulta las
            dispensaciones recientes.
          </p>
        </div>
      </div>

      {error && (
        <div className="alert alert-error" role="alert" aria-live="assertive">
          <strong>No se pudo completar la operación.</strong>
          <p>{error}</p>
        </div>
      )}

      {success && (
        <div
          className="alert alert-success"
          role="status"
          aria-live="polite"
        >
          {success}
        </div>
      )}

      {userRole !== "auditor" && userRole !== "medico" && <div className="content-card">
        <h2>Nueva dispensación</h2>

        <p className="muted">Busca al paciente y selecciona una fórmula vigente. El servidor asigna los lotes disponibles en orden FEFO.</p>

        <form className="form-grid" onSubmit={submitDispensation}>
          <label>Buscar paciente
            <input value={patientSearch} onChange={event => { setPatientSearch(event.target.value); setPatientResults([]); setSelectedPatient(null); setPrescriptionId(""); setSelectedPrescription(null); setPrescriptions([]); setPrescriptionItemId(""); setAuthorizationId(""); }} placeholder="Nombre o documento" disabled={loading} />
            {patientResults.length > 0 && <div className="suggestions">{patientResults.map(patient => <button type="button" key={patient.id} onClick={() => void choosePatient(patient)}>{patient.display_name} · {patient.document_number}</button>)}</div>}
          </label>

          <label>Fórmula médica
            <select required disabled={!selectedPatient || loading} value={prescriptionId} onChange={event => {
              const rx = selectablePrescriptions.find(item => String(item.id) === event.target.value) ?? null;
              setPrescriptionId(event.target.value); setSelectedPrescription(rx); setPrescriptionItemId("");
            }}><option value="">{selectedPatient ? "Selecciona fórmula" : "Primero selecciona un paciente"}</option>{selectablePrescriptions.map(rx => <option value={rx.id} key={rx.id}>{rx.prescription_number} · vigente hasta {rx.valid_until ?? "sin fecha"}</option>)}</select>
            {selectedPatient && prescriptions.length === 0 && <small>Este paciente no tiene fórmulas registradas.</small>}
            {selectedPatient && prescriptions.length > 0 && selectablePrescriptions.length === 0 && <small>Sus fórmulas no tienen medicamentos pendientes para dispensar.</small>}
          </label>

          <label>
            Bodega
            <select
              required
              value={warehouseId}
              onChange={(event) => setWarehouseId(event.target.value)}
              disabled={loading || loadingData}
            >
              <option value="">Selecciona una bodega</option>

              {warehouses.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.name}
                </option>
              ))}
            </select>

            {!loadingData && warehouses.length === 0 && (
              <small>No se encontraron bodegas disponibles.</small>
            )}
          </label>

          <label>Medicamento prescrito
            <select required disabled={!selectedPrescription || loading} value={prescriptionItemId} onChange={event => { setPrescriptionItemId(event.target.value); setAuthorizationId(""); }}><option value="">{selectedPrescription ? "Selecciona medicamento" : "Primero selecciona una fórmula"}</option>{selectedPrescription?.items.filter(item => item.remaining_quantity > 0).map(item => <option value={item.id} key={item.id}>{item.medication_name} · pendientes {item.remaining_quantity}{item.is_controlled ? " · control especial" : ""}</option>)}</select>
          </label>

          <label>
            Cantidad a dispensar
            <input
              type="number"
              min="1"
              step="1"
              required
              value={quantity}
              max={selectedLine?.remaining_quantity ?? undefined}
              onChange={(event) => setQuantity(event.target.value)}
              disabled={loading}
            />
          </label>

          <label className="form-full">
            Observaciones
            <textarea
              value={observations}
              onChange={(event) => setObservations(event.target.value)}
              rows={3}
              maxLength={1000}
              placeholder="Observaciones opcionales"
              disabled={loading}
            />
          </label>

          {selectedLine && <div className="form-full fefo-preview"><strong>Asignación FEFO estimada</strong>{fefoLots.length === 0 ? <p>No hay lotes vigentes disponibles en esta bodega.</p> : <ul>{(() => { let remaining = Number(quantity); return fefoLots.flatMap(lot => { const assigned = Math.min(remaining, lot.quantity); remaining -= assigned; return assigned > 0 ? [<li key={lot.id}>{lot.lot?.lot_number}: {assigned} u. · vence {lot.lot?.expiration_date}</li>] : []; }); })()}</ul>}</div>}

          {selectedLine?.is_controlled && <div className="form-full controlled-auth">
            <strong>Autorización de control especial</strong>
            {userRole === "regente_farmacia" && <><p>Como regente, autoriza exactamente la cantidad y la bodega seleccionadas. El usuario que dispense debe ser distinto.</p>
              <label>Motivo de autorización<input value={authorizationReason} onChange={event => setAuthorizationReason(event.target.value)} required maxLength={500} /></label>
              <button type="button" onClick={() => void authorizeControlledMedication()} disabled={loading || !authorizationReason.trim()}>Autorizar como regente</button></>}
            <label>ID de autorización emitida por otro regente<input value={authorizationId} onChange={event => setAuthorizationId(event.target.value)} required type="number" min="1" /></label>
          </div>}

          <div className="form-full">
            <button type="submit" disabled={loading || loadingData}>
              {loading ? "Registrando..." : "Registrar dispensación"}
            </button>
          </div>
        </form>
      </div>}

      <div className="content-card">
        <h2>Dispensaciones recientes</h2>

        {loadingData ? (
          <p className="muted">Cargando información...</p>
        ) : dispensations.length === 0 ? (
          <p className="muted">
            No hay registros disponibles o el endpoint de consulta aún no está
            implementado.
          </p>
        ) : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Número</th>
                  <th>Fórmula</th>
                  <th>Paciente</th>
                  <th>Estado</th>
                  <th>Fecha</th>
                </tr>
              </thead>

              <tbody>
                {dispensations.map((dispensation) => (
                  <tr key={dispensation.id}>
                    <td>{dispensation.id}</td>
                    <td>{dispensation.prescription_id ?? "Fórmula enmascarada"}</td>
                    <td>{dispensation.patient_id ?? "Paciente oculto"}</td>
                    <td>{dispensation.status}</td>
                    <td>
                      {dispensation.dispensed_at
                        ? new Date(
                            dispensation.dispensed_at,
                          ).toLocaleString("es-CO")
                        : "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </section>
  );
}
