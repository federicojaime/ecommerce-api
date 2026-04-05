# Documentación de Reservas - Frontend

## Descripción General

Sistema de reservas que permite a los clientes crear reservas de productos sin pago inmediato. El flujo completo es:

1. **Cliente crea reserva** (frontend público) → NO se descuenta stock
2. **Admin recibe email de notificación** con los detalles
3. **Admin confirma o rechaza** desde el panel administrativo
4. **Stock se descuenta SOLO cuando admin confirma**
5. **Cliente recibe recibo** por email al confirmarse

---

## Endpoint Público (No requiere autenticación)

### POST /api/reservations
Crear una nueva reserva.

**URL:** `POST https://tu-api.com/api/reservations`

**Headers:**
```json
{
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "customer_name": "Juan Pérez",
  "customer_email": "juan@example.com",
  "customer_phone": "+54 9 11 1234-5678",
  "shipping_address": "Av. Corrientes 1234",
  "shipping_city": "Buenos Aires",
  "shipping_state": "CABA",
  "shipping_zip_code": "C1043",
  "notes": "Prefiero entrega por la mañana",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "price": 15000.00
    },
    {
      "product_id": 5,
      "quantity": 1,
      "price": 8500.00
    }
  ]
}
```

**Campos Obligatorios:**
- `customer_name` (string): Nombre completo del cliente
- `customer_email` (string): Email válido del cliente
- `customer_phone` (string): Teléfono de contacto
- `items` (array): Array de productos con:
  - `product_id` (int): ID del producto
  - `quantity` (int): Cantidad deseada
  - `price` (decimal): Precio unitario

**Campos Opcionales:**
- `shipping_address` (string): Dirección de envío
- `shipping_city` (string): Ciudad
- `shipping_state` (string): Provincia/Estado
- `shipping_zip_code` (string): Código postal
- `notes` (text): Comentarios del cliente
- `discount_amount` (decimal): Descuento aplicado
- `shipping_amount` (decimal): Costo de envío
- `tax_amount` (decimal): Impuestos

**Respuesta Exitosa (201):**
```json
{
  "message": "Reservation created successfully",
  "reservation_id": 15,
  "reservation_number": "RES20251106A3F2",
  "status": "pending",
  "total": 38500.00
}
```

**Errores Posibles:**

**400 Bad Request - Campos faltantes:**
```json
{
  "error": "Missing required fields: customer_name, customer_email, customer_phone, items"
}
```

**400 Bad Request - Email inválido:**
```json
{
  "error": "Invalid email format"
}
```

**400 Bad Request - Sin items:**
```json
{
  "error": "Items array cannot be empty"
}
```

**404 Not Found - Producto no existe:**
```json
{
  "error": "Product with ID 123 not found or inactive"
}
```

**500 Internal Server Error:**
```json
{
  "error": "Error creating reservation: [detalle del error]"
}
```

---

## Ejemplo de Uso con JavaScript

### Crear Reserva desde el Frontend

```javascript
async function createReservation(reservationData) {
  try {
    const response = await fetch('https://tu-api.com/api/reservations', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(reservationData)
    });

    const data = await response.json();

    if (response.ok) {
      // Reserva creada exitosamente
      console.log('Reserva creada:', data.reservation_number);
      console.log('ID:', data.reservation_id);

      // Mostrar mensaje al usuario
      alert(`¡Reserva #${data.reservation_number} creada exitosamente!
             Te contactaremos pronto para confirmar tu pedido.`);

      // Redirigir a página de confirmación
      window.location.href = `/reservation-success?number=${data.reservation_number}`;

    } else {
      // Error en la creación
      console.error('Error:', data.error);
      alert(`Error al crear reserva: ${data.error}`);
    }

  } catch (error) {
    console.error('Network error:', error);
    alert('Error de conexión. Por favor, intenta nuevamente.');
  }
}

// Ejemplo de uso con datos del formulario
const reservationData = {
  customer_name: document.getElementById('name').value,
  customer_email: document.getElementById('email').value,
  customer_phone: document.getElementById('phone').value,
  shipping_address: document.getElementById('address').value,
  shipping_city: document.getElementById('city').value,
  shipping_state: document.getElementById('state').value,
  shipping_zip_code: document.getElementById('zip').value,
  notes: document.getElementById('notes').value,
  items: cartItems.map(item => ({
    product_id: item.id,
    quantity: item.quantity,
    price: item.price
  }))
};

createReservation(reservationData);
```

### Validación de Formulario Antes de Enviar

```javascript
function validateReservationForm(data) {
  const errors = [];

  // Validar nombre
  if (!data.customer_name || data.customer_name.trim().length < 3) {
    errors.push('El nombre debe tener al menos 3 caracteres');
  }

  // Validar email
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!data.customer_email || !emailRegex.test(data.customer_email)) {
    errors.push('Email inválido');
  }

  // Validar teléfono
  if (!data.customer_phone || data.customer_phone.trim().length < 8) {
    errors.push('Teléfono inválido');
  }

  // Validar items
  if (!data.items || data.items.length === 0) {
    errors.push('Debes agregar al menos un producto');
  }

  // Validar cada item
  data.items?.forEach((item, index) => {
    if (!item.product_id || item.quantity <= 0 || item.price <= 0) {
      errors.push(`Producto ${index + 1} tiene datos inválidos`);
    }
  });

  return errors;
}

// Uso
const errors = validateReservationForm(reservationData);
if (errors.length > 0) {
  alert('Errores en el formulario:\n' + errors.join('\n'));
} else {
  createReservation(reservationData);
}
```

---

## Endpoints de Administración (Requieren autenticación de admin)

### GET /api/admin/reservations
Listar todas las reservas con filtros y paginación.

**Headers:**
```json
{
  "Authorization": "Bearer {admin_jwt_token}",
  "Content-Type": "application/json"
}
```

**Query Parameters:**
- `page` (int, default: 1): Número de página
- `limit` (int, default: 10, max: 100): Items por página
- `status` (string): Filtrar por estado (pending, confirmed, rejected, expired)
- `search` (string): Buscar por nombre, email o número de reserva

**Ejemplo de Request:**
```
GET /api/admin/reservations?page=1&limit=20&status=pending
```

**Respuesta Exitosa (200):**
```json
{
  "data": [
    {
      "id": 15,
      "reservation_number": "RES20251106A3F2",
      "customer_name": "Juan Pérez",
      "customer_email": "juan@example.com",
      "customer_phone": "+54 9 11 1234-5678",
      "status": "pending",
      "total_amount": "38500.00",
      "created_at": "2025-11-06 14:30:00",
      "items_count": 3
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 45,
    "pages": 3
  }
}
```

---

### GET /api/admin/reservations/{id}
Obtener detalles completos de una reserva incluyendo items y logs.

**Headers:**
```json
{
  "Authorization": "Bearer {admin_jwt_token}"
}
```

**Respuesta Exitosa (200):**
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
      "product_id": 1,
      "product_name": "Mesa de Comedor",
      "product_sku": "MESA-001",
      "quantity": 2,
      "price": "15000.00",
      "total": "30000.00"
    },
    {
      "id": 24,
      "product_id": 5,
      "product_name": "Silla Tapizada",
      "product_sku": "SILLA-005",
      "quantity": 1,
      "price": "8500.00",
      "total": "8500.00"
    }
  ],
  "logs": [
    {
      "id": 45,
      "action": "created",
      "user_id": null,
      "details": "Reservation created by customer",
      "created_at": "2025-11-06 14:30:00"
    },
    {
      "id": 46,
      "action": "email_sent",
      "user_id": null,
      "details": "Confirmation email sent to customer and admin",
      "created_at": "2025-11-06 14:30:05"
    }
  ]
}
```

**Errores:**
```json
{
  "error": "Reservation not found"
}
```

---

### POST /api/admin/reservations/{id}/confirm
Confirmar una reserva y descontar stock.

**Headers:**
```json
{
  "Authorization": "Bearer {admin_jwt_token}",
  "Content-Type": "application/json"
}
```

**Body (opcional):**
```json
{
  "admin_notes": "Coordinar entrega para el sábado. Cliente pagará en efectivo."
}
```

**Respuesta Exitosa (200):**
```json
{
  "message": "Reservation confirmed successfully",
  "reservation_id": 15,
  "reservation_number": "RES20251106A3F2",
  "status": "confirmed",
  "confirmed_at": "2025-11-06 15:45:00"
}
```

**Errores Posibles:**

**404 Not Found:**
```json
{
  "error": "Reservation not found"
}
```

**400 Bad Request - Ya confirmada:**
```json
{
  "error": "Reservation is already confirmed"
}
```

**400 Bad Request - Stock insuficiente:**
```json
{
  "error": "Insufficient stock for product: Mesa de Comedor (SKU: MESA-001). Available: 1, Required: 2"
}
```

---

### POST /api/admin/reservations/{id}/reject
Rechazar una reserva.

**Headers:**
```json
{
  "Authorization": "Bearer {admin_jwt_token}",
  "Content-Type": "application/json"
}
```

**Body (opcional):**
```json
{
  "admin_notes": "Producto fuera de stock temporalmente. Le ofrecimos alternativa."
}
```

**Respuesta Exitosa (200):**
```json
{
  "message": "Reservation rejected successfully",
  "reservation_id": 15,
  "status": "rejected"
}
```

---

## Flujo de Emails Automáticos

### 1. Email al Cliente - Reserva Recibida
**Cuándo:** Inmediatamente después de crear la reserva
**Template:** `reservation_created`
**Contenido:**
- Número de reserva
- Detalle de productos con tabla HTML
- Total
- Mensaje: "Nuestro equipo revisará tu reserva y se comunicará contigo en las próximas 24-48 horas"

### 2. Email al Admin - Nueva Reserva
**Cuándo:** Inmediatamente después de crear la reserva
**Para:** info@decohomesinrival.com.ar
**Template:** `admin_new_reservation`
**Contenido:**
- Datos del cliente (nombre, email, teléfono)
- Detalle de productos
- Notas del cliente
- Botón para ver en panel de admin

### 3. Email al Cliente - Reserva Confirmada
**Cuándo:** Cuando admin confirma la reserva
**Template:** `reservation_confirmed`
**Contenido:**
- Confirmación de la reserva
- Detalle de productos
- Total a pagar
- Notas del administrador con instrucciones de pago/entrega

---

## Estados de Reserva

| Estado | Descripción | Acciones Permitidas |
|--------|-------------|---------------------|
| `pending` | Reserva creada, esperando confirmación | Confirmar, Rechazar |
| `confirmed` | Confirmada por admin, stock descontado | Ninguna |
| `rejected` | Rechazada por admin | Ninguna |
| `expired` | Reserva expirada (futura feature) | Ninguna |

---

## Ejemplo de Integración Completa - React

```jsx
import React, { useState } from 'react';

function ReservationForm({ cartItems }) {
  const [formData, setFormData] = useState({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    shipping_address: '',
    shipping_city: '',
    shipping_state: '',
    shipping_zip_code: '',
    notes: ''
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const reservationData = {
        ...formData,
        items: cartItems.map(item => ({
          product_id: item.id,
          quantity: item.quantity,
          price: item.price
        }))
      };

      const response = await fetch('https://tu-api.com/api/reservations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(reservationData)
      });

      const data = await response.json();

      if (response.ok) {
        // Éxito
        alert(`¡Reserva #${data.reservation_number} creada! Te contactaremos pronto.`);
        // Limpiar carrito y redirigir
        clearCart();
        window.location.href = `/reservation-success?number=${data.reservation_number}`;
      } else {
        setError(data.error);
      }

    } catch (err) {
      setError('Error de conexión. Por favor, intenta nuevamente.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        placeholder="Nombre completo"
        value={formData.customer_name}
        onChange={(e) => setFormData({...formData, customer_name: e.target.value})}
        required
      />

      <input
        type="email"
        placeholder="Email"
        value={formData.customer_email}
        onChange={(e) => setFormData({...formData, customer_email: e.target.value})}
        required
      />

      <input
        type="tel"
        placeholder="Teléfono"
        value={formData.customer_phone}
        onChange={(e) => setFormData({...formData, customer_phone: e.target.value})}
        required
      />

      <input
        type="text"
        placeholder="Dirección"
        value={formData.shipping_address}
        onChange={(e) => setFormData({...formData, shipping_address: e.target.value})}
      />

      <textarea
        placeholder="Comentarios o notas adicionales"
        value={formData.notes}
        onChange={(e) => setFormData({...formData, notes: e.target.value})}
      />

      {error && <div className="error">{error}</div>}

      <button type="submit" disabled={loading || cartItems.length === 0}>
        {loading ? 'Procesando...' : 'Crear Reserva'}
      </button>
    </form>
  );
}
```

---

## Notas Importantes

1. **NO se requiere autenticación** para crear reservas - es un endpoint público
2. **Stock NO se descuenta** al crear la reserva
3. **Stock se descuenta SOLO** cuando admin confirma desde el panel
4. Los emails se envían automáticamente si SMTP está configurado correctamente
5. El `reservation_number` tiene formato: `RES20251106XXXX` (año+mes+día+random)
6. Los productos deben existir y estar activos (`active = TRUE`)
7. Las reservas quedan en estado `pending` hasta que admin las confirme o rechace

---

## Troubleshooting

### No llegan los emails
- Verificar configuración SMTP en `.env`
- Revisar logs del servidor: `error_log` de PHP
- Verificar que el email de admin esté en `ADMIN_EMAIL`

### Error "Product not found"
- Verificar que el `product_id` existe en la base de datos
- Verificar que el producto tenga `active = TRUE`

### Error "Insufficient stock"
- Ocurre solo al confirmar reserva
- Verificar stock actual del producto
- El admin debe actualizar stock antes de confirmar

### Reserva creada pero no aparece en admin
- Verificar que la tabla `reservations` se creó correctamente
- Ejecutar el archivo SQL: `database/reservations_table.sql`
