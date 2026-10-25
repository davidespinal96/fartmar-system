import { type FormEvent, useState } from "react";
import api from "./services/api";

interface Result { warehouse: string; medication: string; lot: string; expiration_date: string; quantity: number; minimum_stock: number }

export default function Assistant() {
  const [question, setQuestion] = useState("");
  const [answer, setAnswer] = useState("");
  const [results, setResults] = useState<Result[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(""); setAnswer(""); setResults([]);
    try {
      const { data } = await api.post("/inventory-assistant", { question });
      setAnswer(data.answer); setResults(data.results ?? []);
    } catch { setError("No fue posible consultar el asistente."); }
    finally { setBusy(false); }
  }
  return <section className="panel"><div className="panel-heading"><div><h3>Asistente de inventario</h3><p>Consulta existencias, stock bajo o lotes próximos a vencer.</p></div></div>
    <form onSubmit={submit} className="filters" style={{ gridTemplateColumns: "1fr auto" }}>
      <input value={question} onChange={e => setQuestion(e.target.value)} maxLength={500} placeholder="¿Qué lotes vencen en los próximos 60 días?" required />
      <button className="secondary-button" disabled={busy}>{busy ? "Consultando…" : "Consultar"}</button>
    </form>
    <p className="muted">El asistente no consulta ni envía datos de pacientes. Solo ejecuta consultas de lectura predefinidas.</p>
    {error && <div className="alert error">{error}</div>}{answer && <div className="alert success" role="status">{answer}</div>}
    {results.length > 0 && <div className="table-wrapper"><table><thead><tr><th>Medicamento</th><th>Lote</th><th>Bodega</th><th>Vencimiento</th><th>Unidades</th></tr></thead>
      <tbody>{results.map((row, i) => <tr key={`${row.lot}-${i}`}><td>{row.medication}</td><td>{row.lot}</td><td>{row.warehouse}</td><td>{row.expiration_date || "—"}</td><td>{row.quantity}</td></tr>)}</tbody></table></div>}
  </section>;
}
