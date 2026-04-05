# Documentación de Reservas - Panel de Administración

## 📌 Endpoints Disponibles para Admin

**IMPORTANTE:** Todos estos endpoints requieren autenticación con JWT de admin.

```
GET    /api/admin/reservations              - Listar todas las reservas
GET    /api/admin/reservations/{id}         - Ver detalle de una reserva
POST   /api/admin/reservations/{id}/confirm - Confirmar reserva y descontar stock
POST   /api/admin/reservations/{id}/reject  - Rechazar reserva
```

---

## 🔐 Autenticación

Todos los requests deben incluir el token JWT en el header:

```javascript
headers: {
  "Authorization": "Bearer {token_jwt_admin}",
  "Content-Type": "application/json"
}
```

---

## 📋 1. Listar Todas las Reservas

### GET /api/admin/reservations

**URL:** `GET https://tu-dominio.com/api/admin/reservations`

### Query Parameters (Todos opcionales)

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | number | 1 | Número de página |
| `limit` | number | 10 | Items por página (max: 100) |
| `status` | string | - | Filtrar por estado: `pending`, `confirmed`, `rejected`, `expired` |
| `search` | string | - | Buscar por nombre, email o número de reserva |

### Ejemplos de URLs

```bash
# Todas las reservas (página 1)
GET /api/admin/reservations

# Reservas pendientes
GET /api/admin/reservations?status=pending

# Búsqueda por nombre o email
GET /api/admin/reservations?search=juan

# Paginación
GET /api/admin/reservations?page=2&limit=20

# Combinación
GET /api/admin/reservations?status=pending&page=1&limit=50
```

### Request Completo

```javascript
const response = await fetch('https://tu-api.com/api/admin/reservations?status=pending', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${tokenAdmin}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
```

### Response Exitoso (200)

```json
{
  "data": [
    {
      "id": 15,
      "reservation_number": "RES20251106A3F2",
      "customer_name": "Juan Pérez",
      "customer_email": "juan@example.com",
      "customer_phone": "+54 9 11 1234-5678",
      "shipping_address": "Av. Corrientes 1234",
      "shipping_city": "Buenos Aires",
      "shipping_state": "CABA",
      "shipping_zip_code": "C1043",
      "status": "pending",
      "subtotal": "38500.00",
      "tax_amount": "0.00",
      "shipping_amount": "0.00",
      "discount_amount": "0.00",
      "total_amount": "38500.00",
      "notes": "Prefiero entrega por la mañana",
      "admin_notes": null,
      "created_at": "2025-11-06 14:30:00",
      "updated_at": "2025-11-06 14:30:00",
      "confirmed_at": null,
      "confirmed_by": null,
      "items_count": 3
    },
    {
      "id": 14,
      "reservation_number": "RES20251105B7D9",
      "customer_name": "María García",
      "customer_email": "maria@example.com",
      "customer_phone": "+54 9 11 9876-5432",
      "status": "confirmed",
      "total_amount": "25000.00",
      "created_at": "2025-11-05 10:15:00",
      "confirmed_at": "2025-11-05 15:30:00",
      "confirmed_by": 1,
      "items_count": 2
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 45,
    "pages": 5
  }
}
```

### Estados Posibles

| Estado | Descripción | Color sugerido |
|--------|-------------|----------------|
| `pending` | Pendiente de revisión | Amarillo/Naranja |
| `confirmed` | Confirmada por admin | Verde |
| `rejected` | Rechazada por admin | Rojo |
| `expired` | Expirada (futura feature) | Gris |

---

## 🔍 2. Ver Detalle de Reserva

### GET /api/admin/reservations/{id}

**URL:** `GET https://tu-dominio.com/api/admin/reservations/15`

### Request

```javascript
const reservationId = 15;

const response = await fetch(`https://tu-api.com/api/admin/reservations/${reservationId}`, {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${tokenAdmin}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
```

### Response Exitoso (200)

```json
{
  "id": 15,
  "reservation_number": "RES20251106A3F2",
  "customer_name": "Juan Pérez",
  "customer_email": "juan@example.com",
  "customer_phone": "+54 9 11 1234-5678",
  "shipping_address": "Av. Corrientes 1234",
  "shipping_city": "Buenos Aires",
  "shipping_state": "CABA",
  "shipping_zip_code": "C1043",
  "status": "pending",
  "subtotal": "38500.00",
  "tax_amount": "0.00",
  "shipping_amount": "0.00",
  "discount_amount": "0.00",
  "total_amount": "38500.00",
  "notes": "Prefiero entrega por la mañana",
  "admin_notes": null,
  "created_at": "2025-11-06 14:30:00",
  "updated_at": "2025-11-06 14:30:00",
  "confirmed_at": null,
  "confirmed_by": null,
  "items": [
    {
      "id": 23,
      "reservation_id": 15,
      "product_id": 1,
      "product_name": "Mesa de Comedor",
      "product_sku": "MESA-001",
      "quantity": 2,
      "price": "15000.00",
      "total": "30000.00",
      "created_at": "2025-11-06 14:30:00"
    },
    {
      "id": 24,
      "reservation_id": 15,
      "product_id": 5,
      "product_name": "Silla Tapizada",
      "product_sku": "SILLA-005",
      "quantity": 1,
      "price": "8500.00",
      "total": "8500.00",
      "created_at": "2025-11-06 14:30:00"
    }
  ],
  "logs": [
    {
      "id": 45,
      "reservation_id": 15,
      "action": "created",
      "user_id": null,
      "details": "Reservation created by customer",
      "created_at": "2025-11-06 14:30:00"
    },
    {
      "id": 46,
      "reservation_id": 15,
      "action": "email_sent",
      "user_id": null,
      "details": "Confirmation email sent to customer and admin",
      "created_at": "2025-11-06 14:30:05"
    }
  ]
}
```

### Response Error (404)

```json
{
  "error": "Reservation not found"
}
```

---

## ✅ 3. Confirmar Reserva

### POST /api/admin/reservations/{id}/confirm

**IMPORTANTE:** Esta acción descuenta el stock de los productos. Es irreversible.

**URL:** `POST https://tu-dominio.com/api/admin/reservations/15/confirm`

### Request Body (Opcional)

```json
{
  "admin_notes": "Pago confirmado por transferencia. Coordinar entrega para el sábado 09/11 entre 10-14hs."
}
```

### Request Completo

```javascript
const reservationId = 15;
const adminNotes = "Pago confirmado por transferencia. Entrega sábado 10-14hs.";

const response = await fetch(`https://tu-api.com/api/admin/reservations/${reservationId}/confirm`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${tokenAdmin}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    admin_notes: adminNotes
  })
});

const data = await response.json();
```

### Response Exitoso (200)

```json
{
  "message": "Reservation confirmed successfully",
  "reservation_id": 15,
  "reservation_number": "RES20251106A3F2",
  "status": "confirmed",
  "confirmed_at": "2025-11-06 15:45:23"
}
```

### Qué Pasa al Confirmar

1. ✅ Estado cambia de `pending` a `confirmed`
2. ✅ **Stock se descuenta** de cada producto
3. ✅ Se registra fecha y hora de confirmación
4. ✅ Se registra qué admin confirmó (user_id del token)
5. ✅ Se agrega `admin_notes` a la reserva
6. ✅ Se crea log de auditoría
7. ✅ **Se envía email al cliente** con:
   - Confirmación de reserva
   - Detalle de productos
   - Notas del admin (instrucciones de pago/entrega)

### Errores Posibles

#### 404 - Reserva no encontrada
```json
{
  "error": "Reservation not found"
}
```

#### 400 - Ya está confirmada
```json
{
  "error": "Reservation is already confirmed"
}
```

#### 400 - Stock insuficiente
```json
{
  "error": "Insufficient stock for product: Mesa de Comedor (SKU: MESA-001). Available: 1, Required: 2"
}
```

---

## ❌ 4. Rechazar Reserva

### POST /api/admin/reservations/{id}/reject

**URL:** `POST https://tu-dominio.com/api/admin/reservations/15/reject`

### Request Body (Opcional)

```json
{
  "admin_notes": "Producto fuera de stock temporalmente. Se contactó al cliente para ofrecer alternativas."
}
```

### Request Completo

```javascript
const reservationId = 15;
const adminNotes = "Producto discontinuado. Cliente contactado.";

const response = await fetch(`https://tu-api.com/api/admin/reservations/${reservationId}/reject`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${tokenAdmin}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    admin_notes: adminNotes
  })
});

const data = await response.json();
```

### Response Exitoso (200)

```json
{
  "message": "Reservation rejected successfully",
  "reservation_id": 15,
  "status": "rejected"
}
```

### Qué Pasa al Rechazar

1. ✅ Estado cambia de `pending` a `rejected`
2. ✅ Stock **NO se descuenta**
3. ✅ Se agrega `admin_notes` a la reserva
4. ✅ Se crea log de auditoría
5. ❌ **NO se envía email automático** al cliente
   - El admin debe contactar manualmente al cliente

### Errores Posibles

#### 404 - Reserva no encontrada
```json
{
  "error": "Reservation not found"
}
```

#### 400 - Ya está rechazada o confirmada
```json
{
  "error": "Reservation cannot be rejected (current status: confirmed)"
}
```

---

## 💻 Implementación React - Panel Admin

### Componente: Lista de Reservas

```jsx
import React, { useState, useEffect } from 'react';

function ListaReservas() {
  const [reservas, setReservas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filtros, setFiltros] = useState({
    status: '',
    search: '',
    page: 1,
    limit: 20
  });
  const [pagination, setPagination] = useState({
    page: 1,
    limit: 20,
    total: 0,
    pages: 1
  });

  const tokenAdmin = localStorage.getItem('admin_token');

  // Cargar reservas
  useEffect(() => {
    cargarReservas();
  }, [filtros]);

  const cargarReservas = async () => {
    setLoading(true);
    try {
      // Construir query string
      const params = new URLSearchParams();
      if (filtros.status) params.append('status', filtros.status);
      if (filtros.search) params.append('search', filtros.search);
      params.append('page', filtros.page);
      params.append('limit', filtros.limit);

      const response = await fetch(
        `https://tu-api.com/api/admin/reservations?${params}`,
        {
          headers: {
            'Authorization': `Bearer ${tokenAdmin}`,
            'Content-Type': 'application/json'
          }
        }
      );

      const data = await response.json();

      if (response.ok) {
        setReservas(data.data);
        setPagination(data.pagination);
      } else {
        console.error('Error:', data.error);
      }
    } catch (error) {
      console.error('Error de red:', error);
    } finally {
      setLoading(false);
    }
  };

  const cambiarFiltro = (campo, valor) => {
    setFiltros({
      ...filtros,
      [campo]: valor,
      page: 1 // Reset a página 1 al filtrar
    });
  };

  const cambiarPagina = (nuevaPagina) => {
    setFiltros({ ...filtros, page: nuevaPagina });
  };

  const obtenerColorEstado = (status) => {
    const colores = {
      pending: '#FFA500',
      confirmed: '#4CAF50',
      rejected: '#F44336',
      expired: '#9E9E9E'
    };
    return colores[status] || '#000';
  };

  const obtenerTextoEstado = (status) => {
    const textos = {
      pending: 'Pendiente',
      confirmed: 'Confirmada',
      rejected: 'Rechazada',
      expired: 'Expirada'
    };
    return textos[status] || status;
  };

  if (loading) {
    return <div className="loading">Cargando reservas...</div>;
  }

  return (
    <div className="panel-reservas">
      <h1>Gestión de Reservas</h1>

      {/* Filtros */}
      <div className="filtros">
        <div className="filtro">
          <label>Estado:</label>
          <select
            value={filtros.status}
            onChange={(e) => cambiarFiltro('status', e.target.value)}
          >
            <option value="">Todos</option>
            <option value="pending">Pendientes</option>
            <option value="confirmed">Confirmadas</option>
            <option value="rejected">Rechazadas</option>
          </select>
        </div>

        <div className="filtro">
          <label>Buscar:</label>
          <input
            type="text"
            placeholder="Nombre, email o número"
            value={filtros.search}
            onChange={(e) => cambiarFiltro('search', e.target.value)}
          />
        </div>

        <button onClick={cargarReservas} className="btn-refrescar">
          Refrescar
        </button>
      </div>

      {/* Tabla de Reservas */}
      <div className="tabla-reservas">
        <table>
          <thead>
            <tr>
              <th>Número</th>
              <th>Cliente</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Total</th>
              <th>Items</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {reservas.map((reserva) => (
              <tr key={reserva.id}>
                <td>
                  <strong>{reserva.reservation_number}</strong>
                </td>
                <td>{reserva.customer_name}</td>
                <td>{reserva.customer_email}</td>
                <td>{reserva.customer_phone}</td>
                <td>${parseFloat(reserva.total_amount).toLocaleString()}</td>
                <td>{reserva.items_count}</td>
                <td>
                  <span
                    className="badge-estado"
                    style={{
                      backgroundColor: obtenerColorEstado(reserva.status),
                      color: 'white',
                      padding: '5px 10px',
                      borderRadius: '4px'
                    }}
                  >
                    {obtenerTextoEstado(reserva.status)}
                  </span>
                </td>
                <td>{new Date(reserva.created_at).toLocaleDateString()}</td>
                <td>
                  <button
                    onClick={() => window.location.href = `/admin/reservas/${reserva.id}`}
                    className="btn-ver"
                  >
                    Ver Detalle
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {reservas.length === 0 && (
          <div className="sin-resultados">
            No se encontraron reservas
          </div>
        )}
      </div>

      {/* Paginación */}
      {pagination.pages > 1 && (
        <div className="paginacion">
          <button
            onClick={() => cambiarPagina(pagination.page - 1)}
            disabled={pagination.page === 1}
          >
            Anterior
          </button>

          <span>
            Página {pagination.page} de {pagination.pages}
            ({pagination.total} reservas)
          </span>

          <button
            onClick={() => cambiarPagina(pagination.page + 1)}
            disabled={pagination.page === pagination.pages}
          >
            Siguiente
          </button>
        </div>
      )}
    </div>
  );
}

export default ListaReservas;
```

---

### Componente: Detalle de Reserva

```jsx
import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';

function DetalleReserva() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [reserva, setReserva] = useState(null);
  const [loading, setLoading] = useState(true);
  const [procesando, setProcesando] = useState(false);
  const [adminNotes, setAdminNotes] = useState('');
  const [mostrarFormNotas, setMostrarFormNotas] = useState(false);

  const tokenAdmin = localStorage.getItem('admin_token');

  useEffect(() => {
    cargarReserva();
  }, [id]);

  const cargarReserva = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `https://tu-api.com/api/admin/reservations/${id}`,
        {
          headers: {
            'Authorization': `Bearer ${tokenAdmin}`,
            'Content-Type': 'application/json'
          }
        }
      );

      const data = await response.json();

      if (response.ok) {
        setReserva(data);
        setAdminNotes(data.admin_notes || '');
      } else {
        alert('Error: ' + data.error);
        navigate('/admin/reservas');
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  const confirmarReserva = async () => {
    if (!window.confirm('¿Confirmar esta reserva? Se descontará el stock de los productos.')) {
      return;
    }

    setProcesando(true);
    try {
      const response = await fetch(
        `https://tu-api.com/api/admin/reservations/${id}/confirm`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${tokenAdmin}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            admin_notes: adminNotes
          })
        }
      );

      const data = await response.json();

      if (response.ok) {
        alert(`Reserva confirmada exitosamente!\n\nSe ha enviado un email al cliente con las instrucciones.`);
        cargarReserva(); // Recargar datos
      } else {
        alert('Error: ' + data.error);
      }
    } catch (error) {
      alert('Error de conexión');
    } finally {
      setProcesando(false);
    }
  };

  const rechazarReserva = async () => {
    const motivo = window.prompt('Motivo del rechazo (será guardado en notas):');
    if (!motivo) return;

    setProcesando(true);
    try {
      const response = await fetch(
        `https://tu-api.com/api/admin/reservations/${id}/reject`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${tokenAdmin}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            admin_notes: motivo
          })
        }
      );

      const data = await response.json();

      if (response.ok) {
        alert('Reserva rechazada.\n\nRecuerda contactar al cliente manualmente.');
        cargarReserva(); // Recargar datos
      } else {
        alert('Error: ' + data.error);
      }
    } catch (error) {
      alert('Error de conexión');
    } finally {
      setProcesando(false);
    }
  };

  if (loading) {
    return <div className="loading">Cargando...</div>;
  }

  if (!reserva) {
    return <div>Reserva no encontrada</div>;
  }

  const esPendiente = reserva.status === 'pending';
  const esConfirmada = reserva.status === 'confirmed';
  const esRechazada = reserva.status === 'rejected';

  return (
    <div className="detalle-reserva">
      <div className="header">
        <button onClick={() => navigate('/admin/reservas')} className="btn-volver">
          ← Volver a lista
        </button>
        <h1>Reserva #{reserva.reservation_number}</h1>
        <span className={`estado-badge ${reserva.status}`}>
          {reserva.status.toUpperCase()}
        </span>
      </div>

      {/* Información del Cliente */}
      <div className="seccion">
        <h2>Información del Cliente</h2>
        <div className="info-grid">
          <div className="info-item">
            <strong>Nombre:</strong>
            <span>{reserva.customer_name}</span>
          </div>
          <div className="info-item">
            <strong>Email:</strong>
            <a href={`mailto:${reserva.customer_email}`}>{reserva.customer_email}</a>
          </div>
          <div className="info-item">
            <strong>Teléfono:</strong>
            <a href={`tel:${reserva.customer_phone}`}>{reserva.customer_phone}</a>
          </div>
          {reserva.shipping_address && (
            <>
              <div className="info-item">
                <strong>Dirección:</strong>
                <span>{reserva.shipping_address}</span>
              </div>
              <div className="info-item">
                <strong>Ciudad:</strong>
                <span>{reserva.shipping_city}</span>
              </div>
              <div className="info-item">
                <strong>Provincia:</strong>
                <span>{reserva.shipping_state}</span>
              </div>
            </>
          )}
        </div>
      </div>

      {/* Productos */}
      <div className="seccion">
        <h2>Productos ({reserva.items?.length || 0})</h2>
        <table className="tabla-productos">
          <thead>
            <tr>
              <th>Producto</th>
              <th>SKU</th>
              <th>Cantidad</th>
              <th>Precio Unit.</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {reserva.items?.map((item) => (
              <tr key={item.id}>
                <td>{item.product_name}</td>
                <td>{item.product_sku}</td>
                <td>{item.quantity}</td>
                <td>${parseFloat(item.price).toFixed(2)}</td>
                <td>${parseFloat(item.total).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan="4"><strong>TOTAL:</strong></td>
              <td><strong>${parseFloat(reserva.total_amount).toFixed(2)}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>

      {/* Notas del Cliente */}
      {reserva.notes && (
        <div className="seccion">
          <h2>Comentarios del Cliente</h2>
          <div className="notas-cliente">
            {reserva.notes}
          </div>
        </div>
      )}

      {/* Notas del Admin */}
      <div className="seccion">
        <h2>Notas Internas (Admin)</h2>
        {esPendiente ? (
          <textarea
            value={adminNotes}
            onChange={(e) => setAdminNotes(e.target.value)}
            placeholder="Instrucciones de pago, coordinar entrega, etc. (se enviará al cliente al confirmar)"
            rows={4}
            className="textarea-notas"
          />
        ) : (
          <div className="notas-admin">
            {reserva.admin_notes || 'Sin notas'}
          </div>
        )}
      </div>

      {/* Historial de Acciones */}
      {reserva.logs && reserva.logs.length > 0 && (
        <div className="seccion">
          <h2>Historial</h2>
          <div className="logs">
            {reserva.logs.map((log) => (
              <div key={log.id} className="log-item">
                <span className="log-fecha">
                  {new Date(log.created_at).toLocaleString()}
                </span>
                <span className="log-accion">{log.action}</span>
                <span className="log-detalles">{log.details}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Información Adicional */}
      <div className="seccion info-adicional">
        <div className="info-item">
          <strong>Creada:</strong>
          <span>{new Date(reserva.created_at).toLocaleString()}</span>
        </div>
        {reserva.confirmed_at && (
          <div className="info-item">
            <strong>Confirmada:</strong>
            <span>{new Date(reserva.confirmed_at).toLocaleString()}</span>
          </div>
        )}
      </div>

      {/* Botones de Acción */}
      <div className="acciones">
        {esPendiente && (
          <>
            <button
              onClick={confirmarReserva}
              disabled={procesando}
              className="btn-confirmar"
            >
              {procesando ? 'Procesando...' : '✓ Confirmar Reserva'}
            </button>

            <button
              onClick={rechazarReserva}
              disabled={procesando}
              className="btn-rechazar"
            >
              {procesando ? 'Procesando...' : '✗ Rechazar Reserva'}
            </button>
          </>
        )}

        {esConfirmada && (
          <div className="mensaje-confirmada">
            ✓ Esta reserva ya fue confirmada. El stock fue descontado y el cliente recibió un email.
          </div>
        )}

        {esRechazada && (
          <div className="mensaje-rechazada">
            ✗ Esta reserva fue rechazada. Recuerda contactar al cliente.
          </div>
        )}
      </div>
    </div>
  );
}

export default DetalleReserva;
```

---

## 🎨 CSS Sugerido

```css
/* Panel de Reservas */
.panel-reservas {
  padding: 20px;
}

.filtros {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  padding: 15px;
  background: #f5f5f5;
  border-radius: 8px;
}

.filtro {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filtro label {
  font-weight: bold;
  font-size: 14px;
}

.filtro select,
.filtro input {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

/* Tabla de Reservas */
.tabla-reservas {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tabla-reservas table {
  width: 100%;
  border-collapse: collapse;
}

.tabla-reservas th {
  background: #333;
  color: white;
  padding: 12px;
  text-align: left;
  font-weight: 600;
}

.tabla-reservas td {
  padding: 12px;
  border-bottom: 1px solid #eee;
}

.tabla-reservas tr:hover {
  background: #f9f9f9;
}

.badge-estado {
  display: inline-block;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: bold;
}

/* Paginación */
.paginacion {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
  padding: 15px;
}

.paginacion button {
  padding: 8px 16px;
  background: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.paginacion button:disabled {
  background: #ccc;
  cursor: not-allowed;
}

/* Detalle de Reserva */
.detalle-reserva {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 30px;
}

.estado-badge {
  padding: 8px 16px;
  border-radius: 4px;
  font-weight: bold;
  color: white;
}

.estado-badge.pending {
  background: #FFA500;
}

.estado-badge.confirmed {
  background: #4CAF50;
}

.estado-badge.rejected {
  background: #F44336;
}

.seccion {
  background: white;
  padding: 20px;
  margin-bottom: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-item strong {
  color: #666;
  font-size: 14px;
}

/* Tabla de Productos */
.tabla-productos {
  width: 100%;
  border-collapse: collapse;
}

.tabla-productos th {
  background: #f5f5f5;
  padding: 10px;
  text-align: left;
  border-bottom: 2px solid #ddd;
}

.tabla-productos td {
  padding: 10px;
  border-bottom: 1px solid #eee;
}

.tabla-productos tfoot td {
  font-weight: bold;
  background: #f5f5f5;
}

/* Notas */
.textarea-notas {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  resize: vertical;
}

.notas-cliente,
.notas-admin {
  padding: 12px;
  background: #f9f9f9;
  border-left: 4px solid #4CAF50;
  border-radius: 4px;
}

/* Logs */
.logs {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.log-item {
  padding: 10px;
  background: #f9f9f9;
  border-left: 3px solid #2196F3;
  border-radius: 4px;
  display: grid;
  grid-template-columns: auto auto 1fr;
  gap: 15px;
  align-items: center;
}

.log-fecha {
  color: #666;
  font-size: 12px;
}

.log-accion {
  font-weight: bold;
  color: #2196F3;
}

/* Acciones */
.acciones {
  display: flex;
  gap: 15px;
  margin-top: 30px;
  padding: 20px;
  background: #f5f5f5;
  border-radius: 8px;
}

.btn-confirmar {
  flex: 1;
  padding: 15px 30px;
  background: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
}

.btn-confirmar:hover {
  background: #45a049;
}

.btn-confirmar:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.btn-rechazar {
  flex: 1;
  padding: 15px 30px;
  background: #F44336;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
}

.btn-rechazar:hover {
  background: #da190b;
}

.btn-rechazar:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.mensaje-confirmada,
.mensaje-rechazada {
  flex: 1;
  padding: 15px;
  border-radius: 4px;
  text-align: center;
  font-weight: bold;
}

.mensaje-confirmada {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.mensaje-rechazada {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Botones generales */
.btn-volver,
.btn-ver,
.btn-refrescar {
  padding: 8px 16px;
  background: #2196F3;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.btn-volver:hover,
.btn-ver:hover,
.btn-refrescar:hover {
  background: #0b7dda;
}

/* Loading */
.loading {
  text-align: center;
  padding: 50px;
  font-size: 18px;
  color: #666;
}

.sin-resultados {
  text-align: center;
  padding: 50px;
  color: #999;
  font-style: italic;
}
```

---

## 📊 Flujo Completo de Trabajo

### 1. Cliente Crea Reserva
```
Cliente → Formulario frontend → POST /api/reservations
         ↓
Status: pending, Stock NO descontado
         ↓
Email enviado a cliente y admin
```

### 2. Admin Recibe Notificación
```
Admin recibe email → Entra al panel → /admin/reservations
      ↓
Filtra por "pending"
      ↓
Ve lista de reservas pendientes
```

### 3. Admin Revisa Detalle
```
Click en "Ver Detalle" → GET /api/admin/reservations/{id}
                              ↓
                        Ve productos, cantidades, cliente, notas
```

### 4A. Admin Confirma (Flujo Positivo)
```
Admin escribe notas → Click "Confirmar" → Confirma diálogo
              ↓
POST /api/admin/reservations/{id}/confirm
              ↓
Stock SE DESCUENTA, Status = confirmed
              ↓
Email enviado a cliente con instrucciones
              ↓
Admin ve mensaje de éxito
```

### 4B. Admin Rechaza (Flujo Negativo)
```
Admin escribe motivo → Click "Rechazar" → Confirma
              ↓
POST /api/admin/reservations/{id}/reject
              ↓
Stock NO se descuenta, Status = rejected
              ↓
Admin debe contactar al cliente manualmente
```

---

## 🚨 Manejo de Errores

### Stock Insuficiente al Confirmar

```javascript
// En el componente DetalleReserva
const confirmarReserva = async () => {
  try {
    const response = await fetch(`/api/admin/reservations/${id}/confirm`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${tokenAdmin}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ admin_notes: adminNotes })
    });

    const data = await response.json();

    if (response.ok) {
      alert('✓ Reserva confirmada exitosamente!');
      cargarReserva();
    } else if (response.status === 400 && data.error.includes('Insufficient stock')) {
      // Error de stock insuficiente
      alert(`❌ Error: ${data.error}\n\nDebes aumentar el stock del producto antes de confirmar esta reserva.`);
    } else {
      alert(`Error: ${data.error}`);
    }
  } catch (error) {
    alert('Error de conexión');
  }
};
```

---

## ✅ Checklist de Implementación Admin

- [ ] Crear ruta/página para lista de reservas (`/admin/reservations`)
- [ ] Implementar filtros (status, búsqueda)
- [ ] Implementar paginación
- [ ] Mostrar badges de estado con colores
- [ ] Crear ruta/página para detalle (`/admin/reservations/:id`)
- [ ] Mostrar información del cliente
- [ ] Mostrar tabla de productos
- [ ] Mostrar notas del cliente
- [ ] Agregar textarea para notas del admin
- [ ] Implementar botón "Confirmar" con confirmación
- [ ] Implementar botón "Rechazar" con prompt
- [ ] Mostrar historial de acciones (logs)
- [ ] Manejar errores de stock insuficiente
- [ ] Agregar loading states
- [ ] Agregar links para email y teléfono del cliente
- [ ] Deshabilitar botones si no es status pending

---

## 🎯 Tips Importantes

1. **Siempre validar el status** antes de mostrar botones de acción
2. **Pedir confirmación** antes de confirmar o rechazar
3. **Mostrar mensajes claros** al usuario sobre qué pasó
4. **Manejar el caso de stock insuficiente** con mensaje específico
5. **Guardar el token admin** en localStorage o contexto global
6. **Actualizar la lista** después de confirmar/rechazar
7. **Mostrar loading states** durante operaciones
8. **Agregar links** para contactar al cliente (email/teléfono)

---

## 📧 Emails que se Envían Automáticamente

### Al crear reserva:
- ✅ Email al **cliente**: "Reserva recibida"
- ✅ Email a **3 admins**: "Nueva reserva"
  - info@decohomesinrival.com.ar
  - federiconjg@gmail.com
  - Franconico25@gmail.com

### Al confirmar reserva:
- ✅ Email al **cliente**: "Reserva confirmada" con notas del admin

### Al rechazar reserva:
- ❌ NO se envía email automático
- Admin debe contactar manualmente

---

**¿Necesitas algo más específico?** Esta documentación incluye código completo listo para usar en React + ejemplos de CSS.
