# 💳 Documentación Mercado Pago - Frontend

## 🎯 Flujo Completo de Pago

```
1. Usuario agrega productos al carrito
2. Usuario hace checkout → Crea la orden
3. Frontend llama a crear preferencia de MP
4. Backend crea preferencia y retorna init_point
5. Frontend redirige a Mercado Pago (init_point)
6. Usuario paga en Mercado Pago
7. MP redirige de vuelta a tu sitio (success/failure/pending)
8. Backend recibe webhook de MP y actualiza orden
9. Frontend muestra estado del pago
```

---

## 📡 API Endpoints Disponibles

### Base URL
```
Producción: https://decohomesinrival.com.ar/ecommerce-api/public/api
Desarrollo: http://localhost/ecommerce-api/public/api
```

---

## 1️⃣ Crear Preferencia de Pago

### Endpoint
```http
POST /api/checkout/mercadopago/create-preference
```

### Headers
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

### Body
```json
{
  "order_id": 42
}
```

### Response 200 OK
```json
{
  "success": true,
  "preference_id": "409852850-a8b7c6d5-1234-5678-90ab-cdef12345678",
  "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=409852850-a8b7c6d5-1234-5678-90ab-cdef12345678",
  "sandbox_init_point": "https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=409852850-a8b7c6d5-1234-5678-90ab-cdef12345678"
}
```

**Campos:**
- `preference_id` - ID de la preferencia creada en MP
- `init_point` - URL para redirección a MP (PRODUCCIÓN)
- `sandbox_init_point` - URL para redirección a MP (PRUEBAS)

### Errores

**404 Not Found** - Orden no existe
```json
{
  "error": "Order not found"
}
```

**400 Bad Request** - Orden ya tiene pago aprobado
```json
{
  "error": "Order already has an approved payment"
}
```

**500 Internal Server Error** - Error al crear preferencia
```json
{
  "error": "Failed to create preference: {detalle}"
}
```

---

## 2️⃣ URLs de Retorno (Después del Pago)

Cuando el usuario termina de pagar en Mercado Pago, será redirigido a una de estas URLs:

### Success URL
```
https://decohomesinrival.com.ar/payment/success?collection_id={payment_id}&collection_status=approved&payment_id={payment_id}&status=approved&external_reference={order_id}&payment_type=credit_card&merchant_order_id={merchant_order_id}&preference_id={preference_id}&site_id=MLA&processing_mode=aggregator&merchant_account_id=null
```

### Failure URL
```
https://decohomesinrival.com.ar/payment/failure?collection_id={payment_id}&collection_status=rejected&payment_id={payment_id}&status=rejected&external_reference={order_id}&payment_type=credit_card&merchant_order_id={merchant_order_id}&preference_id={preference_id}&site_id=MLA&processing_mode=aggregator&merchant_account_id=null
```

### Pending URL
```
https://decohomesinrival.com.ar/payment/pending?collection_id={payment_id}&collection_status=pending&payment_id={payment_id}&status=pending&external_reference={order_id}&payment_type=ticket&merchant_order_id={merchant_order_id}&preference_id={preference_id}&site_id=MLA&processing_mode=aggregator&merchant_account_id=null
```

**Parámetros importantes en la URL:**
- `payment_id` - ID del pago en Mercado Pago
- `status` - Estado del pago (approved/rejected/pending)
- `external_reference` - El order_id de tu sistema
- `collection_status` - Estado de la colección

---

## 3️⃣ Consultar Estado del Pago

### Por Order ID

```http
GET /api/payments/{order_id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200 OK:**
```json
{
  "payment_id": "123456789",
  "order_id": 42,
  "order_number": "ORD20251026001",
  "status": "approved",
  "amount": "23400.00",
  "currency": "ARS",
  "payment_method": "visa",
  "payment_type": "credit_card",
  "payer_email": "cliente@email.com",
  "payer_name": "Juan Pérez",
  "created_at": "2025-10-26 15:30:00",
  "payment_data": {
    "id": 123456789,
    "status": "approved",
    "status_detail": "accredited",
    "payment_method_id": "visa",
    "payment_type_id": "credit_card",
    "installments": 1,
    "transaction_amount": 23400
  }
}
```

### Por Payment ID

```http
GET /api/payments/detail/{payment_id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response:** Igual que el anterior

---

## 4️⃣ Listar Todos Mis Pagos

```http
GET /api/payments
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200 OK:**
```json
{
  "total": 3,
  "payments": [
    {
      "payment_id": "123456789",
      "order_id": 42,
      "order_number": "ORD20251026001",
      "status": "approved",
      "amount": "23400.00",
      "currency": "ARS",
      "payment_method": "visa",
      "created_at": "2025-10-26 15:30:00"
    },
    {
      "payment_id": "987654321",
      "order_id": 40,
      "order_number": "ORD20251023002",
      "status": "approved",
      "amount": "15000.00",
      "currency": "ARS",
      "payment_method": "mastercard",
      "created_at": "2025-10-23 10:15:00"
    }
  ]
}
```

---

## 💻 Ejemplos de Código Frontend

### 🔷 JavaScript Vanilla - Flujo Completo

```javascript
// ============================================
// PASO 1: Crear la orden primero
// ============================================
async function createOrder(cartItems, customerData) {
  const token = localStorage.getItem('token');

  try {
    const response = await fetch('https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/create-order', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(customerData)
    });

    if (!response.ok) {
      throw new Error('Error al crear la orden');
    }

    const data = await response.json();
    console.log('Orden creada:', data.order);

    // Retornar el order_id para el siguiente paso
    return data.order.id;

  } catch (error) {
    console.error('Error:', error);
    alert('Error al crear la orden');
    return null;
  }
}

// ============================================
// PASO 2: Crear preferencia y redirigir a MP
// ============================================
async function payWithMercadoPago(orderId) {
  const token = localStorage.getItem('token');

  try {
    // Crear preferencia de pago
    const response = await fetch('https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Error al crear preferencia');
    }

    const data = await response.json();
    console.log('Preferencia creada:', data);

    // Redirigir a Mercado Pago
    // IMPORTANTE: Usar init_point para producción
    window.location.href = data.init_point;

  } catch (error) {
    console.error('Error:', error);
    alert('Error al procesar el pago: ' + error.message);
  }
}

// ============================================
// PASO 3: Función completa de checkout
// ============================================
async function handleCheckout(customerData) {
  // 1. Crear la orden
  const orderId = await createOrder(customerData);

  if (!orderId) {
    alert('No se pudo crear la orden');
    return;
  }

  // 2. Guardar order_id en localStorage (para recuperar después)
  localStorage.setItem('pending_order_id', orderId);

  // 3. Redirigir a Mercado Pago
  await payWithMercadoPago(orderId);
}

// ============================================
// PASO 4: En la página de éxito (success)
// ============================================
async function handlePaymentSuccess() {
  // Obtener parámetros de la URL
  const urlParams = new URLSearchParams(window.location.search);
  const paymentId = urlParams.get('payment_id');
  const status = urlParams.get('status');
  const orderId = urlParams.get('external_reference');

  console.log('Pago exitoso:', { paymentId, status, orderId });

  // Mostrar mensaje de éxito
  document.getElementById('payment-status').innerHTML = `
    <div class="success">
      <h2>¡Pago Aprobado!</h2>
      <p>Tu pago fue procesado exitosamente</p>
      <p>ID de Pago: ${paymentId}</p>
      <p>Orden: ${orderId}</p>
      <a href="/mis-ordenes">Ver mis órdenes</a>
    </div>
  `;

  // Limpiar carrito
  localStorage.removeItem('pending_order_id');

  // Opcional: Consultar detalles del pago
  const paymentDetails = await getPaymentDetails(orderId);
  console.log('Detalles del pago:', paymentDetails);
}

// ============================================
// PASO 5: Consultar detalles del pago
// ============================================
async function getPaymentDetails(orderId) {
  const token = localStorage.getItem('token');

  try {
    const response = await fetch(`https://decohomesinrival.com.ar/ecommerce-api/public/api/payments/${orderId}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (!response.ok) {
      throw new Error('Error al obtener detalles del pago');
    }

    const data = await response.json();
    return data;

  } catch (error) {
    console.error('Error:', error);
    return null;
  }
}
```

---

### ⚛️ React - Componente Completo

```javascript
import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';

const API_URL = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

// ============================================
// Componente: Botón de Pago
// ============================================
function CheckoutButton({ orderId }) {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handlePayment = async () => {
    setLoading(true);
    const token = localStorage.getItem('token');

    try {
      const response = await fetch(`${API_URL}/checkout/mercadopago/create-preference`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId })
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error);
      }

      const data = await response.json();

      // Guardar order_id para recuperar después
      localStorage.setItem('pending_order_id', orderId);

      // Redirigir a Mercado Pago
      window.location.href = data.init_point;

    } catch (error) {
      console.error('Error:', error);
      alert('Error al procesar el pago: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <button
      onClick={handlePayment}
      disabled={loading}
      className="btn-pay-mp"
    >
      {loading ? 'Procesando...' : '💳 Pagar con Mercado Pago'}
    </button>
  );
}

// ============================================
// Página: Pago Exitoso
// ============================================
function PaymentSuccess() {
  const [searchParams] = useSearchParams();
  const [paymentDetails, setPaymentDetails] = useState(null);
  const [loading, setLoading] = useState(true);

  const paymentId = searchParams.get('payment_id');
  const status = searchParams.get('status');
  const orderId = searchParams.get('external_reference');

  useEffect(() => {
    if (orderId) {
      fetchPaymentDetails(orderId);
    }
  }, [orderId]);

  const fetchPaymentDetails = async (orderId) => {
    const token = localStorage.getItem('token');

    try {
      const response = await fetch(`${API_URL}/payments/${orderId}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        const data = await response.json();
        setPaymentDetails(data);
      }
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="loading">Cargando detalles del pago...</div>;
  }

  return (
    <div className="payment-success-container">
      <div className="success-card">
        <div className="success-icon">✅</div>
        <h1>¡Pago Aprobado!</h1>
        <p>Tu pago fue procesado exitosamente</p>

        {paymentDetails && (
          <div className="payment-details">
            <div className="detail-row">
              <span>Orden:</span>
              <strong>{paymentDetails.order_number}</strong>
            </div>
            <div className="detail-row">
              <span>ID de Pago:</span>
              <strong>{paymentDetails.payment_id}</strong>
            </div>
            <div className="detail-row">
              <span>Monto:</span>
              <strong>${paymentDetails.amount}</strong>
            </div>
            <div className="detail-row">
              <span>Método:</span>
              <strong>{paymentDetails.payment_method}</strong>
            </div>
            <div className="detail-row">
              <span>Estado:</span>
              <span className="status-approved">Aprobado</span>
            </div>
          </div>
        )}

        <div className="actions">
          <a href="/mis-ordenes" className="btn-primary">
            Ver mis órdenes
          </a>
          <a href="/" className="btn-secondary">
            Volver al inicio
          </a>
        </div>
      </div>
    </div>
  );
}

// ============================================
// Página: Pago Rechazado
// ============================================
function PaymentFailure() {
  const [searchParams] = useSearchParams();
  const orderId = searchParams.get('external_reference');

  return (
    <div className="payment-failure-container">
      <div className="failure-card">
        <div className="failure-icon">❌</div>
        <h1>Pago Rechazado</h1>
        <p>Lo sentimos, no pudimos procesar tu pago</p>

        <div className="failure-reasons">
          <h3>Posibles razones:</h3>
          <ul>
            <li>Fondos insuficientes</li>
            <li>Datos de tarjeta incorrectos</li>
            <li>La tarjeta fue rechazada por el banco</li>
            <li>Límite de compra excedido</li>
          </ul>
        </div>

        <div className="actions">
          <a href={`/checkout/${orderId}`} className="btn-primary">
            Intentar nuevamente
          </a>
          <a href="/cart" className="btn-secondary">
            Volver al carrito
          </a>
        </div>
      </div>
    </div>
  );
}

// ============================================
// Página: Pago Pendiente
// ============================================
function PaymentPending() {
  const [searchParams] = useSearchParams();
  const paymentId = searchParams.get('payment_id');
  const orderId = searchParams.get('external_reference');

  return (
    <div className="payment-pending-container">
      <div className="pending-card">
        <div className="pending-icon">⏳</div>
        <h1>Pago Pendiente</h1>
        <p>Tu pago está siendo procesado</p>

        <div className="pending-info">
          <p>
            Recibirás una confirmación por email cuando el pago sea acreditado.
            Esto puede tardar hasta 2 días hábiles.
          </p>
          <p>
            <strong>ID de Pago:</strong> {paymentId}
          </p>
        </div>

        <div className="actions">
          <a href="/mis-ordenes" className="btn-primary">
            Ver mis órdenes
          </a>
          <a href="/" className="btn-secondary">
            Volver al inicio
          </a>
        </div>
      </div>
    </div>
  );
}

export { CheckoutButton, PaymentSuccess, PaymentFailure, PaymentPending };
```

---

### 🎨 CSS de Ejemplo

```css
/* Botón de Mercado Pago */
.btn-pay-mp {
  background: #009ee3;
  color: white;
  border: none;
  padding: 15px 30px;
  font-size: 18px;
  font-weight: bold;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: background 0.3s;
}

.btn-pay-mp:hover {
  background: #0089c9;
}

.btn-pay-mp:disabled {
  background: #ccc;
  cursor: not-allowed;
}

/* Páginas de resultado */
.payment-success-container,
.payment-failure-container,
.payment-pending-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: #f5f5f5;
}

.success-card,
.failure-card,
.pending-card {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  max-width: 600px;
  width: 100%;
  text-align: center;
}

.success-icon {
  font-size: 80px;
  margin-bottom: 20px;
}

.failure-icon {
  font-size: 80px;
  margin-bottom: 20px;
}

.pending-icon {
  font-size: 80px;
  margin-bottom: 20px;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

h1 {
  color: #333;
  margin-bottom: 10px;
}

.payment-details {
  background: #f9f9f9;
  padding: 20px;
  border-radius: 8px;
  margin: 30px 0;
  text-align: left;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #eee;
}

.detail-row:last-child {
  border-bottom: none;
}

.status-approved {
  color: #28a745;
  font-weight: bold;
}

.actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
  justify-content: center;
}

.btn-primary {
  background: #007bff;
  color: white;
  padding: 12px 24px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 500;
}

.btn-primary:hover {
  background: #0056b3;
}

.btn-secondary {
  background: white;
  color: #007bff;
  border: 2px solid #007bff;
  padding: 12px 24px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 500;
}

.btn-secondary:hover {
  background: #f0f0f0;
}

.failure-reasons {
  background: #fff3cd;
  padding: 20px;
  border-radius: 8px;
  margin: 20px 0;
  text-align: left;
}

.failure-reasons ul {
  margin: 10px 0 0 20px;
}

.failure-reasons li {
  margin: 5px 0;
}
```

---

## 🔄 Estados de Pago

| Estado | Descripción | Acción |
|--------|-------------|--------|
| `approved` | Pago aprobado | Mostrar éxito, enviar orden |
| `pending` | Pago pendiente | Esperar confirmación |
| `in_process` | En proceso | Esperar confirmación |
| `rejected` | Pago rechazado | Permitir reintentar |
| `cancelled` | Pago cancelado | Volver al carrito |
| `refunded` | Pago reembolsado | Notificar al usuario |

---

## 📋 Checklist de Implementación

### Backend ✅ (Ya está hecho)
- [x] MercadoPagoService creado
- [x] MercadoPagoController creado
- [x] PaymentController creado
- [x] Rutas configuradas
- [x] Tablas en base de datos
- [x] Credenciales en .env

### Frontend (Por hacer)
- [ ] Página de checkout
- [ ] Botón "Pagar con Mercado Pago"
- [ ] Página de éxito (/payment/success)
- [ ] Página de error (/payment/failure)
- [ ] Página de pendiente (/payment/pending)
- [ ] Ver detalles del pago
- [ ] Listar mis pagos

---

## 🚀 Flujo Completo Resumido

```javascript
// 1. Usuario hace checkout
const orderId = await createOrder(customerData);

// 2. Crear preferencia MP y obtener init_point
const preference = await fetch('/api/checkout/mercadopago/create-preference', {
  body: JSON.stringify({ order_id: orderId })
});

// 3. Redirigir a Mercado Pago
window.location.href = preference.init_point;

// 4. Usuario paga en MP → MP redirige a /payment/success

// 5. En success page: mostrar detalles
const payment = await fetch(`/api/payments/${orderId}`);
console.log(payment); // { status: 'approved', amount: '23400.00', ... }
```

---

## ⚠️ IMPORTANTE

### URLs de Retorno
Debes configurar estas rutas en tu frontend:
- `/payment/success`
- `/payment/failure`
- `/payment/pending`

### Webhook
El webhook ya está configurado en el backend:
```
POST /api/webhooks/mercadopago
```

**NO necesitas hacer nada en el frontend para el webhook**, Mercado Pago lo llama directamente.

---

## 🎯 ¡Todo Listo!

El backend de Mercado Pago está **100% implementado y funcionando**. Solo necesitas:

1. ✅ Crear las páginas de resultado (success/failure/pending)
2. ✅ Implementar el botón de pago
3. ✅ Probar el flujo completo

**¿Necesitas ayuda con alguna parte específica del frontend?** 🚀
