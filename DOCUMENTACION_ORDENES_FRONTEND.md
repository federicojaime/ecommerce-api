# Documentación de Endpoints de Órdenes/Compras - Frontend

**Última actualización:** 2025-10-26
**Backend API Base URL:** `https://decohomesinrival.com.ar/ecommerce-api/public/api`

---

## 📋 Índice

1. [Obtener Todas Mis Órdenes](#1-obtener-todas-mis-órdenes)
2. [Obtener Detalles de una Orden](#2-obtener-detalles-de-una-orden)
3. [Cancelar una Orden](#3-cancelar-una-orden)
4. [Ejemplos de Uso en React](#ejemplos-de-uso-en-react)
5. [Servicio Completo de Órdenes](#servicio-completo-de-órdenes)
6. [Componente de Lista de Órdenes](#componente-de-lista-de-órdenes)

---

## 1. Obtener Todas Mis Órdenes

### Endpoint
```
GET /orders
```

### Headers
```
Authorization: Bearer {token}
```

### Response

```json
[
  {
    "id": 25,
    "order_number": "ORD20251026XXXX",
    "status": "paid",
    "customer_name": "Federico Jaime",
    "customer_email": "federiconj@gmail.com",
    "customer_phone": "+542657218215",
    "shipping_address": "Catamarca 2126",
    "shipping_city": "San Luis",
    "payment_method": "mercadopago",
    "subtotal": "22.40",
    "tax_amount": "0.00",
    "shipping_amount": "0.00",
    "discount_amount": "0.00",
    "total_amount": "22.40",
    "created_at": "2025-10-26 04:41:35",
    "updated_at": "2025-10-26 04:41:37"
  },
  {
    "id": 24,
    "order_number": "ORD20251025YYYY",
    "status": "pending",
    "customer_name": "Federico Jaime",
    "total_amount": "150.00",
    "created_at": "2025-10-25 10:30:00"
  }
]
```

### Estados de Orden Posibles

- `pending` - Orden creada, esperando pago
- `paid` - Pago aprobado
- `processing` - En proceso de preparación
- `shipped` - Enviada
- `delivered` - Entregada
- `cancelled` - Cancelada
- `refunded` - Reembolsada

### Ejemplo JavaScript

```javascript
const getMyOrders = async () => {
  try {
    const response = await fetch(
      'https://decohomesinrival.com.ar/ecommerce-api/public/api/orders',
      {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      }
    );

    const orders = await response.json();
    console.log('Mis órdenes:', orders);
    return orders;

  } catch (error) {
    console.error('Error al obtener órdenes:', error);
    throw error;
  }
};
```

---

## 2. Obtener Detalles de una Orden

### Endpoint
```
GET /orders/{order_id}
```

### Headers
```
Authorization: Bearer {token}
```

### Response

```json
{
  "order": {
    "id": 25,
    "order_number": "ORD20251026XXXX",
    "status": "paid",
    "customer_id": 2,
    "customer_name": "Federico Jaime",
    "customer_email": "federiconj@gmail.com",
    "customer_phone": "+542657218215",
    "shipping_address": "Catamarca 2126",
    "shipping_city": "San Luis",
    "shipping_state": "San Luis",
    "shipping_zip_code": "5700",
    "billing_address": "Catamarca 2126",
    "payment_method": "mercadopago",
    "subtotal": "22.40",
    "tax_amount": "0.00",
    "shipping_amount": "0.00",
    "discount_amount": "0.00",
    "total_amount": "22.40",
    "notes": "Preference ID: 409852850-0ebc3667-396e-4733-a6d6-bddcbf83d620",
    "created_at": "2025-10-26 04:41:35",
    "updated_at": "2025-10-26 04:41:37"
  },
  "items": [
    {
      "id": 45,
      "order_id": 25,
      "product_id": 35,
      "product_name": "Placa PS 94 M",
      "product_sku": "SKU123",
      "quantity": 2,
      "price": "11.20",
      "total": "22.40"
    }
  ],
  "payment": {
    "id": 1,
    "order_id": 25,
    "payment_id": "130755029267",
    "payment_method": "account_money",
    "payment_type": "account_money",
    "payment_status": "approved",
    "amount": "22.40",
    "currency": "ARS",
    "payer_email": "federiconj@gmail.com",
    "payer_name": "Federico Jaime",
    "created_at": "2025-10-26 04:41:37"
  }
}
```

### Ejemplo JavaScript

```javascript
const getOrderDetails = async (orderId) => {
  try {
    const response = await fetch(
      `https://decohomesinrival.com.ar/ecommerce-api/public/api/orders/${orderId}`,
      {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      }
    );

    if (!response.ok) {
      throw new Error('Orden no encontrada');
    }

    const orderDetails = await response.json();
    console.log('Detalles de la orden:', orderDetails);
    return orderDetails;

  } catch (error) {
    console.error('Error al obtener detalles:', error);
    throw error;
  }
};
```

---

## 3. Cancelar una Orden

### Endpoint
```
POST /orders/{order_id}/cancel
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body (Opcional)

```json
{
  "reason": "Ya no necesito el producto"
}
```

### Response

```json
{
  "message": "Order cancelled successfully",
  "order_id": 25,
  "status": "cancelled"
}
```

### Restricciones

- Solo se pueden cancelar órdenes con estado `pending` o `paid`
- No se pueden cancelar órdenes que ya están `shipped` o `delivered`

### Ejemplo JavaScript

```javascript
const cancelOrder = async (orderId, reason = '') => {
  try {
    const response = await fetch(
      `https://decohomesinrival.com.ar/ecommerce-api/public/api/orders/${orderId}/cancel`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ reason })
      }
    );

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.error || 'Error al cancelar orden');
    }

    console.log('Orden cancelada:', result);
    return result;

  } catch (error) {
    console.error('Error al cancelar orden:', error);
    throw error;
  }
};
```

---

## Ejemplos de Uso en React

### Servicio Completo de Órdenes

```javascript
// services/orderService.js
const API_BASE = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

export const orderService = {
  // Obtener todas las órdenes del usuario
  getMyOrders: async () => {
    const response = await fetch(`${API_BASE}/orders`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    });

    if (!response.ok) {
      throw new Error('Error al obtener órdenes');
    }

    return await response.json();
  },

  // Obtener detalles de una orden específica
  getOrderDetails: async (orderId) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    });

    if (!response.ok) {
      throw new Error('Orden no encontrada');
    }

    return await response.json();
  },

  // Cancelar una orden
  cancelOrder: async (orderId, reason = '') => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/cancel`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ reason })
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.error || 'Error al cancelar orden');
    }

    return result;
  },

  // Filtrar órdenes por estado
  getOrdersByStatus: async (status) => {
    const orders = await orderService.getMyOrders();
    return orders.filter(order => order.status === status);
  },

  // Obtener órdenes pendientes
  getPendingOrders: async () => {
    return await orderService.getOrdersByStatus('pending');
  },

  // Obtener órdenes pagadas
  getPaidOrders: async () => {
    return await orderService.getOrdersByStatus('paid');
  },

  // Obtener órdenes enviadas
  getShippedOrders: async () => {
    return await orderService.getOrdersByStatus('shipped');
  }
};
```

---

## Componente de Lista de Órdenes

```jsx
// components/MyOrders.jsx
import { useState, useEffect } from 'react';
import { orderService } from '../services/orderService';

const MyOrders = () => {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all'); // all, pending, paid, shipped, etc.

  useEffect(() => {
    loadOrders();
  }, []);

  const loadOrders = async () => {
    try {
      setLoading(true);
      const data = await orderService.getMyOrders();
      setOrders(data);
    } catch (error) {
      console.error('Error al cargar órdenes:', error);
      alert('Error al cargar órdenes');
    } finally {
      setLoading(false);
    }
  };

  const handleCancelOrder = async (orderId) => {
    if (!confirm('¿Estás seguro de cancelar esta orden?')) {
      return;
    }

    try {
      await orderService.cancelOrder(orderId, 'Cancelado por el usuario');
      alert('Orden cancelada exitosamente');
      loadOrders(); // Recargar lista
    } catch (error) {
      alert(error.message);
    }
  };

  const getStatusBadge = (status) => {
    const badges = {
      pending: { text: 'Pendiente', color: 'orange' },
      paid: { text: 'Pagado', color: 'green' },
      processing: { text: 'Procesando', color: 'blue' },
      shipped: { text: 'Enviado', color: 'purple' },
      delivered: { text: 'Entregado', color: 'teal' },
      cancelled: { text: 'Cancelado', color: 'red' },
      refunded: { text: 'Reembolsado', color: 'gray' }
    };

    const badge = badges[status] || { text: status, color: 'gray' };

    return (
      <span className={`badge badge-${badge.color}`}>
        {badge.text}
      </span>
    );
  };

  const filteredOrders = filter === 'all'
    ? orders
    : orders.filter(order => order.status === filter);

  if (loading) {
    return <div>Cargando órdenes...</div>;
  }

  return (
    <div className="my-orders">
      <h1>Mis Compras</h1>
      <p>Historial y estado de tus órdenes</p>

      {/* Filtros */}
      <div className="filters">
        <button onClick={() => setFilter('all')} className={filter === 'all' ? 'active' : ''}>
          Todas
        </button>
        <button onClick={() => setFilter('pending')} className={filter === 'pending' ? 'active' : ''}>
          Pendientes
        </button>
        <button onClick={() => setFilter('paid')} className={filter === 'paid' ? 'active' : ''}>
          Pagadas
        </button>
        <button onClick={() => setFilter('shipped')} className={filter === 'shipped' ? 'active' : ''}>
          Enviadas
        </button>
        <button onClick={() => setFilter('delivered')} className={filter === 'delivered' ? 'active' : ''}>
          Entregadas
        </button>
      </div>

      {/* Lista de órdenes */}
      {filteredOrders.length === 0 ? (
        <div className="empty-state">
          <p>No tienes órdenes aún</p>
          <button onClick={() => window.location.href = '/productos'}>
            Explorar Productos
          </button>
        </div>
      ) : (
        <div className="orders-list">
          {filteredOrders.map(order => (
            <div key={order.id} className="order-card">
              <div className="order-header">
                <div>
                  <h3>Orden #{order.order_number}</h3>
                  <p className="order-date">
                    {new Date(order.created_at).toLocaleDateString('es-AR', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric'
                    })}
                  </p>
                </div>
                <div>
                  {getStatusBadge(order.status)}
                </div>
              </div>

              <div className="order-details">
                <p><strong>Total:</strong> ${parseFloat(order.total_amount).toFixed(2)}</p>
                <p><strong>Método de pago:</strong> {order.payment_method}</p>
                <p><strong>Dirección:</strong> {order.shipping_address}, {order.shipping_city}</p>
              </div>

              <div className="order-actions">
                <button onClick={() => window.location.href = `/orders/${order.id}`}>
                  Ver Detalles
                </button>

                {(order.status === 'pending' || order.status === 'paid') && (
                  <button
                    onClick={() => handleCancelOrder(order.id)}
                    className="btn-danger"
                  >
                    Cancelar Orden
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default MyOrders;
```

---

## Componente de Detalles de Orden

```jsx
// components/OrderDetails.jsx
import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { orderService } from '../services/orderService';

const OrderDetails = () => {
  const { id } = useParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadOrderDetails();
  }, [id]);

  const loadOrderDetails = async () => {
    try {
      setLoading(true);
      const data = await orderService.getOrderDetails(id);
      setOrder(data);
    } catch (error) {
      console.error('Error al cargar detalles:', error);
      alert('Orden no encontrada');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div>Cargando detalles...</div>;
  }

  if (!order) {
    return <div>Orden no encontrada</div>;
  }

  return (
    <div className="order-details">
      <h1>Detalles de Orden #{order.order.order_number}</h1>

      {/* Estado */}
      <div className="order-status">
        <h2>Estado: {order.order.status}</h2>
        <p>Creada: {new Date(order.order.created_at).toLocaleString('es-AR')}</p>
      </div>

      {/* Información del Cliente */}
      <div className="customer-info">
        <h3>Información del Cliente</h3>
        <p><strong>Nombre:</strong> {order.order.customer_name}</p>
        <p><strong>Email:</strong> {order.order.customer_email}</p>
        <p><strong>Teléfono:</strong> {order.order.customer_phone}</p>
      </div>

      {/* Dirección de Envío */}
      <div className="shipping-info">
        <h3>Dirección de Envío</h3>
        <p>{order.order.shipping_address}</p>
        <p>{order.order.shipping_city}, {order.order.shipping_state} {order.order.shipping_zip_code}</p>
      </div>

      {/* Items de la Orden */}
      <div className="order-items">
        <h3>Productos</h3>
        <table>
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
            {order.items.map(item => (
              <tr key={item.id}>
                <td>{item.product_name}</td>
                <td>{item.product_sku}</td>
                <td>{item.quantity}</td>
                <td>${parseFloat(item.price).toFixed(2)}</td>
                <td>${parseFloat(item.total).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Resumen de Pago */}
      <div className="payment-summary">
        <h3>Resumen</h3>
        <p><strong>Subtotal:</strong> ${parseFloat(order.order.subtotal).toFixed(2)}</p>
        {parseFloat(order.order.discount_amount) > 0 && (
          <p><strong>Descuento:</strong> -${parseFloat(order.order.discount_amount).toFixed(2)}</p>
        )}
        {parseFloat(order.order.tax_amount) > 0 && (
          <p><strong>IVA:</strong> ${parseFloat(order.order.tax_amount).toFixed(2)}</p>
        )}
        {parseFloat(order.order.shipping_amount) > 0 && (
          <p><strong>Envío:</strong> ${parseFloat(order.order.shipping_amount).toFixed(2)}</p>
        )}
        <h3><strong>Total:</strong> ${parseFloat(order.order.total_amount).toFixed(2)}</h3>
      </div>

      {/* Información del Pago */}
      {order.payment && (
        <div className="payment-info">
          <h3>Información del Pago</h3>
          <p><strong>ID de Pago:</strong> {order.payment.payment_id}</p>
          <p><strong>Método:</strong> {order.payment.payment_method}</p>
          <p><strong>Estado:</strong> {order.payment.payment_status}</p>
          <p><strong>Monto:</strong> ${parseFloat(order.payment.amount).toFixed(2)} {order.payment.currency}</p>
        </div>
      )}

      {/* Acciones */}
      <div className="order-actions">
        <button onClick={() => window.history.back()}>
          Volver a Mis Compras
        </button>

        {(order.order.status === 'pending' || order.order.status === 'paid') && (
          <button
            onClick={async () => {
              if (confirm('¿Cancelar esta orden?')) {
                await orderService.cancelOrder(order.order.id);
                loadOrderDetails();
              }
            }}
            className="btn-danger"
          >
            Cancelar Orden
          </button>
        )}
      </div>
    </div>
  );
};

export default OrderDetails;
```

---

## Página de Success de Mercado Pago

```jsx
// components/CheckoutSuccess.jsx
import { useEffect, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { orderService } from '../services/orderService';

const CheckoutSuccess = () => {
  const [searchParams] = useSearchParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);

  const orderId = searchParams.get('external_reference');
  const paymentId = searchParams.get('payment_id');
  const status = searchParams.get('status');

  useEffect(() => {
    // Limpiar carrito local
    localStorage.removeItem('cart');
    localStorage.removeItem('pending_order_id');

    // Cargar detalles de la orden
    if (orderId) {
      loadOrderDetails();
    }
  }, [orderId]);

  const loadOrderDetails = async () => {
    try {
      const data = await orderService.getOrderDetails(orderId);
      setOrder(data);
    } catch (error) {
      console.error('Error al cargar orden:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div>Cargando detalles de tu compra...</div>;
  }

  return (
    <div className="checkout-success">
      <div className="success-icon">✅</div>
      <h1>¡Pago Exitoso!</h1>
      <p>Tu orden ha sido procesada correctamente</p>

      {order && (
        <div className="order-summary">
          <h2>Orden #{order.order.order_number}</h2>
          <p><strong>Total pagado:</strong> ${parseFloat(order.order.total_amount).toFixed(2)}</p>
          <p><strong>ID de Pago:</strong> {paymentId}</p>
          <p><strong>Estado:</strong> {status}</p>
        </div>
      )}

      <div className="success-actions">
        <Link to={`/orders/${orderId}`} className="btn-primary">
          Ver Detalles de la Orden
        </Link>
        <Link to="/orders" className="btn-secondary">
          Ver Todas Mis Órdenes
        </Link>
        <Link to="/productos" className="btn-tertiary">
          Seguir Comprando
        </Link>
      </div>

      <div className="next-steps">
        <h3>Próximos pasos:</h3>
        <ol>
          <li>Recibirás un email de confirmación a {order?.order.customer_email}</li>
          <li>Prepararemos tu pedido en las próximas 24-48 horas</li>
          <li>Te notificaremos cuando tu pedido sea enviado</li>
        </ol>
      </div>
    </div>
  );
};

export default CheckoutSuccess;
```

---

## Rutas en React Router

```jsx
// App.jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import MyOrders from './components/MyOrders';
import OrderDetails from './components/OrderDetails';
import CheckoutSuccess from './components/CheckoutSuccess';
import CheckoutFailure from './components/CheckoutFailure';
import CheckoutPending from './components/CheckoutPending';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Órdenes */}
        <Route path="/orders" element={<MyOrders />} />
        <Route path="/orders/:id" element={<OrderDetails />} />

        {/* Checkout callbacks */}
        <Route path="/checkout/success" element={<CheckoutSuccess />} />
        <Route path="/checkout/failure" element={<CheckoutFailure />} />
        <Route path="/checkout/pending" element={<CheckoutPending />} />

        {/* ... otras rutas */}
      </Routes>
    </BrowserRouter>
  );
}
```

---

## Testing con cURL

### Obtener todas las órdenes
```bash
curl -X GET https://decohomesinrival.com.ar/ecommerce-api/public/api/orders \
  -H "Authorization: Bearer {token}"
```

### Obtener detalles de una orden
```bash
curl -X GET https://decohomesinrival.com.ar/ecommerce-api/public/api/orders/25 \
  -H "Authorization: Bearer {token}"
```

### Cancelar una orden
```bash
curl -X POST https://decohomesinrival.com.ar/ecommerce-api/public/api/orders/25/cancel \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Ya no lo necesito"}'
```

---

## Resumen de Endpoints

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| GET | `/orders` | Lista todas las órdenes del usuario | ✅ |
| GET | `/orders/{id}` | Detalles de una orden específica | ✅ |
| POST | `/orders/{id}/cancel` | Cancelar una orden | ✅ |

---

**Notas Importantes:**

1. Todos los endpoints requieren autenticación con JWT
2. Solo puedes ver tus propias órdenes
3. Las órdenes no se pueden eliminar, solo cancelar
4. El webhook de Mercado Pago actualiza automáticamente el estado de las órdenes
5. Las URLs de retorno de Mercado Pago apuntan a tu dominio de producción

---

**¿Necesitas más endpoints?** Revisa la documentación de administración de órdenes para endpoints de admin.
