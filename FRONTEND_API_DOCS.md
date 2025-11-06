# 📚 API Documentation para Frontend - E-commerce DecoHomes

## 🔗 Base URL

```
Producción: https://decohomesinrival.com.ar/ecommerce-api/public/api
Desarrollo: http://localhost/ecommerce-api/public/api
```

---

# 📑 Índice

1. [Autenticación](#1-autenticación)
2. [Carrito de Compras](#2-carrito-de-compras)
3. [Checkout y Pagos](#3-checkout-y-pagos)
4. [Órdenes](#4-órdenes)
5. [Pagos](#5-pagos)
6. [Productos](#6-productos)
7. [Direcciones](#7-direcciones)
8. [Cupones](#8-cupones)
9. [Wishlist](#9-wishlist)
10. [Códigos de Error](#10-códigos-de-error)

---

# 1. Autenticación

## 1.1 Registro de Usuario

```http
POST /api/auth/register
```

**Headers:**
```json
{
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@email.com",
  "password": "password123",
  "phone": "2664123456"  // opcional
}
```

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@email.com",
    "role": "customer"
  }
}
```

**Errores:**
- `400` - Email already exists
- `400` - Missing required fields

---

## 1.2 Login

```http
POST /api/auth/login
```

**Body:**
```json
{
  "email": "juan@email.com",
  "password": "password123"
}
```

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@email.com",
    "role": "customer"
  }
}
```

**Errores:**
- `401` - Invalid credentials
- `400` - Email and password required

---

## 1.3 Obtener Usuario Actual

```http
GET /api/auth/me
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "id": 1,
  "name": "Juan Pérez",
  "email": "juan@email.com",
  "phone": "2664123456",
  "birth_date": "1990-05-15",
  "age": 35,
  "gender": "male",
  "document_type": "DNI",
  "document_number": "35123456",
  "avatar": null,
  "bio": "Amante de la decoración y el diseño",
  "newsletter_subscribed": true,
  "email_verified": true,
  "role": "customer",
  "status": "active",
  "created_at": "2025-10-25 10:30:00",
  "updated_at": "2025-10-26 14:20:00",
  "last_login_at": "2025-10-26 09:15:00",
  "stats": {
    "total_orders": 5
  }
}
```

**Campos del Perfil:**
- `id` - ID único del usuario
- `name` - Nombre completo
- `email` - Email (usado para login)
- `phone` - Teléfono (opcional)
- `birth_date` - Fecha de nacimiento en formato YYYY-MM-DD (opcional)
- `age` - Edad calculada desde birth_date (calculado automáticamente)
- `gender` - Género: "male", "female", "other", "prefer_not_to_say" (opcional)
- `document_type` - Tipo de documento: "DNI", "CUIL", "CUIT", "Pasaporte" (opcional)
- `document_number` - Número de documento (opcional)
- `avatar` - URL de imagen de perfil (opcional)
- `bio` - Biografía o descripción personal (opcional)
- `newsletter_subscribed` - Suscripción a newsletter (boolean)
- `email_verified` - Si el email fue verificado (boolean)
- `last_login_at` - Última fecha de login
- `stats` - Estadísticas del usuario (órdenes, etc.)

---

## 1.4 Actualizar Perfil

```http
PUT /api/auth/profile
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body (todos los campos son opcionales):**
```json
{
  "name": "Juan Carlos Pérez",
  "phone": "2664987654",
  "birth_date": "1990-05-15",
  "gender": "male",
  "document_type": "DNI",
  "document_number": "35123456",
  "bio": "Diseñador de interiores apasionado por el minimalismo",
  "newsletter_subscribed": true
}
```

**Validaciones:**
- `birth_date`: Debe ser formato YYYY-MM-DD, usuario debe tener al menos 13 años
- `gender`: Solo acepta "male", "female", "other", "prefer_not_to_say"
- `newsletter_subscribed`: Debe ser booleano (true/false)

**Response 200:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "user": {
    "id": 1,
    "name": "Juan Carlos Pérez",
    "email": "juan@email.com",
    "phone": "2664987654",
    "birth_date": "1990-05-15",
    "age": 35,
    "gender": "male",
    "document_type": "DNI",
    "document_number": "35123456",
    "bio": "Diseñador de interiores apasionado por el minimalismo",
    "avatar": null,
    "newsletter_subscribed": true,
    "email_verified": true,
    "role": "customer",
    "status": "active",
    "created_at": "2025-10-25 10:30:00",
    "updated_at": "2025-10-26 14:45:00"
  }
}
```

**Errores Posibles:**
- `400` - "Invalid birth date format. Use YYYY-MM-DD"
- `400` - "You must be at least 13 years old"
- `400` - "Invalid gender value"

---

## 1.5 Cambiar Contraseña

```http
PUT /api/auth/change-password
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "current_password": "password123",
  "new_password": "newpassword456",
  "confirm_password": "newpassword456"
}
```

**Response 200:**
```json
{
  "message": "Password changed successfully"
}
```

---

## 1.6 Recuperar Contraseña

```http
POST /api/auth/forgot-password
```

**Body:**
```json
{
  "email": "juan@email.com"
}
```

**Response 200:**
```json
{
  "message": "Password reset email sent"
}
```

---

## 1.7 Resetear Contraseña

```http
POST /api/auth/reset-password
```

**Body:**
```json
{
  "token": "reset-token-from-email",
  "email": "juan@email.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response 200:**
```json
{
  "message": "Password reset successfully"
}
```

---

# 2. Carrito de Compras

## 2.1 Ver Carrito

```http
GET /api/cart
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "cart_id": 5,
  "items": [
    {
      "id": 12,
      "product_id": 23,
      "product_name": "Lámpara Moderna",
      "product_sku": "LAMP-001",
      "price": "5500.00",
      "quantity": 2,
      "stock": 10,
      "subtotal": 11000.00,
      "image_url": "/ecommerce-api/public/uploads/products/lamp001.jpg"
    },
    {
      "id": 13,
      "product_id": 24,
      "product_name": "Sillón Escandinavo",
      "product_sku": "SILLON-002",
      "price": "15000.00",
      "quantity": 1,
      "stock": 5,
      "subtotal": 15000.00,
      "image_url": "/ecommerce-api/public/uploads/products/sillon002.jpg"
    }
  ],
  "totals": {
    "items_count": 3,
    "subtotal": 26000.00,
    "total": 26000.00
  }
}
```

---

## 2.2 Agregar Item al Carrito

```http
POST /api/cart
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "product_id": 23,
  "quantity": 2
}
```

**Response 201:**
```json
{
  "message": "Product added to cart",
  "cart_item": {
    "id": 12,
    "product_id": 23,
    "product_name": "Lámpara Moderna",
    "quantity": 2,
    "price": "5500.00",
    "subtotal": 11000.00
  }
}
```

**Errores:**
- `404` - Product not found
- `400` - Insufficient stock

---

## 2.3 Actualizar Cantidad

```http
PUT /api/cart/items/{id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "quantity": 3
}
```

**Response 200:**
```json
{
  "message": "Cart item updated",
  "cart_item": {
    "id": 12,
    "product_id": 23,
    "quantity": 3,
    "price": "5500.00",
    "subtotal": 16500.00
  }
}
```

---

## 2.4 Eliminar Item del Carrito

```http
DELETE /api/cart/items/{id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Item removed from cart"
}
```

---

## 2.5 Vaciar Carrito

```http
DELETE /api/cart
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Cart cleared"
}
```

---

# 3. Checkout y Pagos

## 3.1 Validar Datos de Checkout

```http
POST /api/checkout/validate
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "customer_name": "Juan Pérez",
  "customer_email": "juan@email.com",
  "customer_phone": "2664123456",
  "shipping_address": "Av. Italia 1234, San Luis"
}
```

**Response 200:**
```json
{
  "valid": true,
  "errors": {}
}
```

**O si hay errores:**
```json
{
  "valid": false,
  "errors": {
    "customer_name": "Customer name is required",
    "cart": "Cart is empty",
    "stock_23": "Insufficient stock for Lámpara Moderna"
  }
}
```

---

## 3.2 Calcular Totales

```http
POST /api/checkout/calculate
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "coupon_code": "BIENVENIDO10"  // opcional
}
```

**Response 200:**
```json
{
  "subtotal": "26000.00",
  "discount": "2600.00",
  "subtotal_after_discount": "23400.00",
  "tax_rate": 0,
  "tax_amount": "0.00",
  "shipping_amount": "0.00",
  "total_amount": "23400.00",
  "coupon": {
    "code": "BIENVENIDO10",
    "type": "percentage",
    "value": "10.00",
    "description": "Descuento de bienvenida del 10%"
  }
}
```

---

## 3.3 Crear Preferencia de Pago (Mercado Pago)

```http
POST /api/checkout/mercadopago/create-preference
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "customer_name": "Juan Pérez",
  "customer_email": "juan@email.com",
  "customer_phone": "2664123456",
  "shipping_address": "Av. Italia 1234, San Luis, Argentina",
  "billing_address": "Av. Italia 1234, San Luis, Argentina",  // opcional
  "notes": "Entregar por la mañana",  // opcional
  "coupon_code": "BIENVENIDO10"  // opcional
}
```

**Response 200:**
```json
{
  "success": true,
  "order_id": 42,
  "order_number": "ORD20251025001",
  "preference_id": "123456789-abc123def456",
  "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=123456789-abc123def456",
  "total_amount": "23400.00"
}
```

**Ejemplo de uso en frontend:**
```javascript
async function handleCheckout(checkoutData) {
  try {
    const response = await fetch('/api/checkout/mercadopago/create-preference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`
      },
      body: JSON.stringify(checkoutData)
    });

    const data = await response.json();

    if (data.success) {
      // Guardar order_id en localStorage
      localStorage.setItem('pending_order_id', data.order_id);

      // Redirigir a Mercado Pago
      window.location.href = data.init_point;
    } else {
      console.error('Error:', data.error);
    }
  } catch (error) {
    console.error('Request failed:', error);
  }
}
```

**Errores:**
- `400` - Cart is empty
- `400` - Insufficient stock for {product}
- `400` - Customer name is required
- `500` - Error creating payment preference

---

## 3.4 Página de Éxito (Success)

Mercado Pago redirige a:
```
https://decohomesinrival.com.ar/checkout/success?external_reference=42&payment_id=123456789
```

Luego consultar el estado del pago:

```http
GET /api/payments/42
```

**Response 200:**
```json
{
  "order_id": 42,
  "order_status": "paid",
  "order_number": "ORD20251025001",
  "payment": {
    "payment_id": "123456789",
    "status": "approved",
    "amount": "23400.00",
    "currency": "ARS",
    "payment_method": "visa",
    "payment_type": "credit_card",
    "payer_email": "juan@email.com",
    "payer_name": "Juan Pérez",
    "created_at": "2025-10-25 15:30:00",
    "updated_at": "2025-10-25 15:30:15"
  }
}
```

---

## 3.5 Página de Error (Failure)

Mercado Pago redirige a:
```
https://decohomesinrival.com.ar/checkout/failure?external_reference=42
```

Consultar el estado:

```http
GET /api/payments/42
```

**Response 200:**
```json
{
  "order_id": 42,
  "order_status": "cancelled",
  "order_number": "ORD20251025001",
  "payment": {
    "payment_id": "123456789",
    "status": "rejected",
    "amount": "23400.00",
    "currency": "ARS",
    "created_at": "2025-10-25 15:30:00"
  }
}
```

---

## 3.6 Página de Pendiente (Pending)

Mercado Pago redirige a:
```
https://decohomesinrival.com.ar/checkout/pending?external_reference=42&payment_id=123456789
```

El pago está en proceso (por ejemplo, pago en efectivo o transferencia).

---

# 4. Órdenes

## 4.1 Listar Mis Órdenes

```http
GET /api/orders
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Query Parameters:**
- `page` - Número de página (default: 1)
- `limit` - Items por página (default: 10)
- `status` - Filtrar por estado: pending, paid, processing, shipped, delivered, cancelled

**Example:**
```
GET /api/orders?page=1&limit=10&status=paid
```

**Response 200:**
```json
{
  "orders": [
    {
      "id": 42,
      "order_number": "ORD20251025001",
      "status": "paid",
      "total_amount": "23400.00",
      "payment_method": "mercadopago",
      "created_at": "2025-10-25 15:30:00",
      "items_count": 3,
      "can_cancel": false
    },
    {
      "id": 41,
      "order_number": "ORD20251024002",
      "status": "pending",
      "total_amount": "15000.00",
      "payment_method": "mercadopago",
      "created_at": "2025-10-24 10:15:00",
      "items_count": 1,
      "can_cancel": true
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 3,
    "total_items": 25,
    "items_per_page": 10
  }
}
```

---

## 4.2 Ver Detalle de Orden

```http
GET /api/orders/{id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "id": 42,
  "order_number": "ORD20251025001",
  "status": "paid",
  "customer_name": "Juan Pérez",
  "customer_email": "juan@email.com",
  "customer_phone": "2664123456",
  "shipping_address": "Av. Italia 1234, San Luis, Argentina",
  "billing_address": "Av. Italia 1234, San Luis, Argentina",
  "subtotal": "26000.00",
  "discount_amount": "2600.00",
  "tax_amount": "0.00",
  "shipping_amount": "0.00",
  "total_amount": "23400.00",
  "payment_method": "mercadopago",
  "notes": "Entregar por la mañana",
  "created_at": "2025-10-25 15:30:00",
  "updated_at": "2025-10-25 15:30:15",
  "items": [
    {
      "id": 85,
      "product_id": 23,
      "product_name": "Lámpara Moderna",
      "product_sku": "LAMP-001",
      "quantity": 2,
      "price": "5500.00",
      "total": "11000.00",
      "image_url": "/ecommerce-api/public/uploads/products/lamp001.jpg"
    },
    {
      "id": 86,
      "product_id": 24,
      "product_name": "Sillón Escandinavo",
      "product_sku": "SILLON-002",
      "quantity": 1,
      "price": "15000.00",
      "total": "15000.00",
      "image_url": "/ecommerce-api/public/uploads/products/sillon002.jpg"
    }
  ],
  "payment": {
    "payment_id": "123456789",
    "status": "approved",
    "payment_method": "visa",
    "amount": "23400.00"
  },
  "can_cancel": false
}
```

**Errores:**
- `404` - Order not found
- `403` - Unauthorized (orden no pertenece al usuario)

---

## 4.3 Cancelar Orden

```http
POST /api/orders/{id}/cancel
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Order cancelled successfully",
  "order": {
    "id": 41,
    "order_number": "ORD20251024002",
    "status": "cancelled"
  }
}
```

**Errores:**
- `400` - Order cannot be cancelled (solo se pueden cancelar órdenes en estado 'pending')
- `404` - Order not found
- `403` - Unauthorized

---

# 5. Pagos

## 5.1 Listar Mis Pagos

```http
GET /api/payments
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "total": 3,
  "payments": [
    {
      "payment_id": "123456789",
      "order_id": 42,
      "order_number": "ORD20251025001",
      "status": "approved",
      "amount": "23400.00",
      "currency": "ARS",
      "payment_method": "visa",
      "created_at": "2025-10-25 15:30:15"
    },
    {
      "payment_id": "987654321",
      "order_id": 40,
      "order_number": "ORD20251023003",
      "status": "approved",
      "amount": "8500.00",
      "currency": "ARS",
      "payment_method": "mastercard",
      "created_at": "2025-10-23 12:15:30"
    }
  ]
}
```

---

## 5.2 Ver Pago de una Orden

```http
GET /api/payments/{orderId}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "order_id": 42,
  "order_status": "paid",
  "order_number": "ORD20251025001",
  "payment": {
    "payment_id": "123456789",
    "status": "approved",
    "amount": "23400.00",
    "currency": "ARS",
    "payment_method": "visa",
    "payment_type": "credit_card",
    "payer_email": "juan@email.com",
    "payer_name": "Juan Pérez",
    "created_at": "2025-10-25 15:30:00",
    "updated_at": "2025-10-25 15:30:15"
  }
}
```

**Si no hay pago aún:**
```json
{
  "order_id": 43,
  "order_status": "pending",
  "payment_status": "not_found",
  "message": "No payment found for this order yet"
}
```

---

## 5.3 Ver Detalle Completo de Pago

```http
GET /api/payments/detail/{paymentId}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "payment_id": "123456789",
  "order_id": 42,
  "status": "approved",
  "status_detail": "accredited",
  "amount": "23400.00",
  "currency": "ARS",
  "payment_method": "visa",
  "payment_type": "credit_card",
  "payer": {
    "email": "juan@email.com",
    "first_name": "Juan",
    "last_name": "Pérez",
    "identification": {
      "type": "DNI",
      "number": "12345678"
    }
  },
  "date_created": "2025-10-25T15:30:00.000-03:00",
  "date_approved": "2025-10-25T15:30:15.000-03:00"
}
```

---

# 6. Productos

## 6.1 Listar Productos

```http
GET /api/products
```

**Query Parameters:**
- `page` - Número de página (default: 1)
- `limit` - Items por página (default: 10, max: 100)
- `search` - Buscar por nombre
- `category_id` - Filtrar por categoría
- `min_price` - Precio mínimo
- `max_price` - Precio máximo
- `sort` - Ordenar por: `price_asc`, `price_desc`, `name_asc`, `name_desc`, `newest`

**Example:**
```
GET /api/products?page=1&limit=12&category_id=3&min_price=1000&max_price=10000&sort=price_asc
```

**Response 200:**
```json
{
  "products": [
    {
      "id": 23,
      "name": "Lámpara Moderna LED",
      "slug": "lampara-moderna-led",
      "description": "Lámpara de diseño moderno con tecnología LED",
      "price": "5500.00",
      "stock": 10,
      "sku": "LAMP-001",
      "category_id": 3,
      "category_name": "Iluminación",
      "status": "active",
      "primary_image": "/ecommerce-api/public/uploads/products/lamp001.jpg",
      "images": [
        {
          "id": 45,
          "image_url": "/ecommerce-api/public/uploads/products/lamp001.jpg",
          "is_primary": true,
          "sort_order": 0
        },
        {
          "id": 46,
          "image_url": "/ecommerce-api/public/uploads/products/lamp001_2.jpg",
          "is_primary": false,
          "sort_order": 1
        }
      ],
      "average_rating": 4.5,
      "reviews_count": 12
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 48,
    "items_per_page": 10
  }
}
```

---

## 6.2 Ver Detalle de Producto

```http
GET /api/products/{id}
```

**Response 200:**
```json
{
  "id": 23,
  "name": "Lámpara Moderna LED",
  "slug": "lampara-moderna-led",
  "description": "Lámpara de diseño moderno con tecnología LED de bajo consumo. Ideal para espacios contemporáneos.",
  "price": "5500.00",
  "stock": 10,
  "sku": "LAMP-001",
  "category_id": 3,
  "category_name": "Iluminación",
  "status": "active",
  "created_at": "2025-10-20 10:00:00",
  "images": [
    {
      "id": 45,
      "image_url": "/ecommerce-api/public/uploads/products/lamp001.jpg",
      "is_primary": true,
      "sort_order": 0
    },
    {
      "id": 46,
      "image_url": "/ecommerce-api/public/uploads/products/lamp001_2.jpg",
      "is_primary": false,
      "sort_order": 1
    },
    {
      "id": 47,
      "image_url": "/ecommerce-api/public/uploads/products/lamp001_3.jpg",
      "is_primary": false,
      "sort_order": 2
    }
  ],
  "reviews": {
    "average_rating": 4.5,
    "total_reviews": 12,
    "rating_distribution": {
      "5": 8,
      "4": 2,
      "3": 1,
      "2": 1,
      "1": 0
    }
  },
  "related_products": [
    {
      "id": 24,
      "name": "Lámpara de Escritorio",
      "price": "3500.00",
      "primary_image": "/ecommerce-api/public/uploads/products/lamp002.jpg"
    }
  ]
}
```

---

## 6.3 Ver Reseñas de Producto

```http
GET /api/products/{product_id}/reviews
```

**Query Parameters:**
- `page` - Número de página
- `limit` - Items por página
- `rating` - Filtrar por calificación (1-5)

**Response 200:**
```json
{
  "reviews": [
    {
      "id": 15,
      "user_name": "María González",
      "rating": 5,
      "title": "Excelente producto",
      "comment": "La lámpara es hermosa y de muy buena calidad. Llegó en perfectas condiciones.",
      "status": "approved",
      "created_at": "2025-10-22 14:30:00",
      "helpful_count": 3
    },
    {
      "id": 14,
      "user_name": "Carlos Ruiz",
      "rating": 4,
      "title": "Muy buena",
      "comment": "Me gustó mucho, solo que el envío demoró un poco más de lo esperado.",
      "status": "approved",
      "created_at": "2025-10-20 09:15:00",
      "helpful_count": 1
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 2,
    "total_items": 12
  }
}
```

---

## 6.4 Crear Reseña de Producto

```http
POST /api/products/{product_id}/reviews
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "rating": 5,
  "title": "Excelente producto",
  "comment": "La lámpara es hermosa y de muy buena calidad."
}
```

**Response 201:**
```json
{
  "message": "Review created successfully",
  "review": {
    "id": 16,
    "product_id": 23,
    "rating": 5,
    "title": "Excelente producto",
    "comment": "La lámpara es hermosa y de muy buena calidad.",
    "status": "pending",
    "created_at": "2025-10-25 16:00:00"
  }
}
```

**Errores:**
- `400` - Rating is required (1-5)
- `400` - You must purchase this product before reviewing

---

# 7. Direcciones

## 7.1 Listar Mis Direcciones

```http
GET /api/addresses
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "addresses": [
    {
      "id": 1,
      "address_type": "shipping",
      "full_name": "Juan Pérez",
      "phone": "2664123456",
      "address_line1": "Av. Italia 1234",
      "address_line2": "Depto 5B",
      "city": "San Luis",
      "state": "San Luis",
      "postal_code": "5700",
      "country": "Argentina",
      "is_default": true,
      "created_at": "2025-10-20 10:00:00"
    },
    {
      "id": 2,
      "address_type": "billing",
      "full_name": "Juan Pérez",
      "phone": "2664123456",
      "address_line1": "Av. Lafinur 500",
      "address_line2": null,
      "city": "San Luis",
      "state": "San Luis",
      "postal_code": "5700",
      "country": "Argentina",
      "is_default": false,
      "created_at": "2025-10-21 14:30:00"
    }
  ]
}
```

---

## 7.2 Crear Dirección

```http
POST /api/addresses
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "address_type": "shipping",
  "full_name": "Juan Pérez",
  "phone": "2664123456",
  "address_line1": "Av. Italia 1234",
  "address_line2": "Depto 5B",
  "city": "San Luis",
  "state": "San Luis",
  "postal_code": "5700",
  "country": "Argentina",
  "is_default": true
}
```

**Response 201:**
```json
{
  "message": "Address created successfully",
  "address": {
    "id": 3,
    "address_type": "shipping",
    "full_name": "Juan Pérez",
    "phone": "2664123456",
    "address_line1": "Av. Italia 1234",
    "address_line2": "Depto 5B",
    "city": "San Luis",
    "state": "San Luis",
    "postal_code": "5700",
    "country": "Argentina",
    "is_default": true
  }
}
```

---

## 7.3 Actualizar Dirección

```http
PUT /api/addresses/{id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:** (igual que crear)

**Response 200:**
```json
{
  "message": "Address updated successfully",
  "address": { ... }
}
```

---

## 7.4 Eliminar Dirección

```http
DELETE /api/addresses/{id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Address deleted successfully"
}
```

---

## 7.5 Establecer Dirección Predeterminada

```http
PUT /api/addresses/{id}/default
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Default address updated",
  "address_id": 1
}
```

---

# 8. Cupones

## 8.1 Validar Cupón

```http
POST /api/coupons/validate
```

**Headers:**
```json
{
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "code": "BIENVENIDO10",
  "subtotal": 26000.00
}
```

**Response 200:**
```json
{
  "valid": true,
  "coupon": {
    "code": "BIENVENIDO10",
    "type": "percentage",
    "value": "10.00",
    "description": "Descuento de bienvenida del 10%",
    "min_purchase": "100.00"
  },
  "discount": 2600.00,
  "subtotal_after_discount": 23400.00
}
```

**O si es inválido:**
```json
{
  "valid": false,
  "error": "Coupon not found or expired"
}
```

---

# 9. Wishlist (Lista de Deseos)

## 9.1 Ver Mi Wishlist

```http
GET /api/wishlist
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "total": 2,
  "items": [
    {
      "wishlist_id": 5,
      "product_id": 25,
      "name": "Mesa de Centro Moderna",
      "slug": "mesa-centro-moderna",
      "sku": "MESA-001",
      "price": "12000.00",
      "sale_price": "10800.00",
      "final_price": "10800.00",
      "discount_percentage": 10,
      "stock": 8,
      "status": "active",
      "in_stock": true,
      "image_path": "products/mesa001.jpg",
      "image_url": "https://decohomesinrival.com.ar/ecommerce-api/public/uploads/products/mesa001.jpg",
      "added_at": "2025-10-24 10:00:00"
    },
    {
      "wishlist_id": 6,
      "product_id": 26,
      "name": "Espejo Decorativo",
      "slug": "espejo-decorativo",
      "sku": "ESPEJO-001",
      "price": "4500.00",
      "sale_price": null,
      "final_price": "4500.00",
      "discount_percentage": 0,
      "stock": 15,
      "status": "active",
      "in_stock": true,
      "image_path": "products/espejo001.jpg",
      "image_url": "https://decohomesinrival.com.ar/ecommerce-api/public/uploads/products/espejo001.jpg",
      "added_at": "2025-10-23 15:30:00"
    }
  ]
}
```

**Campos Importantes:**
- `wishlist_id` - ID del item en la wishlist (para eliminar)
- `product_id` - ID del producto
- `final_price` - Precio final a mostrar (usa sale_price si existe, sino price)
- `discount_percentage` - Porcentaje de descuento si hay sale_price
- `in_stock` - Boolean que combina stock > 0 y status = 'active'
- `image_url` - URL completa de la imagen principal
- `added_at` - Fecha cuando se agregó a la wishlist

---

## 9.2 Agregar a Wishlist

```http
POST /api/wishlist
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Body:**
```json
{
  "product_id": 27
}
```

**Response 201:**
```json
{
  "message": "Product added to wishlist"
}
```

**Errores:**
- `400` - Product ID is required
- `404` - Product not found
- `409` - Product already in wishlist (se ignora silenciosamente, devuelve 201 igual)

---

## 9.3 Eliminar de Wishlist

```http
DELETE /api/wishlist/{product_id}
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response 200:**
```json
{
  "message": "Product removed from wishlist"
}
```

---

# 10. Códigos de Error

## HTTP Status Codes

| Código | Significado | Cuándo ocurre |
|--------|-------------|---------------|
| 200 | OK | Petición exitosa |
| 201 | Created | Recurso creado exitosamente |
| 400 | Bad Request | Datos inválidos o faltantes |
| 401 | Unauthorized | Token inválido o expirado |
| 403 | Forbidden | No tienes permiso para este recurso |
| 404 | Not Found | Recurso no encontrado |
| 500 | Internal Server Error | Error del servidor |

## Formato de Errores

```json
{
  "error": "Mensaje descriptivo del error"
}
```

O con más detalles:

```json
{
  "error": "Validation failed",
  "details": {
    "customer_name": "Customer name is required",
    "customer_email": "Invalid email format"
  }
}
```

---

# 11. Ejemplos de Código Frontend

## React Example - Flujo Completo de Checkout

```javascript
import { useState } from 'react';

const API_URL = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

function CheckoutFlow() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const userToken = localStorage.getItem('token');

  // 1. Ver carrito
  async function getCart() {
    const response = await fetch(`${API_URL}/cart`, {
      headers: {
        'Authorization': `Bearer ${userToken}`
      }
    });
    return await response.json();
  }

  // 2. Validar datos
  async function validateCheckout(data) {
    const response = await fetch(`${API_URL}/checkout/validate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`
      },
      body: JSON.stringify(data)
    });
    return await response.json();
  }

  // 3. Calcular totales
  async function calculateTotals(couponCode = null) {
    const response = await fetch(`${API_URL}/checkout/calculate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`
      },
      body: JSON.stringify({ coupon_code: couponCode })
    });
    return await response.json();
  }

  // 4. Crear preferencia de pago
  async function createPaymentPreference(checkoutData) {
    const response = await fetch(`${API_URL}/checkout/mercadopago/create-preference`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`
      },
      body: JSON.stringify(checkoutData)
    });
    return await response.json();
  }

  // 5. Procesar checkout
  async function handleCheckout(formData) {
    try {
      setLoading(true);
      setError(null);

      // Validar datos
      const validation = await validateCheckout(formData);
      if (!validation.valid) {
        setError(validation.errors);
        return;
      }

      // Crear preferencia de pago
      const preference = await createPaymentPreference(formData);

      if (preference.success) {
        // Guardar order_id
        localStorage.setItem('pending_order_id', preference.order_id);

        // Redirigir a Mercado Pago
        window.location.href = preference.init_point;
      } else {
        setError(preference.error);
      }
    } catch (err) {
      setError('Error al procesar el pago. Intenta nuevamente.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <h1>Checkout</h1>
      {/* Tu formulario aquí */}
      <button
        onClick={() => handleCheckout({
          customer_name: 'Juan Pérez',
          customer_email: 'juan@email.com',
          customer_phone: '2664123456',
          shipping_address: 'Av. Italia 1234, San Luis'
        })}
        disabled={loading}
      >
        {loading ? 'Procesando...' : 'Pagar con Mercado Pago'}
      </button>
      {error && <div className="error">{error}</div>}
    </div>
  );
}

export default CheckoutFlow;
```

---

## Vue.js Example - Ver Órdenes

```vue
<template>
  <div class="orders-list">
    <h2>Mis Órdenes</h2>

    <div v-if="loading">Cargando...</div>

    <div v-else-if="orders.length === 0">
      No tienes órdenes aún
    </div>

    <div v-else>
      <div v-for="order in orders" :key="order.id" class="order-card">
        <h3>Orden #{{ order.order_number }}</h3>
        <p>Estado: {{ getStatusLabel(order.status) }}</p>
        <p>Total: ${{ order.total_amount }}</p>
        <p>Fecha: {{ formatDate(order.created_at) }}</p>
        <button @click="viewOrder(order.id)">Ver detalle</button>
        <button
          v-if="order.can_cancel"
          @click="cancelOrder(order.id)"
          class="btn-danger"
        >
          Cancelar orden
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      orders: [],
      loading: false,
      error: null
    };
  },

  mounted() {
    this.loadOrders();
  },

  methods: {
    async loadOrders() {
      this.loading = true;
      try {
        const token = localStorage.getItem('token');
        const response = await fetch('/api/orders', {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });
        const data = await response.json();
        this.orders = data.orders;
      } catch (error) {
        this.error = 'Error al cargar las órdenes';
        console.error(error);
      } finally {
        this.loading = false;
      }
    },

    async cancelOrder(orderId) {
      if (!confirm('¿Estás seguro de cancelar esta orden?')) return;

      try {
        const token = localStorage.getItem('token');
        const response = await fetch(`/api/orders/${orderId}/cancel`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });

        if (response.ok) {
          alert('Orden cancelada exitosamente');
          this.loadOrders(); // Recargar lista
        }
      } catch (error) {
        alert('Error al cancelar la orden');
        console.error(error);
      }
    },

    viewOrder(orderId) {
      this.$router.push(`/orders/${orderId}`);
    },

    getStatusLabel(status) {
      const labels = {
        'pending': 'Pendiente',
        'paid': 'Pagado',
        'processing': 'En proceso',
        'shipped': 'Enviado',
        'delivered': 'Entregado',
        'cancelled': 'Cancelado'
      };
      return labels[status] || status;
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('es-AR');
    }
  }
};
</script>
```

---

## JavaScript Vanilla - Agregar al Carrito

```javascript
// Función para agregar producto al carrito
async function addToCart(productId, quantity = 1) {
  const token = localStorage.getItem('token');

  if (!token) {
    // Redirigir a login
    window.location.href = '/login';
    return;
  }

  try {
    const response = await fetch('/api/cart', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        product_id: productId,
        quantity: quantity
      })
    });

    const data = await response.json();

    if (response.ok) {
      // Mostrar notificación de éxito
      showNotification('Producto agregado al carrito', 'success');

      // Actualizar contador del carrito
      updateCartCount();
    } else {
      showNotification(data.error, 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showNotification('Error al agregar al carrito', 'error');
  }
}

// Actualizar contador del carrito
async function updateCartCount() {
  const token = localStorage.getItem('token');

  try {
    const response = await fetch('/api/cart', {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    const data = await response.json();
    const cartBadge = document.getElementById('cart-count');

    if (cartBadge && data.totals) {
      cartBadge.textContent = data.totals.items_count;
    }
  } catch (error) {
    console.error('Error updating cart count:', error);
  }
}

// Event listener para botones "Agregar al carrito"
document.querySelectorAll('.btn-add-to-cart').forEach(button => {
  button.addEventListener('click', function() {
    const productId = this.dataset.productId;
    const quantity = parseInt(this.dataset.quantity) || 1;
    addToCart(productId, quantity);
  });
});
```

---

## Axios Example - Configuración Global

```javascript
// api.js - Configuración de Axios
import axios from 'axios';

const API = axios.create({
  baseURL: 'https://decohomesinrival.com.ar/ecommerce-api/public/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Interceptor para agregar token automáticamente
API.interceptors.request.use(
  config => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => Promise.reject(error)
);

// Interceptor para manejar errores globalmente
API.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      // Token expirado o inválido
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default API;

// Uso en componentes:
import API from './api';

// Get cart
const cart = await API.get('/cart');

// Add to cart
const result = await API.post('/cart', {
  product_id: 23,
  quantity: 2
});

// Checkout
const preference = await API.post('/checkout/mercadopago/create-preference', {
  customer_name: 'Juan Pérez',
  customer_email: 'juan@email.com',
  customer_phone: '2664123456',
  shipping_address: 'Av. Italia 1234'
});
```

---

# 12. Testing

## Credenciales de Test

### Usuario de Prueba
```
Email: test@ecommerce.com
Password: test123
```

### Tarjetas de Test Mercado Pago

**VISA - Aprobada:**
```
Número: 4509 9535 6623 3704
CVV: 123
Vencimiento: 11/25
Titular: APRO
```

**Mastercard - Rechazada:**
```
Número: 5031 7557 3453 0604
CVV: 123
Vencimiento: 11/25
Titular: OTHE
```

**Más tarjetas:** https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing

---

# 13. Notas Importantes

## Manejo de Tokens

- El token JWT expira después de 24 horas
- Guardar el token en `localStorage` o `sessionStorage`
- Verificar si el token está presente antes de hacer peticiones protegidas
- Manejar error 401 para redirigir a login

## CORS

La API ya tiene CORS habilitado para todos los orígenes (`*`). En producción, considera limitar los orígenes permitidos.

## Rate Limiting

Actualmente no hay rate limiting implementado, pero considera:
- No hacer más de 100 peticiones por minuto
- Implementar debouncing en búsquedas
- Usar paginación para listas largas

## Imágenes

- Las URLs de imágenes son relativas: `/ecommerce-api/public/uploads/...`
- En producción, agregar el dominio completo
- Las imágenes se sirven directamente (sin autenticación)

---

**¿Necesitas más ejemplos o tienes dudas?** 🚀
