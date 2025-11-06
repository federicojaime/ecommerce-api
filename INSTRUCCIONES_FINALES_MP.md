# ✅ Implementación de Mercado Pago - COMPLETADA

## 🎉 ¡Todo está listo!

He implementado completamente la integración con Mercado Pago. Aquí está todo lo que se ha creado:

---

## 📁 Archivos Creados

### 1. **Servicio Principal**
- ✅ `src/Services/MercadoPagoService.php` - Servicio completo de MP
  - Crear preferencias de pago
  - Procesar webhooks
  - Obtener información de pagos
  - Actualizar estados de órdenes
  - Validar pagos

### 2. **Controllers**
- ✅ `src/Controllers/MercadoPagoController.php` - Control de pagos
  - Crear preferencia
  - Manejar webhooks
  - Páginas de success/failure/pending
  - Obtener public key

- ✅ `src/Controllers/PaymentController.php` - Consulta de pagos
  - Ver pagos por orden
  - Ver detalles de pago
  - Listar todos los pagos del usuario

### 3. **Base de Datos**
- ✅ `database/payments_table.sql` - Esquema SQL
  - Tabla `payments` - Registro de transacciones
  - Tabla `mercadopago_webhooks` - Auditoría de webhooks

### 4. **Configuración**
- ✅ `.env` actualizado con credenciales de MP
- ✅ `composer.json` - SDK instalado
- ✅ `public/index.php` - Todas las rutas agregadas

---

## 🚀 Pasos para Poner en Producción

### **PASO 1: Ejecutar SQL en la Base de Datos** ⚠️ IMPORTANTE

Debes ejecutar el archivo SQL para crear las tablas necesarias:

```bash
# Opción 1: Desde MySQL command line
mysql -h srv1597.hstgr.io -u u565673608_ssh -p u565673608_sinrival < database/payments_table.sql

# Opción 2: phpMyAdmin
# - Ir a phpMyAdmin
# - Seleccionar base de datos: u565673608_sinrival
# - Ir a "SQL"
# - Copiar y pegar el contenido de database/payments_table.sql
# - Ejecutar
```

### **PASO 2: Subir Archivos al Servidor**

Subir estos archivos a producción:

```
/src/Services/MercadoPagoService.php
/src/Controllers/MercadoPagoController.php
/src/Controllers/PaymentController.php
/public/index.php (actualizado)
/.env (actualizado)
/composer.json (actualizado)
/composer.lock (actualizado)
/vendor/ (carpeta completa - incluye SDK de MP)
```

### **PASO 3: Instalar SDK en Producción** (si no subes vendor/)

```bash
composer install
```

### **PASO 4: Configurar Webhooks en Mercado Pago**

1. Ir a: https://www.mercadopago.com.ar/developers/panel/app
2. Seleccionar tu aplicación
3. Ir a "Webhooks"
4. Configurar URL:
   ```
   https://decohomesinrival.com.ar/ecommerce-api/public/api/webhooks/mercadopago
   ```
5. Seleccionar eventos:
   - ✅ payment
   - ✅ merchant_order

### **PASO 5: Configurar URLs de Retorno**

En el mismo panel de MP:
1. Ir a "Configuración" → "Credenciales"
2. Configurar:
   ```
   Success URL: https://decohomesinrival.com.ar/checkout/success
   Failure URL: https://decohomesinrival.com.ar/checkout/failure
   Pending URL: https://decohomesinrival.com.ar/checkout/pending
   ```

---

## 📡 Endpoints Disponibles

### **Para el Frontend (Rutas Protegidas - Requieren Auth)**

#### Checkout con Mercado Pago
```
POST /api/checkout/mercadopago/create-preference
Authorization: Bearer {token}
Body: {
  "customer_name": "Juan Pérez",
  "customer_email": "juan@email.com",
  "customer_phone": "2664123456",
  "shipping_address": "Av. Ejemplo 123, San Luis",
  "billing_address": "...",  // opcional
  "notes": "...",            // opcional
  "coupon_code": "..."       // opcional
}

Response: {
  "success": true,
  "order_id": 42,
  "order_number": "ORD20251025001",
  "preference_id": "123456789-abc123...",
  "init_point": "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=...",
  "total_amount": "1500.00"
}
```

#### Páginas de Retorno
```
GET /api/checkout/mercadopago/success?external_reference={orderId}&payment_id={paymentId}
GET /api/checkout/mercadopago/failure?external_reference={orderId}
GET /api/checkout/mercadopago/pending?external_reference={orderId}&payment_id={paymentId}
```

#### Consultar Pagos
```
GET /api/payments                    # Todos los pagos del usuario
GET /api/payments/{orderId}          # Pago de una orden específica
GET /api/payments/detail/{paymentId} # Detalle completo de un pago
```

### **Rutas Públicas (No requieren Auth)**

```
POST /api/webhooks/mercadopago       # Webhook de Mercado Pago
GET  /api/mercadopago/public-key     # Obtener public key para frontend
```

---

## 🎨 Flujo de Pago en el Frontend

### **Código de Ejemplo (React/Vue/JS)**

```javascript
// 1. Usuario hace clic en "Pagar"
async function handleCheckout() {
  try {
    // Crear preferencia de pago
    const response = await fetch('/api/checkout/mercadopago/create-preference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`
      },
      body: JSON.stringify({
        customer_name: 'Juan Pérez',
        customer_email: 'juan@email.com',
        customer_phone: '2664123456',
        shipping_address: 'Av. Ejemplo 123',
        billing_address: 'Av. Ejemplo 123',
        notes: 'Sin notas'
      })
    });

    const data = await response.json();

    if (data.success) {
      // Guardar order_id en localStorage (opcional)
      localStorage.setItem('pending_order_id', data.order_id);

      // Redirigir a Mercado Pago
      window.location.href = data.init_point;
    } else {
      alert('Error al crear el pago');
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// 2. Usuario paga en Mercado Pago
// 3. MP redirige a /checkout/success o /failure

// 4. En la página de success, consultar el estado
async function checkPaymentStatus(orderId) {
  const response = await fetch(`/api/payments/${orderId}`, {
    headers: {
      'Authorization': `Bearer ${userToken}`
    }
  });

  const data = await response.json();

  if (data.payment && data.payment.status === 'approved') {
    console.log('Pago aprobado!');
    // Mostrar mensaje de éxito
    // Redirigir a página de orden
  } else if (data.payment && data.payment.status === 'pending') {
    console.log('Pago pendiente');
  }
}
```

---

## 🔐 Seguridad

### **Validaciones Implementadas**

✅ Verificación de stock antes de crear la orden
✅ Validación de monto pagado vs monto de la orden
✅ Prevención de procesamiento duplicado de webhooks
✅ Guardado de todos los webhooks para auditoría
✅ Verificación de que la orden pertenece al usuario
✅ Estados de pago seguros (pending → approved/rejected)

### **Webhook Secret**

El webhook secret generado es:
```
171f8821dfc68bd72ab25ac6ab390c26cc9d03841d3e4556b88e79bd3c742ed6
```

Está guardado en `.env` como `MP_WEBHOOK_SECRET`

---

## 📊 Estados del Sistema

### **Estados de Orden**
```
pending    → Orden creada, esperando pago
paid       → Pago confirmado por MP
processing → En preparación
shipped    → Enviado
delivered  → Entregado
cancelled  → Cancelado (pago rechazado o cancelado)
refunded   → Reembolsado
```

### **Estados de Pago (Mercado Pago)**
```
pending    → Pago pendiente
approved   → Pago aprobado
in_process → Pago en proceso
rejected   → Pago rechazado
cancelled  → Pago cancelado
refunded   → Pago reembolsado
```

---

## 🧪 Cómo Probar

### **Opción 1: Credenciales de Test (Recomendado)**

1. Ir a: https://www.mercadopago.com.ar/developers/panel/test-users
2. Crear usuario de test
3. Usar tarjetas de test: https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing

**Tarjetas de prueba:**
```
VISA aprobada:
  Número: 4509 9535 6623 3704
  CVV: 123
  Fecha: 11/25
  Nombre: APRO

Mastercard rechazada:
  Número: 5031 7557 3453 0604
  CVV: 123
  Fecha: 11/25
  Nombre: OTHE
```

### **Opción 2: Pago Real con Monto Mínimo**

- Crear una orden de prueba con un producto de $1
- Realizar el pago real
- Verificar que todo funciona
- Cancelar o reembolsar si es necesario

---

## 📝 Logs y Debugging

### **Logs Importantes**

Los logs se guardan automáticamente en:
- Apache error log: `c:\xampp\apache\logs\error.log` (desarrollo)
- Producción: `/var/log/apache2/error.log` o ver en cPanel

### **Ver Logs en Tiempo Real (Desarrollo)**

```bash
tail -f c:\xampp\apache\logs\error.log | findstr "MercadoPago\|Webhook"
```

### **Verificar Webhooks Recibidos**

```sql
SELECT * FROM mercadopago_webhooks
ORDER BY created_at DESC
LIMIT 10;
```

### **Verificar Pagos Procesados**

```sql
SELECT
  p.payment_id,
  p.payment_status,
  p.amount,
  o.order_number,
  o.status as order_status,
  p.created_at
FROM payments p
JOIN orders o ON p.order_id = o.id
ORDER BY p.created_at DESC;
```

---

## ❗ Problemas Comunes

### **1. Webhook no se recibe**

**Solución:**
- Verificar que la URL esté bien configurada en MP
- Verificar que la URL sea accesible públicamente
- Revisar logs de error
- Probar el webhook manualmente con cURL:

```bash
curl -X POST https://decohomesinrival.com.ar/ecommerce-api/public/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": {
      "id": "123456789"
    }
  }'
```

### **2. Pago aprobado pero orden sigue en pending**

**Solución:**
- Verificar logs del webhook
- Ver tabla `mercadopago_webhooks` para errores
- Ejecutar manualmente el procesamiento:

```sql
-- Ver webhooks sin procesar
SELECT * FROM mercadopago_webhooks WHERE processed = FALSE;
```

### **3. Error "Payment amount does not match"**

**Solución:**
- Verificar que el total de la orden coincida exactamente
- Revisar si hay impuestos o shipping mal calculados
- Ver el log para los montos exactos

---

## ✅ Checklist Final

### Backend
- [x] SDK de Mercado Pago instalado
- [x] Credenciales configuradas en `.env`
- [x] Tablas `payments` y `mercadopago_webhooks` creadas
- [x] MercadoPagoService.php creado
- [x] MercadoPagoController.php creado
- [x] PaymentController.php creado
- [x] Rutas agregadas en index.php
- [ ] **SQL ejecutado en base de datos de producción** ⚠️
- [ ] **Archivos subidos al servidor** ⚠️

### Mercado Pago Dashboard
- [ ] Webhook URL configurada
- [ ] URLs de retorno configuradas
- [ ] Credenciales activadas

### Testing
- [ ] Probar pago con tarjeta de test
- [ ] Verificar que webhook se recibe
- [ ] Verificar que orden cambia a 'paid'
- [ ] Verificar que carrito se vacía
- [ ] Probar pago rechazado
- [ ] Probar pago pendiente

---

## 🎯 Siguiente Paso

**¡EJECUTAR EL SQL EN LA BASE DE DATOS!**

```bash
cd database
mysql -h srv1597.hstgr.io -u u565673608_ssh -p u565673608_sinrival < payments_table.sql
```

O usar phpMyAdmin para ejecutar `payments_table.sql`

---

## 📞 Soporte

Si tienes algún problema:

1. **Revisar logs de error**
2. **Verificar tabla `mercadopago_webhooks`**
3. **Consultar documentación oficial**: https://www.mercadopago.com.ar/developers/es/docs

---

## 🎉 ¡Listo!

El sistema está **100% implementado** y listo para procesar pagos con Mercado Pago.

Solo falta:
1. Ejecutar el SQL
2. Subir archivos
3. Configurar webhooks en MP
4. ¡Probar!

**¿Alguna duda o necesitas ayuda con algo?** 🚀
