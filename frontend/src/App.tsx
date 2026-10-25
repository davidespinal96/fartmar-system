import { useEffect, useState } from "react";
import api from "./services/api";
import "./App.css";
import Transfers from "./components/Transfers";
import Dispensations from "./Dispensations";
import Kardex from "./Kardex";
import Assistant from "./Assistant";
import { getApiErrorMessage } from "./services/errors";

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface WarehouseOption { id: number; code: string; name: string }

interface InventoryItem {
  id: number;
  quantity: number;
  minimum_stock: number;
  warehouse?: { name: string; code: string };
  lot?: {
    lot_number: string;
    expiration_date: string;
    medication?: { name: string; code: string };
  };
}

function App() {
  const [user, setUser] = useState<User | null>(null);
  const [email, setEmail] = useState("auxiliar@fartmar.test");
  const [password, setPassword] = useState("");
  const [inventory, setInventory] = useState<InventoryItem[]>([]);
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [section, setSection] = useState("dashboard");
  const [search, setSearch] = useState("");
  const [warehouse, setWarehouse] = useState("");
  const [inventoryStatus, setInventoryStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    const token = localStorage.getItem("fartmar_token");

    if (token) {
      api.get("/user")
        .then((response) => {
          setUser(response.data.user ?? response.data);
        })
        .catch(() => {
          localStorage.removeItem("fartmar_token");
          setUser(null);
        });
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (user) void loadInventory();
    }, 0);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user, search, warehouse, inventoryStatus, page]);

  useEffect(() => {
    if (!user) return;
    api.get("/warehouses").then(({ data }) => {
      const rows = data.data ?? data;
      setWarehouses(Array.isArray(rows) ? rows : []);
    }).catch(() => setWarehouses([]));
  }, [user]);

  async function login(event: React.FormEvent) {
    event.preventDefault();
    setLoading(true);
    setError("");

    try {
      const response = await api.post("/login", { email, password });
      localStorage.setItem("fartmar_token", response.data.token);
      setUser(response.data.user);
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, "No fue posible iniciar sesión. Verifica tus credenciales y el backend."));
    } finally {
      setLoading(false);
    }
  }

  async function loadInventory() {
    setLoading(true);
    setError("");

    try {
      const params: Record<string, string | number> = {
        page,
        per_page: 15,
        status: inventoryStatus,
      };

      if (search) params.search = search;
      if (warehouse) params.warehouse_id = warehouse;

      const response = await api.get("/inventory", { params });
      const payload = response.data;

      setInventory(payload.data ?? []);
      setLastPage(payload.last_page ?? 1);
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, "No fue posible cargar el inventario."));
    } finally {
      setLoading(false);
    }
  }

  async function logout() {
    try {
      await api.post("/logout");
    } catch {
      // Limpiar la sesión local incluso si el servidor no responde.
    }

    localStorage.removeItem("fartmar_token");
    setUser(null);
    setInventory([]);
  }

  const totalUnits = inventory.reduce(
    (sum, item) => sum + Number(item.quantity),
    0
  );

  const lowStock = inventory.filter(
    (item) => item.quantity <= item.minimum_stock
  ).length;

  const expired = inventory.filter(
    (item) =>
      item.lot?.expiration_date &&
      item.lot.expiration_date < new Date().toISOString().slice(0, 10)
  ).length;

  if (!user) {
    return (
      <main className="login-page">
        <section className="login-card">
          <div className="brand-mark">F</div>
          <p className="eyebrow">PLATAFORMA DE GESTIÓN FARMACÉUTICA</p>
          <h1>FARTMAR <span>IPS</span></h1>
          <p className="muted">Control de inventario, dispensación y trazabilidad.</p>

          <form onSubmit={login}>
            <label htmlFor="email">Correo electrónico</label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />

            <label htmlFor="password">Contraseña</label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />

            {error && <div className="alert error">{error}</div>}

            <button className="primary-button" disabled={loading}>
              {loading ? "Ingresando..." : "Iniciar sesión →"}
            </button>
          </form>

          <p className="login-footer">Acceso seguro · Gestión de medicamentos</p>
        </section>
      </main>
    );
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="brand-mark small">F</div>
          <div><strong>FARTMAR</strong><span>IPS · FARMACIA</span></div>
        </div>

        <p className="nav-caption">MENÚ PRINCIPAL</p>

        <button
          className={`nav-item ${section === "dashboard" ? "active" : ""}`}
          onClick={() => setSection("dashboard")}
        >
          <span>▦</span> Resumen
        </button>

        <button
          className={`nav-item ${section === "inventory" ? "active" : ""}`}
          onClick={() => setSection("inventory")}
        >
          <span>▤</span> Inventario
        </button>

        <button
          className={`nav-item ${section === "transfers" ? "active" : ""}`}
          onClick={() => setSection("transfers")}
        >
          <span>⇄</span> Transferencias
        </button>

        <button
          className={`nav-item ${section === "dispensations" ? "active" : ""}`}
          onClick={() => setSection("dispensations")}
        >
          <span>＋</span> Dispensaciones
        </button>

        <button className={`nav-item ${section === "kardex" ? "active" : ""}`} onClick={() => setSection("kardex")}>
          <span>↕</span> Kardex
        </button>

        <button className={`nav-item ${section === "assistant" ? "active" : ""}`} onClick={() => setSection("assistant")}>
          <span>✦</span> Asistente
        </button>

        <div className="sidebar-bottom">
          <div className="user-avatar">
            {(user?.name || "U").charAt(0).toUpperCase()}
          </div>

          <div className="user-details">
            <strong>{user?.name || "Usuario"}</strong>
            <span>{(user?.role || "usuario").replaceAll("_", " ")}</span>
          </div>

          <button className="logout-button" onClick={logout} title="Cerrar sesión">
            ↪
          </button>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div>
            <span className="breadcrumb">FARTMAR / {section.toUpperCase()}</span>
            <h2>
              {section === "dashboard" && "Resumen general"}
              {section === "inventory" && "Inventario farmacéutico"}
              {section === "transfers" && "Transferencias entre bodegas"}
              {section === "dispensations" && "Dispensación de medicamentos"}
              {section === "kardex" && "Kardex de inventario"}
              {section === "assistant" && "Asistente de inventario"}
            </h2>
          </div>
          <div className="topbar-status"><span /> Sistema conectado</div>
        </header>

        {error && <div className="alert error">{error}</div>}

        {section === "dashboard" && (
          <>
            <section className="welcome-panel">
              <div>
                <p className="eyebrow">PANEL DE CONTROL</p>
                <h1>Bienvenido, {user.name.split(" ")[0]}</h1>
                <p>Supervisa las existencias y la operación farmacéutica desde un solo lugar.</p>
              </div>
              <div className="welcome-icon">✚</div>
            </section>

            <section className="stats-grid">
              <article className="stat-card">
                <span className="stat-icon blue">▤</span>
                <p>Unidades en pantalla</p>
                <strong>{totalUnits.toLocaleString("es-CO")}</strong>
                <span className="stat-note">Página actual de inventario</span>
              </article>

              <article className="stat-card">
                <span className="stat-icon orange">⚠</span>
                <p>Referencias con stock bajo</p>
                <strong>{lowStock}</strong>
                <span className="stat-note">Según el mínimo configurado</span>
              </article>

              <article className="stat-card">
                <span className="stat-icon red">◷</span>
                <p>Lotes vencidos en pantalla</p>
                <strong>{expired}</strong>
                <span className="stat-note">Requieren revisión</span>
              </article>
            </section>

            <section className="panel">
              <div className="panel-heading">
                <div><h3>Inventario reciente</h3><p>Existencias por bodega y lote</p></div>
                <button className="secondary-button" onClick={() => setSection("inventory")}>
                  Ver inventario →
                </button>
              </div>
              <InventoryTable items={inventory.slice(0, 5)} loading={loading} canAdjust={user.role === "regente_farmacia"} onAdjusted={loadInventory} />
            </section>
          </>
        )}

        {section === "inventory" && (
          <section className="panel">
            <div className="panel-heading">
              <div><h3>Existencias disponibles</h3><p>Consulta por medicamento, bodega y estado.</p></div>
              <button className="secondary-button" onClick={loadInventory}>↻ Actualizar</button>
            </div>

            <div className="filters">
              <input
                placeholder="Buscar medicamento o código..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              />
              <select
                value={warehouse}
                onChange={(e) => { setWarehouse(e.target.value); setPage(1); }}
              >
                <option value="">Todas las bodegas</option>
                {warehouses.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
              </select>
              <select
                value={inventoryStatus}
                onChange={(e) => { setInventoryStatus(e.target.value); setPage(1); }}
              >
                <option value="all">Todos los estados</option>
                <option value="available">Disponibles</option>
                <option value="low_stock">Stock bajo</option>
                <option value="expiring">Próximos a vencer</option>
                <option value="expired">Vencidos</option>
              </select>
            </div>

            <InventoryTable items={inventory} loading={loading} canAdjust={user.role === "regente_farmacia"} onAdjusted={loadInventory} />

            <div className="pagination">
              <span>Página {page} de {lastPage}</span>
              <div>
                <button disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</button>
                <button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Siguiente</button>
              </div>
            </div>
          </section>
        )}

        {section === "transfers" && <Transfers />}

        {section === "dispensations" && <Dispensations userRole={user.role} />}

        {section === "kardex" && <Kardex />}

        {section === "assistant" && <Assistant />}

        <footer className="page-footer">
          <span>FARTMAR IPS · Sistema de gestión farmacéutica</span>
          <span>Control de inventario y trazabilidad</span>
        </footer>
      </main>
    </div>
  );
}

function InventoryTable({
  items,
  loading,
  canAdjust = false,
  onAdjusted,
}: {
  items: InventoryItem[];
  loading: boolean;
  canAdjust?: boolean;
  onAdjusted?: () => void;
}) {
  const [adjustingId, setAdjustingId] = useState<number | null>(null);
  const [adjustmentQuantity, setAdjustmentQuantity] = useState("");
  const [adjustmentReason, setAdjustmentReason] = useState("");
  const [adjustmentError, setAdjustmentError] = useState("");
  const [adjustmentBusy, setAdjustmentBusy] = useState(false);

  async function submitAdjustment(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (adjustingId === null) return;
    setAdjustmentBusy(true); setAdjustmentError("");
    try {
      await api.post(`/inventory/${adjustingId}/adjustments`, { quantity: Number(adjustmentQuantity), reason: adjustmentReason });
      setAdjustingId(null); setAdjustmentReason(""); onAdjusted?.();
    } catch (err: unknown) {
      setAdjustmentError(getApiErrorMessage(err, "No se pudo registrar el ajuste."));
    } finally { setAdjustmentBusy(false); }
  }
  if (loading) return <div className="table-message">Cargando inventario...</div>;
  if (items.length === 0) return <div className="table-message">No hay resultados para los filtros seleccionados.</div>;

  return (
    <div className="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Medicamento / lote</th>
            <th>Bodega</th>
            <th>Vencimiento</th>
            <th>Existencias</th>
            <th>Estado</th>
            {canAdjust && <th>Acciones</th>}
          </tr>
        </thead>
        <tbody>
          {items.map((item) => {
            const expiration = item.lot?.expiration_date ?? "";
            const isExpired = expiration !== "" &&
              expiration < new Date().toISOString().slice(0, 10);
            const isLow = item.quantity <= item.minimum_stock;

            return (
              <tr key={item.id}>
                <td>
                  <strong>{item.lot?.medication?.name ?? "Medicamento"}</strong>
                  <small>{item.lot?.lot_number ?? "Sin lote"}</small>
                </td>
                <td>{item.warehouse?.name ?? "—"}</td>
                <td>{expiration || "—"}</td>
                <td><strong>{item.quantity}</strong></td>
                <td>
                  <span className={`badge ${isExpired ? "badge-red" : isLow ? "badge-orange" : "badge-green"}`}>
                    {isExpired ? "Vencido" : isLow ? "Stock bajo" : "Disponible"}
                  </span>
                </td>
                {canAdjust && <td><button type="button" className="secondary-button" onClick={() => { setAdjustingId(item.id); setAdjustmentQuantity(String(item.quantity)); setAdjustmentError(""); }}>Ajustar</button>
                  {adjustingId === item.id && <form className="adjustment-form" onSubmit={submitAdjustment}>
                    <label>Nueva existencia<input type="number" min="0" step="1" required value={adjustmentQuantity} onChange={event => setAdjustmentQuantity(event.target.value)} /></label>
                    <label>Motivo del ajuste<textarea minLength={5} maxLength={500} required value={adjustmentReason} onChange={event => setAdjustmentReason(event.target.value)} /></label>
                    {adjustmentError && <small role="alert">{adjustmentError}</small>}
                    <button type="submit" disabled={adjustmentBusy}>{adjustmentBusy ? "Guardando…" : "Guardar ajuste"}</button>
                    <button type="button" onClick={() => setAdjustingId(null)}>Cancelar</button>
                  </form>}
                </td>}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default App;
