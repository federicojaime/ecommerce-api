# Documentación Completa - E-commerce API

## Información General

**Base URL:** `http://localhost:8000`  
**Versión:** 1.0  
**Framework:** PHP Slim 4 + MySQL  
**Autenticación:** JWT Bearer Token  

---

## Autenticación

### POST `/api/auth/login`
**Descripción:** Iniciar sesión y obtener token JWT

**Request Body:**
```json
{
  "email": "admin@ecommerce.com",
  "password": "password"
}
```

**Response (200 OK):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@ecommerce.com",
    "role": "admin"
  }
}
```

**Errores:**
- `400` Email and password required
- `401` Invalid credentials

---

### POST `/api/auth/register`
**Descripción:** Registrar nuevo usuario

**Request Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "role": "customer"
}
```

**Response (201 Created):**
```json
{
  "message": "User created successfully",
  "user_id": 2
}
```

**Errores:**
- `400` [field] is required / Email already exists

---

### GET `/api/auth/me`
**Descripción:** Obtener perfil del usuario autenticado

**Headers:** `Authorization: Bearer {token}`

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "Admin",
  "email": "admin@ecommerce.com",
  "role": "admin",
  "status": "active",
  "created_at": "2025-01-01 00:00:00"
}
```

---

## Productos (Público)

### GET `/api/products`
**Descripción:** Listar productos públicos

**Query Parameters:**
- `page` (int): Número de página (default: 1)
- `limit` (int): Límite por página (default: 10, max: 100)
- `search` (string): Búsqueda por nombre, SKU o descripción
- `category` (int): Filtrar por ID de categoría
- `status` (string): Filtrar por estado (active, inactive)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "slug": "iphone-15-pro",
      "description": "El iPhone más avanzado",
      "sku": "IPH15PRO001",
      "price": "1199.99",
      "sale_price": "999.99",
      "stock": 50,
      "status": "active",
      "featured": true,
      "category_name": "Electrónicos",
      "primary_image": "products/img123.jpg",
      "created_at": "2025-01-01 00:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 25,
    "pages": 3
  }
}
```

---

### GET `/api/products/{id}`
**Descripción:** Obtener producto específico

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "iPhone 15 Pro",
  "slug": "iphone-15-pro",
  "description": "El iPhone más avanzado",
  "short_description": "iPhone con tecnología de vanguardia",
  "sku": "IPH15PRO001",
  "price": "1199.99",
  "sale_price": "999.99",
  "stock": 50,
  "min_stock": 5,
  "status": "active",
  "featured": true,
  "weight": "0.20",
  "dimensions": "15.0 x 7.5 x 0.8 cm",
  "category_id": 1,
  "category_name": "Electrónicos",
  "images": [
    {
      "id": 1,
      "product_id": 1,
      "image_path": "products/img123.jpg",
      "alt_text": "iPhone 15 Pro frontal",
      "is_primary": true,
      "sort_order": 0
    }
  ],
  "created_at": "2025-01-01 00:00:00",
  "updated_at": "2025-01-01 00:00:00"
}
```

**Errores:**
- `404` Product not found

---

## Productos (Admin)

**Autenticación requerida:** `Authorization: Bearer {token}`

### GET `/api/admin/products`
**Descripción:** Listar productos (admin)

**Mismos parámetros y respuesta que** `/api/products`

---

### POST `/api/admin/products`
**Descripción:** Crear producto

**Request Body:**
```json
{
  "name": "iPhone 15 Pro",
  "description": "El iPhone 15 Pro más avanzado con chip A17 Pro",
  "short_description": "iPhone 15 Pro con tecnología de vanguardia",
  "sku": "IPH15PRO001",
  "price": 1199.99,
  "sale_price": 999.99,
  "stock": 50,
  "min_stock": 5,
  "status": "active",
  "featured": true,
  "weight": 0.2,
  "dimensions": "15.0 x 7.5 x 0.8 cm",
  "category_id": 1
}
```

**Response (201 Created):**
```json
{
  "message": "Product created successfully",
  "product_id": 1
}
```

**Errores:**
- `400` [field] is required / SKU already exists

---

### PUT `/api/admin/products/{id}`
**Descripción:** Actualizar producto

**Request Body:** (campos opcionales)
```json
{
  "name": "iPhone 15 Pro Max Actualizado",
  "price": 1299.99,
  "stock": 25,
  "status": "active"
}
```

**Response (200 OK):**
```json
{
  "message": "Product updated successfully"
}
```

**Errores:**
- `404` Product not found
- `400` SKU already exists

---

### DELETE `/api/admin/products/{id}`
**Descripción:** Eliminar producto

**Response (200 OK):**
```json
{
  "message": "Product deleted successfully"
}
```

**Errores:**
- `404` Product not found

---

## Categorías (Público)

### GET `/api/categories`
**Descripción:** Listar categorías públicas

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Electrónicos",
    "slug": "electronicos",
    "description": "Productos electrónicos y tecnología",
    "parent_id": null,
    "parent_name": null,
    "status": "active",
    "products_count": 15,
    "created_at": "2025-01-01 00:00:00",
    "updated_at": "2025-01-01 00:00:00"
  }
]
```

---

### GET `/api/categories/{id}`
**Descripción:** Obtener categoría específica

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "Electrónicos",
  "slug": "electronicos",
  "description": "Productos electrónicos y tecnología",
  "parent_id": null,
  "parent_name": null,
  "status": "active",
  "subcategories": [
    {
      "id": 4,
      "name": "Smartphones",
      "slug": "smartphones",
      "description": "Teléfonos inteligentes",
      "parent_id": 1,
      "status": "active",
      "created_at": "2025-01-01 00:00:00"
    }
  ],
  "created_at": "2025-01-01 00:00:00",
  "updated_at": "2025-01-01 00:00:00"
}
```

**Errores:**
- `404` Category not found

---

## Categorías (Admin)

**Autenticación requerida:** `Authorization: Bearer {token}`

### POST `/api/admin/categories`
**Descripción:** Crear categoría

**Request Body:**
```json
{
  "name": "Smartphones",
  "description": "Teléfonos inteligentes y accesorios",
  "parent_id": 1,
  "status": "active"
}
```

**Response (201 Created):**
```json
{
  "message": "Category created successfully",
  "category_id": 4
}
```

**Errores:**
- `400` Name is required / Category slug already exists

---

### PUT `/api/admin/categories/{id}`
**Descripción:** Actualizar categoría

**Request Body:**
```json
{
  "name": "Tecnología y Electrónicos",
  "description": "Categoría actualizada de productos tecnológicos"
}
```

**Response (200 OK):**
```json
{
  "message": "Category updated successfully"
}
```

---

### DELETE `/api/admin/categories/{id}`
**Descripción:** Eliminar categoría

**Response (200 OK):**
```json
{
  "message": "Category deleted successfully"
}
```

**Errores:**
- `404` Category not found
- `400` Cannot delete category with associated products / Cannot delete category with subcategories

---

## Usuarios (Admin)

**Autenticación requerida:** `Authorization: Bearer {token}`

### GET `/api/admin/users`
**Descripción:** Listar usuarios

**Query Parameters:**
- `page` (int): Número de página (default: 1)
- `limit` (int): Límite por página (default: 10, max: 100)
- `role` (string): Filtrar por rol (admin, staff, customer)
- `status` (string): Filtrar por estado (active, inactive)
- `search` (string): Búsqueda por nombre o email

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Admin",
      "email": "admin@ecommerce.com",
      "role": "admin",
      "status": "active",
      "created_at": "2025-01-01 00:00:00",
      "updated_at": "2025-01-01 00:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 5,
    "pages": 1
  }
}
```

---

### GET `/api/admin/users/{id}`
**Descripción:** Obtener usuario específico

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "Admin",
  "email": "admin@ecommerce.com",
  "role": "admin",
  "status": "active",
  "created_at": "2025-01-01 00:00:00",
  "updated_at": "2025-01-01 00:00:00"
}
```

---

### POST `/api/admin/users`
**Descripción:** Crear usuario

**Request Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan.perez@example.com",
  "password": "password123",
  "role": "staff",
  "status": "active"
}
```

**Response (201 Created):**
```json
{
  "message": "User created successfully",
  "user_id": 2
}
```

---

### PUT `/api/admin/users/{id}`
**Descripción:** Actualizar usuario

**Request Body:**
```json
{
  "name": "Juan Pérez Actualizado",
  "role": "admin",
  "status": "active",
  "password": "newpassword123"
}
```

**Response (200 OK):**
```json
{
  "message": "User updated successfully"
}
```

---

### DELETE `/api/admin/users/{id}`
**Descripción:** Eliminar usuario

**Response (200 OK):**
```json
{
  "message": "User deleted successfully"
}
```

**Errores:**
- `400` Cannot delete the last admin user

---

## Órdenes (Admin)

**Autenticación requerida:** `Authorization: Bearer {token}`

### GET `/api/admin/orders`
**Descripción:** Listar órdenes

**Query Parameters:**
- `page` (int): Número de página (default: 1)
- `limit` (int): Límite por página (default: 10, max: 100)
- `status` (string): Filtrar por estado (pending, processing, shipped, delivered, cancelled)
- `search` (string): Búsqueda por número de orden, nombre o email del cliente

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "order_number": "ORD20250903001",
      "customer_id": null,
      "customer_name": "María García",
      "customer_email": "maria.garcia@example.com",
      "customer_phone": "+1234567890",
      "status": "pending",
      "subtotal": "1999.98",
      "tax_amount": "150.00",
      "shipping_amount": "50.00",
      "total_amount": "2199.98",
      "payment_status": "pending",
      "payment_method": "credit_card",
      "customer_user_name": null,
      "items_count": 2,
      "created_at": "2025-09-03 00:00:00",
      "updated_at": "2025-09-03 00:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 1,
    "pages": 1
  }
}
```

---

### GET `/api/admin/orders/{id}`
**Descripción:** Obtener orden específica

**Response (200 OK):**
```json
{
  "id": 1,
  "order_number": "ORD20250903001",
  "customer_id": null,
  "customer_name": "María García",
  "customer_email": "maria.garcia@example.com",
  "customer_phone": "+1234567890",
  "status": "pending",
  "subtotal": "1999.98",
  "tax_amount": "150.00",
  "shipping_amount": "50.00",
  "total_amount": "2199.98",
  "payment_status": "pending",
  "payment_method": "credit_card",
  "shipping_address": "Calle Principal 123, Ciudad, País",
  "billing_address": "Calle Principal 123, Ciudad, País",
  "notes": "Entregar en horario de oficina",
  "customer_user_name": null,
  "items": [
    {
      "id": 1,
      "order_id": 1,
      "product_id": 1,
      "product_name": "iPhone 15 Pro",
      "product_sku": "IPH15PRO001",
      "quantity": 2,
      "price": "999.99",
      "total": "1999.98",
      "created_at": "2025-09-03 00:00:00"
    }
  ],
  "created_at": "2025-09-03 00:00:00",
  "updated_at": "2025-09-03 00:00:00"
}
```

---

### POST `/api/admin/orders`
**Descripción:** Crear orden

**Request Body:**
```json
{
  "customer_name": "María García",
  "customer_email": "maria.garcia@example.com",
  "customer_phone": "+1234567890",
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ],
  "tax_amount": 150.00,
  "shipping_amount": 50.00,
  "payment_method": "credit_card",
  "shipping_address": "Calle Principal 123, Ciudad, País",
  "billing_address": "Calle Principal 123, Ciudad, País",
  "notes": "Entregar en horario de oficina"
}
```

**Response (201 Created):**
```json
{
  "message": "Order created successfully",
  "order_id": 1,
  "order_number": "ORD20250903001"
}
```

**Errores:**
- `400` [field] is required / Invalid item data / Product not found / Insufficient stock

---

### PUT `/api/admin/orders/{id}/status`
**Descripción:** Actualizar estado de orden

**Request Body:**
```json
{
  "status": "processing"
}
```

**Estados válidos:** pending, processing, shipped, delivered, cancelled

**Response (200 OK):**
```json
{
  "message": "Order status updated successfully"
}
```

**Errores:**
- `400` Status is required / Invalid status
- `404` Order not found

---

### DELETE `/api/admin/orders/{id}`
**Descripción:** Eliminar orden

**Response (200 OK):**
```json
{
  "message": "Order deleted successfully"
}
```

**Errores:**
- `404` Order not found
- `400` Cannot delete processed orders (solo se pueden eliminar órdenes cancelled o pending)

---

## Dashboard (Admin)

**Autenticación requerida:** `Authorization: Bearer {token}`

### GET `/api/dashboard/stats`
**Descripción:** Obtener estadísticas del dashboard

**Response (200 OK):**
```json
{
  "totals": {
    "total_products": 25,
    "total_orders": 150,
    "total_users": 75,
    "total_revenue": "125000.00",
    "monthly_revenue": "15000.00",
    "pending_orders": 5
  },
  "monthly_sales": [
    {
      "month": "2025-08",
      "orders": 45,
      "revenue": "18500.00"
    },
    {
      "month": "2025-09",
      "orders": 52,
      "revenue": "22100.00"
    }
  ],
  "top_products": [
    {
      "name": "iPhone 15 Pro",
      "price": "999.99",
      "total_sold": 25,
      "total_revenue": "24999.75"
    }
  ],
  "recent_orders": [
    {
      "id": 1,
      "order_number": "ORD20250903001",
      "customer_name": "María García",
      "total_amount": "2199.98",
      "status": "pending",
      "payment_status": "pending",
      "created_at": "2025-09-03 00:00:00"
    }
  ],
  "low_stock": [
    {
      "id": 5,
      "name": "MacBook Pro",
      "sku": "MBP16001",
      "stock": 2,
      "min_stock": 5
    }
  ]
}
```

---

## Utilidades

### GET `/`
**Descripción:** Health check de la API

**Response (200 OK):**
```json
{
  "message": "Ecommerce API v1.0",
  "status": "running",
  "timestamp": "2025-09-03 12:00:00"
}
```

---

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| 200 | OK - Solicitud exitosa |
| 201 | Created - Recurso creado exitosamente |
| 400 | Bad Request - Error en los datos enviados |
| 401 | Unauthorized - Token faltante o inválido |
| 404 | Not Found - Recurso no encontrado |
| 500 | Internal Server Error - Error interno del servidor |

---

## Estructura de Errores

**Formato estándar de respuesta de error:**
```json
{
  "error": "Descripción del error"
}
```

**Ejemplos comunes:**
- `{"error": "No authorization header"}`
- `{"error": "Invalid token"}`
- `{"error": "Email and password required"}`
- `{"error": "Product not found"}`
- `{"error": "SKU already exists"}`

---

## Notas Importantes

### Autenticación
- Todas las rutas `/api/admin/*` requieren autenticación JWT
- El token se obtiene mediante `/api/auth/login`
- El token debe incluirse en el header: `Authorization: Bearer {token}`
- El token expira en 24 horas por defecto

### Paginación
- Parámetro `page`: Número de página (mínimo: 1)
- Parámetro `limit`: Elementos por página (mínimo: 1, máximo: 100)
- La respuesta incluye información de paginación en el objeto `pagination`

### Filtros y Búsquedas
- Los parámetros de búsqueda permiten valores vacíos
- Las búsquedas son case-insensitive y usan LIKE con %
- Los filtros por estado/rol deben usar valores exactos

### Gestión de Stock
- Al crear una orden, el stock se reduce automáticamente
- Al eliminar una orden pending/cancelled, el stock se restaura
- Las validaciones de stock se realizan al crear órdenes

### Base de Datos
- Todas las fechas están en formato MySQL TIMESTAMP
- Los precios se almacenan como DECIMAL(10,2)
- Los campos de texto soportan UTF-8

### Credenciales por Defecto
- **Email:** admin@ecommerce.com
- **Password:** password
- **Rol:** admin