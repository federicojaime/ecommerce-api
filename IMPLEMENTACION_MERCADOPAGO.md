# 📋 Plan de Implementación: Login de Clientes y Pagos con Mercado Pago

## 📊 Estado Actual del Sistema

### ✅ Lo que YA está implementado:

#### 1. **Autenticación Básica**
- ✅ Login y registro de usuarios ([AuthController.php](src/Controllers/AuthController.php))
- ✅ JWT para tokens de autenticación
- ✅ Middleware de autenticación ([AuthMiddleware.php](src/Middleware/AuthMiddleware.php))
- ✅ Recuperación de contraseña (forgot/reset password)
- ✅ Login con Google OAuth

#### 2. **Carrito de Compras**
- ✅ Tabla `carts` y `cart_items` en BD
- ✅ Controller completo ([CartController.php](src/Controllers/CartController.php))
- ✅ Endpoints funcionales:
  - `GET /api/cart` - Ver carrito
  - `POST /api/cart` - Agregar item
  - `PUT /api/cart/items/{id}` - Actualizar cantidad
  - `DELETE /api/cart/items/{id}` - Eliminar item
  - `DELETE /api/cart` - Vaciar carrito

#### 3. **Checkout Básico**
- ✅ Controller parcial ([CheckoutController.php](src/Controllers/CheckoutController.php))
- ✅ Validación de datos
- ✅ Cálculo de totales (subtotal, impuestos, envío)
- ✅ Aplicación de cupones
- ✅ Creación de órdenes
- ✅ Reducción automática de stock

#### 4. **Gestión de Órdenes**
- ✅ Tabla `orders` y `order_items` en BD
- ✅ OrderController para admin
- ✅ CustomerOrderController para clientes
- ✅ Ver órdenes del cliente
- ✅ Cancelar órdenes pendientes

#### 5. **Funcionalidades Adicionales**
- ✅ Wishlist (lista de deseos)
- ✅ Reseñas de productos
- ✅ Direcciones de envío
- ✅ Cupones y descuentos
- ✅ Notificaciones

---

## ❌ Lo que FALTA implementar:

### 🔴 CRÍTICO: Integración con Mercado Pago

**Estado actual:** No existe integración alguna con Mercado Pago

**Lo que se necesita:**

#### 1. **SDK de Mercado Pago**
Instalar el SDK oficial:
```bash
composer require mercadopago/dx-php
```

#### 2. **Configuración en .env**
Agregar credenciales de producción al archivo `.env`:
```env
# Mercado Pago - Credenciales de Producción
MP_CLIENT_ID=1663267329194942
MP_CLIENT_SECRET=gDiAKjhtPUM7ytcMLgdZvaC7tkmQTwon
MP_PUBLIC_KEY=APP_USR-b2367926-39ff-4190-b5db-cdbecef583ee
MP_ACCESS_TOKEN=APP_USR-1663267329194942-102319-0e29e47f5e38dbaff74ba6266870015c-409852850
MP_WEBHOOK_SECRET=tu_webhook_secret_aqui
```

#### 3. **Crear servicio de Mercado Pago**
Archivo nuevo: `src/Services/MercadoPagoService.php`

**Responsabilidades:**
- Crear preferencia de pago
- Procesar webhooks de Mercado Pago
- Validar pagos
- Actualizar estado de órdenes

#### 4. **Tabla de pagos en BD**
Crear tabla `payments` para registrar transacciones:

```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id VARCHAR(255), -- ID de Mercado Pago
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'approved', 'rejected', 'cancelled', 'refunded'),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ARS',
    external_reference VARCHAR(255),
    payment_data JSON, -- Datos completos de MP
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id)
);
```

#### 5. **Nuevos Endpoints**

**Para el Checkout:**
```
POST   /api/checkout/mercadopago/create-preference
  → Crea preferencia de pago en MP
  → Devuelve init_point (URL de pago)

GET    /api/checkout/mercadopago/success
  → Página de éxito después del pago

GET    /api/checkout/mercadopago/failure
  → Página de error si falla el pago

GET    /api/checkout/mercadopago/pending
  → Página de pago pendiente
```

**Para Webhooks (IPN):**
```
POST   /api/webhooks/mercadopago
  → Recibe notificaciones de MP
  → Actualiza estado de órdenes
```

**Para consultar pagos:**
```
GET    /api/payments/{orderId}
  → Ver estado del pago de una orden
```

---

## 🎯 Plan de Implementación Paso a Paso

### **FASE 1: Preparación (30 minutos)**

#### Paso 1.1: Instalar SDK de Mercado Pago
```bash
cd c:\xampp5\htdocs\ecommerce-api
composer require mercadopago/dx-php
```

#### Paso 1.2: Actualizar .env
Agregar las credenciales de MP al archivo `.env`

#### Paso 1.3: Crear tabla de pagos
Ejecutar el SQL de creación de tabla `payments`

---

### **FASE 2: Servicio de Mercado Pago (1-2 horas)**

#### Paso 2.1: Crear MercadoPagoService.php
**Archivo:** `src/Services/MercadoPagoService.php`

**Métodos principales:**
- `createPreference($orderData)` - Crear preferencia de pago
- `getPaymentInfo($paymentId)` - Obtener info de un pago
- `processWebhook($data)` - Procesar notificación de MP
- `validatePayment($paymentId)` - Validar estado de pago

#### Paso 2.2: Crear MercadoPagoController.php
**Archivo:** `src/Controllers/MercadoPagoController.php`

**Métodos:**
- `createPreference()` - Endpoint para crear preferencia
- `handleWebhook()` - Endpoint para recibir webhooks
- `success()` - Página de éxito
- `failure()` - Página de error
- `pending()` - Página de pendiente

---

### **FASE 3: Modificar Checkout (1 hora)**

#### Paso 3.1: Actualizar CheckoutController
Modificar el método `complete()` para:
1. Crear la orden en estado 'pending'
2. Crear preferencia de pago en Mercado Pago
3. Devolver el `init_point` al frontend
4. NO vaciar el carrito todavía (esperar confirmación de pago)

#### Paso 3.2: Agregar validación de método de pago
Solo permitir `mercadopago` como método de pago

---

### **FASE 4: Webhooks y Confirmación (1-2 horas)**

#### Paso 4.1: Implementar procesamiento de webhooks
Cuando Mercado Pago notifique:
1. Validar la firma del webhook
2. Obtener info del pago desde MP
3. Buscar la orden por `external_reference`
4. Actualizar estado de la orden:
   - `approved` → orden a 'paid'
   - `rejected` → orden a 'cancelled'
   - `pending` → orden sigue en 'pending'
5. Guardar info del pago en tabla `payments`
6. Vaciar carrito si pago aprobado
7. Enviar email de confirmación

#### Paso 4.2: Crear PaymentController
Para que el cliente pueda consultar el estado de su pago

---

### **FASE 5: Frontend (Solo endpoints necesarios)**

#### Endpoints que el frontend debe llamar:

**Flujo completo de checkout:**

1. **Validar datos:**
   ```
   POST /api/checkout/validate
   Body: {
     customer_name, customer_email, customer_phone,
     shipping_address
   }
   ```

2. **Calcular totales:**
   ```
   POST /api/checkout/calculate
   Body: {
     coupon_code (opcional)
   }
   ```

3. **Crear preferencia de MP:**
   ```
   POST /api/checkout/mercadopago/create-preference
   Body: {
     customer_name, customer_email, customer_phone,
     shipping_address, billing_address, notes
   }
   Response: {
     init_point: "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=...",
     preference_id: "123456789",
     order_id: 42
   }
   ```

4. **Redirigir a Mercado Pago:**
   ```javascript
   window.location.href = response.init_point;
   ```

5. **Recibir respuesta:**
   Mercado Pago redirige a:
   - Success: `/checkout/success?order_id=42&payment_id=123456789`
   - Failure: `/checkout/failure?order_id=42`
   - Pending: `/checkout/pending?order_id=42`

6. **Consultar estado del pago:**
   ```
   GET /api/payments/{orderId}
   Response: {
     status: "approved" | "pending" | "rejected",
     payment_id: "123456789",
     amount: 1500.00,
     payment_method: "visa"
   }
   ```

---

## 🔐 Seguridad y Validaciones

### 1. **Validación de Webhooks**
```php
// Validar firma de Mercado Pago
$signature = $_SERVER['HTTP_X_SIGNATURE'];
$dataID = $_GET['data.id'];
// Validar con el secret
```

### 2. **Prevención de Fraude**
- Validar que el monto pagado coincida con el total de la orden
- Validar que la orden no haya sido procesada anteriormente
- Verificar que el payment_id sea único
- Guardar todos los datos del webhook para auditoría

### 3. **Manejo de Estados**
```
Estados de orden:
- pending → Orden creada, esperando pago
- paid → Pago confirmado
- processing → En preparación
- shipped → Enviado
- delivered → Entregado
- cancelled → Cancelado
- refunded → Reembolsado
```

---

## 📝 Configuración en Mercado Pago Dashboard

### 1. **Configurar URLs de retorno**
En tu cuenta de Mercado Pago → Configuración → Credenciales:

```
Success URL: https://decohomesinrival.com.ar/checkout/success
Failure URL: https://decohomesinrival.com.ar/checkout/failure
Pending URL: https://decohomesinrival.com.ar/checkout/pending
```

### 2. **Configurar Webhook (IPN)**
```
Notification URL: https://decohomesinrival.com.ar/ecommerce-api/public/api/webhooks/mercadopago
```

### 3. **Configurar Webhook Secret**
Generar un secret aleatorio y guardarlo en `.env`:
```bash
openssl rand -hex 32
```

---

## 🧪 Testing

### 1. **Credenciales de Prueba**
Antes de usar producción, probar con credenciales de test:
- Entrar a Mercado Pago Developer → Test Accounts
- Crear cuentas de prueba
- Usar tarjetas de test: https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing

### 2. **Flujo de prueba**
1. Crear orden con credenciales de test
2. Pagar con tarjeta de test
3. Verificar que el webhook se recibe
4. Verificar que la orden cambia a 'paid'
5. Verificar que se guarda el payment

### 3. **Casos de error a probar**
- Pago rechazado
- Timeout de Mercado Pago
- Webhook duplicado
- Orden ya procesada

---

## 📊 Diagrama de Flujo

```
CLIENTE                    BACKEND                 MERCADO PAGO
   |                          |                          |
   |-- 1. Ver carrito ------->|                          |
   |<- Items del carrito -----|                          |
   |                          |                          |
   |-- 2. Iniciar checkout -->|                          |
   |<- Validación OK ---------|                          |
   |                          |                          |
   |-- 3. Confirmar compra -->|                          |
   |                          |-- Crear preferencia ---->|
   |                          |<- Preference ID ---------|
   |<- init_point URL --------|                          |
   |                          |                          |
   |========== Redirigir a Mercado Pago ================>|
   |                          |                          |
   |                          |<-- Webhook (pago OK) ----|
   |                          |                          |
   |                          |-- Validar pago --------->|
   |                          |<- Payment info ----------|
   |                          |                          |
   |                          |- Actualizar orden        |
   |                          |- Guardar payment         |
   |                          |- Vaciar carrito          |
   |                          |- Enviar email            |
   |                          |                          |
   |<====== Redirigir a /success ========================|
   |                          |                          |
   |-- Ver estado orden ----->|                          |
   |<- Orden PAID ------------|                          |
```

---

## 💰 Costos de Mercado Pago

### Comisiones (Argentina - 2025)
- **Tarjeta de crédito:** ~4.99% + IVA
- **Tarjeta de débito:** ~3.50% + IVA
- **Mercado Pago Wallet:** ~3.50% + IVA

### Tiempo de acreditación
- Mercado Pago saldo: Inmediato
- Cuenta bancaria: 1-2 días hábiles

---

## ✅ Checklist de Implementación

### Backend
- [ ] Instalar SDK de Mercado Pago
- [ ] Configurar credenciales en `.env`
- [ ] Crear tabla `payments`
- [ ] Crear `MercadoPagoService.php`
- [ ] Crear `MercadoPagoController.php`
- [ ] Modificar `CheckoutController::complete()`
- [ ] Implementar webhook handler
- [ ] Crear endpoint de consulta de pagos
- [ ] Agregar rutas en `index.php`
- [ ] Probar con credenciales de test

### Mercado Pago Dashboard
- [ ] Configurar URLs de retorno
- [ ] Configurar webhook URL
- [ ] Generar y guardar webhook secret
- [ ] Activar credenciales de producción
- [ ] Configurar descripción del negocio

### Frontend
- [ ] Implementar botón "Pagar con Mercado Pago"
- [ ] Redirigir a `init_point`
- [ ] Crear páginas success/failure/pending
- [ ] Mostrar estado del pago
- [ ] Manejar errores de pago

### Testing
- [ ] Pago exitoso con tarjeta de test
- [ ] Pago rechazado
- [ ] Webhook recibido correctamente
- [ ] Orden actualizada correctamente
- [ ] Email de confirmación enviado
- [ ] Stock reducido correctamente
- [ ] Carrito vaciado después de pago

### Producción
- [ ] Cambiar a credenciales de producción
- [ ] Verificar URLs en MP dashboard
- [ ] Habilitar logs de errores
- [ ] Configurar monitoreo de webhooks
- [ ] Probar pago real con monto mínimo

---

## 🚀 Tiempo Estimado

| Fase | Tiempo | Prioridad |
|------|--------|-----------|
| Instalación SDK y configuración | 30 min | Alta |
| Crear servicio de MP | 1-2 h | Alta |
| Modificar checkout | 1 h | Alta |
| Implementar webhooks | 2 h | Alta |
| Testing | 1-2 h | Alta |
| **TOTAL** | **5-7 horas** | - |

---

## 📞 Soporte

### Documentación oficial
- **Mercado Pago PHP SDK:** https://github.com/mercadopago/sdk-php
- **Checkout Pro:** https://www.mercadopago.com.ar/developers/es/docs/checkout-pro
- **Webhooks:** https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks

### Credenciales provistas
```
Client ID: 1663267329194942
Client Secret: gDiAKjhtPUM7ytcMLgdZvaC7tkmQTwon
Public Key: APP_USR-b2367926-39ff-4190-b5db-cdbecef583ee
Access Token: APP_USR-1663267329194942-102319-0e29e47f5e38dbaff74ba6266870015c-409852850
```

**⚠️ IMPORTANTE:** Estas son credenciales de **PRODUCCIÓN**. Manejar con cuidado.

---

## 🎯 Siguiente Paso

**Empezar por la FASE 1**: Instalación del SDK y configuración básica.

¿Quieres que proceda con la implementación del código?
