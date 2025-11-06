# Documentación Completa - Mercado Pago Integration para Frontend

**Última actualización:** 2025-10-26
**Backend API Base URL:** `https://decohomesinrival.com.ar/ecommerce-api/public/api`

---

## Índice

1. [Flujo Completo de Pago](#flujo-completo-de-pago)
2. [Endpoints Disponibles](#endpoints-disponibles)
3. [Método 1: Flujo Simplificado (Recomendado)](#método-1-flujo-simplificado)
4. [Método 2: Flujo con Carrito Persistente](#método-2-flujo-con-carrito-persistente)
5. [Respuestas del Backend](#respuestas-del-backend)
6. [Manejo de Errores](#manejo-de-errores)
7. [Ejemplos Completos](#ejemplos-completos)
8. [Testing](#testing)

---

## Flujo Completo de Pago

### Opción A: Flujo Simplificado (1 paso) - RECOMENDADO

```
Usuario → Checkout → [1 Llamada Backend] → Mercado Pago
```

1. Usuario completa formulario de checkout
2. Frontend envía items + datos del cliente a `/checkout/mercadopago/create-preference`
3. Backend crea la orden Y la preferencia de Mercado Pago
4. Frontend redirige al usuario a `init_point` de Mercado Pago
5. Usuario paga
6. Mercado Pago notifica al backend (webhook)
7. Backend actualiza estado de la orden

### Opción B: Flujo con Carrito Persistente (2 pasos)

```
Usuario → Carrito BD → Checkout → Mercado Pago
```

1. Usuario agrega productos al carrito (persiste en BD)
2. Usuario va a checkout
3. Frontend llama a `/checkout/mercadopago/create-preference` SIN enviar items
4. Backend lee items del carrito en BD
5. Backend crea orden y preferencia MP
6. Frontend redirige a Mercado Pago
7. Usuario paga
8. Webhook actualiza orden

---

## Endpoints Disponibles

### 1. Crear Preferencia de Mercado Pago (TODO EN UNO)

**Endpoint:** `POST /checkout/mercadopago/create-preference`
**Auth:** Required (Bearer Token)
**Descripción:** Crea la orden en BD Y la preferencia en Mercado Pago en una sola llamada

#### Request Body (Flujo Simplificado - CON items):

```typescript
interface CreatePreferenceRequest {
  // Datos del cliente (REQUERIDOS)
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  shipping_address: string;

  // Items del carrito (OPCIONAL - si no se envía, busca en BD)
  // IMPORTANTE: NO es necesario agregar al carrito en BD primero
  cart?: Array<{
    product_id: number;
    quantity: number;
    price: number;
  }>;

  // Opcionales
  billing_address?: string;  // Si no viene, usa shipping_address
  shipping_city?: string;
  shipping_state?: string;
  shipping_zip_code?: string;
  notes?: string;
  coupon_code?: string;
  shipping_amount?: number;  // Si no viene, usa valor de settings (50.00)
}
```

#### Response:

```json
{
  "success": true,
  "order_id": 123,
  "order_number": "ORD20251026XXXX",
  "preference_id": "123456789-abc123def456-00000000000abc",
  "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=...",
  "total_amount": "1500.50"
}
```

### 2. Validar Datos de Checkout (Opcional)

**Endpoint:** `POST /checkout/validate`
**Auth:** Required
**Descripción:** Valida datos antes de crear la orden

#### Request Body:

```json
{
  "customer_name": "Federico Jaime",
  "customer_email": "federiconj@gmail.com",
  "customer_phone": "2657218215",
  "shipping_address": "Martin Guemes 50"
}
```

#### Response:

```json
{
  "valid": true,
  "errors": {}
}
```

O si hay errores:

```json
{
  "valid": false,
  "errors": {
    "customer_email": "Invalid email format",
    "cart": "Cart is empty"
  }
}
```

### 3. Calcular Totales (Opcional)

**Endpoint:** `POST /checkout/calculate`
**Auth:** Required
**Descripción:** Calcula totales sin crear la orden

#### Request Body:

```json
{
  "coupon_code": "VERANO50",
  "shipping_amount": 100.00
}
```

#### Response:

```json
{
  "subtotal": "1200.00",
  "discount": "600.00",
  "tax_rate": 16.00,
  "tax_amount": "96.00",
  "shipping_amount": "100.00",
  "total": "796.00",
  "items_count": 3,
  "coupon": {
    "code": "VERANO50",
    "type": "percentage",
    "value": 50,
    "description": "50% de descuento"
  }
}
```

### 4. Obtener Public Key de Mercado Pago

**Endpoint:** `GET /mercadopago/public-key`
**Auth:** Not Required
**Descripción:** Obtiene la public key para usar en el frontend

#### Response:

```json
{
  "public_key": "APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
}
```

### 5. Páginas de Retorno

#### Success
**Endpoint:** `GET /checkout/mercadopago/success`
**Query Params:** `?external_reference={order_id}&payment_id={payment_id}`

#### Failure
**Endpoint:** `GET /checkout/mercadopago/failure`
**Query Params:** `?external_reference={order_id}`

#### Pending
**Endpoint:** `GET /checkout/mercadopago/pending`
**Query Params:** `?external_reference={order_id}&payment_id={payment_id}`

---

## Método 1: Flujo Simplificado (Recomendado)

### Paso 1: Usuario completa checkout

```jsx
// React Example
const handleCheckout = async () => {
  const checkoutData = {
    customer_name: formData.name,
    customer_email: formData.email,
    customer_phone: formData.phone,
    shipping_address: formData.address,
    shipping_city: formData.city,
    shipping_state: formData.state,
    shipping_zip_code: formData.zipCode,
    notes: formData.notes,

    // IMPORTANTE: Enviar los items del carrito directamente
    cart: cartItems.map(item => ({
      product_id: item.id,
      quantity: item.quantity,
      price: item.price
    })),

    // Opcional
    coupon_code: couponCode,
    shipping_amount: shippingCost
  };

  try {
    const response = await fetch(
      'https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference',
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkoutData)
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Error al crear la preferencia');
    }

    // Guardar order_id para tracking
    localStorage.setItem('pending_order_id', data.order_id);

    // Redirigir a Mercado Pago
    window.location.href = data.init_point;

  } catch (error) {
    console.error('Error:', error);
    alert(error.message);
  }
};
```

### Paso 2: Manejar retorno de Mercado Pago

```jsx
// Success Page Component
const CheckoutSuccess = () => {
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('external_reference');
    const paymentId = urlParams.get('payment_id');

    if (orderId) {
      // Limpiar carrito local
      localStorage.removeItem('cart');
      localStorage.removeItem('pending_order_id');

      // Mostrar confirmación
      console.log('Orden completada:', orderId);
      console.log('Pago ID:', paymentId);

      // Opcional: Obtener detalles de la orden
      fetchOrderDetails(orderId);
    }
  }, []);

  const fetchOrderDetails = async (orderId) => {
    try {
      const response = await fetch(
        `https://decohomesinrival.com.ar/ecommerce-api/public/api/orders/${orderId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      const order = await response.json();
      // Mostrar detalles de la orden
    } catch (error) {
      console.error('Error al obtener orden:', error);
    }
  };

  return (
    <div>
      <h1>¡Pago Exitoso!</h1>
      <p>Tu orden ha sido procesada correctamente.</p>
      <a href="/orders">Ver mis órdenes</a>
    </div>
  );
};
```

---

## Método 2: Flujo con Carrito Persistente

### Paso 1: Agregar productos al carrito en BD

```javascript
const addToCart = async (productId, quantity) => {
  try {
    const response = await fetch(
      'https://decohomesinrival.com.ar/ecommerce-api/public/api/cart',
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          product_id: productId,
          quantity: quantity
        })
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error);
    }

    console.log('Producto agregado al carrito');
  } catch (error) {
    console.error('Error:', error);
  }
};
```

### Paso 2: Crear preferencia SIN enviar items

```javascript
const handleCheckout = async () => {
  const checkoutData = {
    customer_name: formData.name,
    customer_email: formData.email,
    customer_phone: formData.phone,
    shipping_address: formData.address,
    // NO enviar 'cart' - el backend lo tomará de la BD
  };

  try {
    const response = await fetch(
      'https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference',
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkoutData)
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Error al crear la preferencia');
    }

    // Redirigir a Mercado Pago
    window.location.href = data.init_point;

  } catch (error) {
    console.error('Error:', error);
    alert(error.message);
  }
};
```

---

## Respuestas del Backend

### Respuesta Exitosa

```json
{
  "success": true,
  "order_id": 123,
  "order_number": "ORD20251026XXXX",
  "preference_id": "123456789-abc123def456-00000000000abc",
  "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=...",
  "total_amount": "1500.50"
}
```

### Errores Comunes

#### 1. Datos Faltantes

```json
{
  "error": "Customer name is required"
}
```

**Causa:** Falta algún campo requerido
**Solución:** Verificar que se envíen: `customer_name`, `customer_email`, `customer_phone`, `shipping_address`

#### 2. Carrito Vacío

```json
{
  "error": "Cart is empty"
}
```

**Causa:** No hay items en el request ni en el carrito BD
**Solución:** Enviar array `cart` con los productos O agregar productos al carrito BD primero

#### 3. Producto No Encontrado

```json
{
  "error": "Product not found: 35"
}
```

**Causa:** El product_id no existe o está inactivo
**Solución:** Verificar que el producto exista y tenga `status = 'active'`

#### 4. Stock Insuficiente

```json
{
  "error": "Insufficient stock for Mesa de Comedor",
  "product_id": 42
}
```

**Causa:** No hay suficiente stock
**Solución:** Reducir cantidad o informar al usuario

#### 5. Token Inválido

```json
{
  "error": "Unauthorized - Invalid user"
}
```

**Causa:** Token JWT inválido o expirado
**Solución:** Solicitar login nuevamente

---

## Manejo de Errores

### Wrapper para Llamadas API

```javascript
const callAPI = async (endpoint, options = {}) => {
  try {
    const response = await fetch(
      `https://decohomesinrival.com.ar/ecommerce-api/public/api${endpoint}`,
      {
        ...options,
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json',
          ...options.headers
        }
      }
    );

    const data = await response.json();

    if (!response.ok) {
      // Manejar errores específicos
      if (response.status === 401) {
        // Token inválido - redirigir a login
        localStorage.removeItem('token');
        window.location.href = '/login';
        throw new Error('Sesión expirada. Por favor inicia sesión nuevamente.');
      }

      if (response.status === 400) {
        // Error de validación
        throw new Error(data.error || 'Datos inválidos');
      }

      if (response.status === 404) {
        // Recurso no encontrado
        throw new Error(data.error || 'Producto no encontrado');
      }

      if (response.status === 500) {
        // Error del servidor
        throw new Error('Error del servidor. Intenta nuevamente más tarde.');
      }

      throw new Error(data.error || 'Error desconocido');
    }

    return data;

  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
};

// Uso
try {
  const result = await callAPI('/checkout/mercadopago/create-preference', {
    method: 'POST',
    body: JSON.stringify(checkoutData)
  });

  window.location.href = result.init_point;
} catch (error) {
  alert(error.message);
}
```

---

## Ejemplos Completos

### Ejemplo 1: Servicio de Checkout (React)

```javascript
// services/checkoutService.js
const API_BASE = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

export const checkoutService = {
  // Validar datos de checkout
  validate: async (data) => {
    const response = await fetch(`${API_BASE}/checkout/validate`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });

    return await response.json();
  },

  // Calcular totales
  calculate: async (couponCode = null, shippingAmount = null) => {
    const response = await fetch(`${API_BASE}/checkout/calculate`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        coupon_code: couponCode,
        shipping_amount: shippingAmount
      })
    });

    return await response.json();
  },

  // Crear preferencia de Mercado Pago
  createMercadoPagoPreference: async (checkoutData) => {
    const response = await fetch(`${API_BASE}/checkout/mercadopago/create-preference`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(checkoutData)
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Error al crear la preferencia');
    }

    return data;
  },

  // Obtener detalles de una orden
  getOrder: async (orderId) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    });

    return await response.json();
  }
};
```

### Ejemplo 2: Componente de Checkout Completo (React)

```jsx
// components/Checkout.jsx
import { useState, useEffect } from 'react';
import { checkoutService } from '../services/checkoutService';

const Checkout = ({ cartItems }) => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    zipCode: '',
    notes: ''
  });

  const [couponCode, setCouponCode] = useState('');
  const [totals, setTotals] = useState(null);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  // Calcular totales cuando cambia el cupón
  useEffect(() => {
    if (couponCode) {
      calculateTotals();
    }
  }, [couponCode]);

  const calculateTotals = async () => {
    try {
      const result = await checkoutService.calculate(couponCode);
      setTotals(result);
    } catch (error) {
      console.error('Error al calcular totales:', error);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    try {
      // Preparar datos
      const checkoutData = {
        customer_name: formData.name,
        customer_email: formData.email,
        customer_phone: formData.phone,
        shipping_address: formData.address,
        shipping_city: formData.city,
        shipping_state: formData.state,
        shipping_zip_code: formData.zipCode,
        notes: formData.notes,

        // Enviar items del carrito
        cart: cartItems.map(item => ({
          product_id: item.id,
          quantity: item.quantity,
          price: item.price
        })),

        // Cupón si existe
        coupon_code: couponCode || undefined
      };

      // Validar primero (opcional)
      const validation = await checkoutService.validate(checkoutData);

      if (!validation.valid) {
        setErrors(validation.errors);
        setLoading(false);
        return;
      }

      // Crear preferencia de Mercado Pago
      const result = await checkoutService.createMercadoPagoPreference(checkoutData);

      // Guardar order_id para tracking
      localStorage.setItem('pending_order_id', result.order_id);

      // Redirigir a Mercado Pago
      window.location.href = result.init_point;

    } catch (error) {
      console.error('Error en checkout:', error);
      alert(error.message);
      setLoading(false);
    }
  };

  return (
    <div className="checkout">
      <h1>Checkout</h1>

      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Nombre Completo *</label>
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({...formData, name: e.target.value})}
            required
          />
          {errors.customer_name && <span className="error">{errors.customer_name}</span>}
        </div>

        <div className="form-group">
          <label>Email *</label>
          <input
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({...formData, email: e.target.value})}
            required
          />
          {errors.customer_email && <span className="error">{errors.customer_email}</span>}
        </div>

        <div className="form-group">
          <label>Teléfono *</label>
          <input
            type="tel"
            value={formData.phone}
            onChange={(e) => setFormData({...formData, phone: e.target.value})}
            required
          />
          {errors.customer_phone && <span className="error">{errors.customer_phone}</span>}
        </div>

        <div className="form-group">
          <label>Dirección de Envío *</label>
          <input
            type="text"
            value={formData.address}
            onChange={(e) => setFormData({...formData, address: e.target.value})}
            required
          />
          {errors.shipping_address && <span className="error">{errors.shipping_address}</span>}
        </div>

        <div className="form-row">
          <div className="form-group">
            <label>Ciudad</label>
            <input
              type="text"
              value={formData.city}
              onChange={(e) => setFormData({...formData, city: e.target.value})}
            />
          </div>

          <div className="form-group">
            <label>Provincia</label>
            <input
              type="text"
              value={formData.state}
              onChange={(e) => setFormData({...formData, state: e.target.value})}
            />
          </div>

          <div className="form-group">
            <label>Código Postal</label>
            <input
              type="text"
              value={formData.zipCode}
              onChange={(e) => setFormData({...formData, zipCode: e.target.value})}
            />
          </div>
        </div>

        <div className="form-group">
          <label>Notas (opcional)</label>
          <textarea
            value={formData.notes}
            onChange={(e) => setFormData({...formData, notes: e.target.value})}
            rows="3"
          />
        </div>

        <div className="form-group">
          <label>Código de Cupón (opcional)</label>
          <input
            type="text"
            value={couponCode}
            onChange={(e) => setCouponCode(e.target.value)}
            placeholder="VERANO50"
          />
          <button type="button" onClick={calculateTotals}>
            Aplicar Cupón
          </button>
        </div>

        {totals && (
          <div className="totals">
            <p>Subtotal: ${totals.subtotal}</p>
            {totals.discount > 0 && <p>Descuento: -${totals.discount}</p>}
            <p>IVA ({totals.tax_rate}%): ${totals.tax_amount}</p>
            <p>Envío: ${totals.shipping_amount}</p>
            <h3>Total: ${totals.total}</h3>
          </div>
        )}

        <button type="submit" disabled={loading}>
          {loading ? 'Procesando...' : 'Pagar con Mercado Pago'}
        </button>
      </form>
    </div>
  );
};

export default Checkout;
```

### Ejemplo 3: Vanilla JavaScript

```javascript
// checkout.js
document.addEventListener('DOMContentLoaded', () => {
  const checkoutForm = document.getElementById('checkout-form');

  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Obtener datos del formulario
    const formData = new FormData(checkoutForm);

    // Obtener items del carrito (asumiendo que están en localStorage)
    const cartItems = JSON.parse(localStorage.getItem('cart') || '[]');

    const checkoutData = {
      customer_name: formData.get('name'),
      customer_email: formData.get('email'),
      customer_phone: formData.get('phone'),
      shipping_address: formData.get('address'),
      shipping_city: formData.get('city'),
      notes: formData.get('notes'),
      cart: cartItems.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        price: item.price
      }))
    };

    try {
      const response = await fetch(
        'https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference',
        {
          method: 'POST',
          headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token'),
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(checkoutData)
        }
      );

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Error al procesar el checkout');
      }

      // Guardar order_id
      localStorage.setItem('pending_order_id', data.order_id);

      // Redirigir a Mercado Pago
      window.location.href = data.init_point;

    } catch (error) {
      alert('Error: ' + error.message);
      console.error(error);
    }
  });
});
```

---

## Testing

### Test 1: Crear Preferencia con Items (cURL)

```bash
curl -X POST https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Federico Jaime",
    "customer_email": "federiconj@gmail.com",
    "customer_phone": "2657218215",
    "shipping_address": "Martin Guemes 50",
    "shipping_city": "SAN LUIS",
    "cart": [
      {
        "product_id": 35,
        "quantity": 1,
        "price": 12342.20
      }
    ]
  }'
```

### Test 2: Validar Datos (cURL)

```bash
curl -X POST https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/validate \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Federico Jaime",
    "customer_email": "federiconj@gmail.com",
    "customer_phone": "2657218215",
    "shipping_address": "Martin Guemes 50"
  }'
```

### Test 3: Calcular Totales con Cupón (cURL)

```bash
curl -X POST https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/calculate \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "coupon_code": "VERANO50",
    "shipping_amount": 100.00
  }'
```

### Test en Navegador (Console)

```javascript
// Test completo en la consola del navegador
(async () => {
  const token = localStorage.getItem('token');

  const checkoutData = {
    customer_name: "Federico Test",
    customer_email: "test@test.com",
    customer_phone: "1234567890",
    shipping_address: "Test 123",
    shipping_city: "Test City",
    cart: [
      { product_id: 35, quantity: 1, price: 100 }
    ]
  };

  try {
    const response = await fetch(
      'https://decohomesinrival.com.ar/ecommerce-api/public/api/checkout/mercadopago/create-preference',
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkoutData)
      }
    );

    const data = await response.json();
    console.log('Response:', data);

    if (data.init_point) {
      console.log('✅ Success! Init point:', data.init_point);
      console.log('Order ID:', data.order_id);
    } else {
      console.log('❌ Error:', data.error);
    }
  } catch (error) {
    console.error('❌ Error:', error);
  }
})();
```

---

## Configuración de URLs de Retorno en el Frontend

Asegúrate de tener páginas en tu frontend para manejar los retornos:

- `/checkout/success` - Pago exitoso
- `/checkout/failure` - Pago fallido
- `/checkout/pending` - Pago pendiente

Estas páginas recibirán los parámetros de Mercado Pago y deberán mostrar el estado al usuario.

---

## Notas Importantes

1. **Autenticación:** Todos los endpoints requieren token JWT en el header `Authorization: Bearer {token}`

2. **Items en Request:** Puedes enviar los items directamente en el request (`cart` array) O dejarlos vacíos para que el backend los tome del carrito persistente en BD

3. **Payment Method:** Aunque el campo `payment_method` no es requerido en `create-preference`, el backend automáticamente lo establece como `'mercadopago'`

4. **Stock:** El backend verifica stock automáticamente antes de crear la orden. Si no hay stock suficiente, retorna error 400

5. **Transacciones:** El backend usa transacciones de BD. Si algo falla (error de MP, stock insuficiente, etc.), la orden NO se crea

6. **Webhook:** El backend tiene un webhook configurado en `/webhooks/mercadopago` que Mercado Pago llamará automáticamente cuando el pago se complete

7. **Estado de Orden:** La orden se crea con estado `'pending'` y se actualiza a `'paid'` cuando el webhook de MP confirma el pago

---

## Resumen de Campos

### Campos Requeridos
- `customer_name` ✅
- `customer_email` ✅
- `customer_phone` ✅
- `shipping_address` ✅

### Campos Opcionales
- `cart` (array de items) - Si no se envía, usa carrito BD
- `billing_address` - Default: mismo que shipping_address
- `shipping_city`
- `shipping_state`
- `shipping_zip_code`
- `notes`
- `coupon_code`
- `shipping_amount` - Default: valor de settings (50.00)

---

**¿Dudas?** Revisa los ejemplos de código completos arriba o prueba con cURL para verificar las respuestas del backend.
