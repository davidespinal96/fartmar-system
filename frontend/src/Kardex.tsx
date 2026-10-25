import { useEffect, useState } from "react";
import api from "./services/api";

interface Movement {
  id: number; type: string; quantity: number; balance_before: number;
  balance_after: number; occurred_at: string; observations?: string;
  inventory?: { warehouse?: { name: string }; lot?: { lot_number: string; medication?: { name: string } } };
  user?: { name: string };
}

export default function Kardex() {
  const [rows, setRows] = useState<Movement[]>([]);
  const [type, setType] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  useEffect(() => {
    api.get("/movements", { params: { per_page: 100, ...(type ? { type } : {}) } })
      .then(({ data }) => setRows(data.data ?? []))
      .catch(() => setError("No fue posible consultar el kardex."))
      .finally(() => setLoading(false));
  }, [type]);
  return <section className="panel">
    <div className="panel-heading"><div><h3>Kardex de inventario</h3><p>Movimientos de existencias con lote, bodega, responsable y saldo.</p></div>
      <select aria-label="Filtrar por tipo" value={type} onChange={e => { setLoading(true); setError(""); setType(e.target.value); }}>
        <option value="">Todos los movimientos</option><option value="DISPENSATION">Dispensación</option>
        <option value="TRANSFER_OUT">Salida por traslado</option><option value="TRANSFER_IN">Entrada por traslado</option>
        <option value="ADJUSTMENT">Ajuste</option><option value="RECEIPT">Entrada</option>
      </select>
    </div>
    {error && <div className="alert error">{error}</div>}
    {loading ? <div className="table-message">Cargando movimientos…</div> : rows.length === 0 ? <div className="table-message">No hay movimientos para este filtro.</div> :
      <div className="table-wrapper"><table><thead><tr><th>Fecha</th><th>Medicamento / lote</th><th>Bodega</th><th>Tipo</th><th>Cantidad</th><th>Saldo</th><th>Usuario</th></tr></thead>
        <tbody>{rows.map(row => <tr key={row.id}><td>{new Date(row.occurred_at).toLocaleString("es-CO")}</td>
          <td><strong>{row.inventory?.lot?.medication?.name ?? "Medicamento"}</strong><small>{row.inventory?.lot?.lot_number ?? "—"}</small></td>
          <td>{row.inventory?.warehouse?.name ?? "—"}</td><td>{row.type}</td><td>{row.quantity}</td>
          <td>{row.balance_before} → {row.balance_after}</td><td>{row.user?.name ?? "—"}</td></tr>)}</tbody></table></div>}
  </section>;
}
