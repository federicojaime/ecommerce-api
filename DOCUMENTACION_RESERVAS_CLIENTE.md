# Documentación de Reservas - Frontend Cliente

## 📌 Endpoint Principal (Único para Clientes)

### POST /api/reservations
Crear una nueva reserva. **NO requiere autenticación** - es completamente público.

---

## 🔗 URL del Endpoint

```
POST https://tu-dominio.com/api/reservations
```

---

## 📥 Request (Lo que envías)

### Headers
```javascript
{
  "Content-Type": "application/json"
}
```

### Body (JSON)
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

### Campos OBLIGATORIOS ⚠️
- `customer_name` (string) - Nombre completo
- `customer_email` (string) - Email válido
- `customer_phone` (string) - Teléfono de contacto
- `items` (array) - Array con los productos
  - `product_id` (número) - ID del producto
  - `quantity` (número) - Cantidad
  - `price` (número decimal) - Precio unitario

### Campos OPCIONALES
- `shipping_address` (string) - Dirección de envío
- `shipping_city` (string) - Ciudad
- `shipping_state` (string) - Provincia/Estado
- `shipping_zip_code` (string) - Código postal
- `notes` (string) - Comentarios del cliente
- `discount_amount` (número) - Descuento aplicado
- `shipping_amount` (número) - Costo de envío
- `tax_amount` (número) - Impuestos

---

## 📤 Response (Lo que recibes)

### ✅ Éxito (201 Created)
```json
{
  "message": "Reservation created successfully",
  "reservation_id": 15,
  "reservation_number": "RES20251106A3F2",
  "status": "pending",
  "total": 38500.00
}
```

### ❌ Errores Posibles

#### 400 - Campos faltantes
```json
{
  "error": "Missing required fields: customer_name, customer_email, customer_phone, items"
}
```

#### 400 - Email inválido
```json
{
  "error": "Invalid email format"
}
```

#### 400 - Sin productos
```json
{
  "error": "Items array cannot be empty"
}
```

#### 404 - Producto no existe
```json
{
  "error": "Product with ID 123 not found or inactive"
}
```

#### 500 - Error del servidor
```json
{
  "error": "Error creating reservation: [detalle]"
}
```

---

## 💻 Implementación en JavaScript Vanilla

### Ejemplo Completo
```javascript
// Función para crear reserva
async function crearReserva(datosFormulario, productosCarrito) {
  try {
    // Preparar datos
    const reservationData = {
      customer_name: datosFormulario.nombre,
      customer_email: datosFormulario.email,
      customer_phone: datosFormulario.telefono,
      shipping_address: datosFormulario.direccion || '',
      shipping_city: datosFormulario.ciudad || '',
      shipping_state: datosFormulario.provincia || '',
      shipping_zip_code: datosFormulario.codigoPostal || '',
      notes: datosFormulario.comentarios || '',
      items: productosCarrito.map(producto => ({
        product_id: producto.id,
        quantity: producto.cantidad,
        price: producto.precio
      }))
    };

    // Hacer request
    const response = await fetch('https://tu-api.com/api/reservations', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(reservationData)
    });

    const data = await response.json();

    if (response.ok) {
      // ÉXITO - Mostrar página de confirmación
      mostrarExito(data.reservation_number);
      limpiarCarrito();
      redirigirAPaginaExito(data.reservation_number);
    } else {
      // ERROR - Mostrar mensaje
      mostrarError(data.error);
    }

  } catch (error) {
    console.error('Error de red:', error);
    mostrarError('Error de conexión. Por favor, intenta nuevamente.');
  }
}

// Función para mostrar éxito
function mostrarExito(numeroReserva) {
  alert(`¡Reserva #${numeroReserva} creada exitosamente!\n\nTe contactaremos en las próximas 24-48 horas para confirmar tu pedido.`);
}

// Función para mostrar error
function mostrarError(mensaje) {
  alert(`Error: ${mensaje}`);
}

// Función para limpiar carrito
function limpiarCarrito() {
  localStorage.removeItem('carrito');
  // O tu método de limpiar carrito
}

// Función para redirigir
function redirigirAPaginaExito(numeroReserva) {
  window.location.href = `/reserva-exitosa?numero=${numeroReserva}`;
}
```

### Validación del Formulario
```javascript
function validarFormulario(datos) {
  const errores = [];

  // Validar nombre
  if (!datos.nombre || datos.nombre.trim().length < 3) {
    errores.push('El nombre debe tener al menos 3 caracteres');
  }

  // Validar email
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!datos.email || !emailRegex.test(datos.email)) {
    errores.push('Email inválido');
  }

  // Validar teléfono
  if (!datos.telefono || datos.telefono.trim().length < 8) {
    errores.push('Teléfono inválido');
  }

  // Validar que hay productos en el carrito
  if (!datos.productos || datos.productos.length === 0) {
    errores.push('El carrito está vacío');
  }

  return errores;
}

// Uso
const errores = validarFormulario(datosFormulario);
if (errores.length > 0) {
  alert('Errores:\n' + errores.join('\n'));
} else {
  crearReserva(datosFormulario, productosCarrito);
}
```

---

## ⚛️ Implementación en React

### Componente Completo
```jsx
import React, { useState } from 'react';

function FormularioReserva({ productosCarrito, onExito, onError }) {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    nombre: '',
    email: '',
    telefono: '',
    direccion: '',
    ciudad: '',
    provincia: '',
    codigoPostal: '',
    comentarios: ''
  });

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const reservationData = {
        customer_name: formData.nombre,
        customer_email: formData.email,
        customer_phone: formData.telefono,
        shipping_address: formData.direccion,
        shipping_city: formData.ciudad,
        shipping_state: formData.provincia,
        shipping_zip_code: formData.codigoPostal,
        notes: formData.comentarios,
        items: productosCarrito.map(item => ({
          product_id: item.id,
          quantity: item.cantidad,
          price: item.precio
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
        onExito(data);
      } else {
        onError(data.error);
      }

    } catch (error) {
      onError('Error de conexión');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="formulario-reserva">
      <h2>Datos de Contacto</h2>

      <div className="campo">
        <label htmlFor="nombre">Nombre Completo *</label>
        <input
          type="text"
          id="nombre"
          name="nombre"
          value={formData.nombre}
          onChange={handleChange}
          required
          minLength={3}
        />
      </div>

      <div className="campo">
        <label htmlFor="email">Email *</label>
        <input
          type="email"
          id="email"
          name="email"
          value={formData.email}
          onChange={handleChange}
          required
        />
      </div>

      <div className="campo">
        <label htmlFor="telefono">Teléfono *</label>
        <input
          type="tel"
          id="telefono"
          name="telefono"
          value={formData.telefono}
          onChange={handleChange}
          required
          minLength={8}
          placeholder="+54 9 11 1234-5678"
        />
      </div>

      <h2>Dirección de Envío (Opcional)</h2>

      <div className="campo">
        <label htmlFor="direccion">Dirección</label>
        <input
          type="text"
          id="direccion"
          name="direccion"
          value={formData.direccion}
          onChange={handleChange}
        />
      </div>

      <div className="campo">
        <label htmlFor="ciudad">Ciudad</label>
        <input
          type="text"
          id="ciudad"
          name="ciudad"
          value={formData.ciudad}
          onChange={handleChange}
        />
      </div>

      <div className="campo">
        <label htmlFor="provincia">Provincia</label>
        <input
          type="text"
          id="provincia"
          name="provincia"
          value={formData.provincia}
          onChange={handleChange}
        />
      </div>

      <div className="campo">
        <label htmlFor="codigoPostal">Código Postal</label>
        <input
          type="text"
          id="codigoPostal"
          name="codigoPostal"
          value={formData.codigoPostal}
          onChange={handleChange}
        />
      </div>

      <div className="campo">
        <label htmlFor="comentarios">Comentarios Adicionales</label>
        <textarea
          id="comentarios"
          name="comentarios"
          value={formData.comentarios}
          onChange={handleChange}
          rows={4}
          placeholder="Horarios preferidos, instrucciones especiales, etc."
        />
      </div>

      <button
        type="submit"
        disabled={loading || productosCarrito.length === 0}
        className="btn-reservar"
      >
        {loading ? 'Procesando...' : 'Crear Reserva'}
      </button>

      {productosCarrito.length === 0 && (
        <p className="error">El carrito está vacío</p>
      )}
    </form>
  );
}

export default FormularioReserva;
```

### Uso del Componente
```jsx
import { useNavigate } from 'react-router-dom';

function PaginaCheckout() {
  const navigate = useNavigate();
  const [carrito, setCarrito] = useState(obtenerCarrito());

  const handleExito = (data) => {
    // Guardar número de reserva
    localStorage.setItem('ultimaReserva', data.reservation_number);

    // Limpiar carrito
    localStorage.removeItem('carrito');
    setCarrito([]);

    // Mostrar mensaje
    alert(`¡Reserva #${data.reservation_number} creada exitosamente!`);

    // Redirigir a página de éxito
    navigate(`/reserva-exitosa?numero=${data.reservation_number}`);
  };

  const handleError = (error) => {
    alert(`Error: ${error}`);
  };

  return (
    <div className="checkout-page">
      <h1>Finalizar Reserva</h1>

      <div className="contenedor-checkout">
        <div className="columna-formulario">
          <FormularioReserva
            productosCarrito={carrito}
            onExito={handleExito}
            onError={handleError}
          />
        </div>

        <div className="columna-resumen">
          <ResumenCarrito productos={carrito} />
        </div>
      </div>
    </div>
  );
}
```

---

## 🎨 HTML del Formulario (Sin Framework)

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear Reserva</title>
  <style>
    .formulario-reserva {
      max-width: 600px;
      margin: 0 auto;
      padding: 20px;
    }
    .campo {
      margin-bottom: 15px;
    }
    .campo label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
    }
    .campo input,
    .campo textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .btn-reservar {
      width: 100%;
      padding: 15px;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
    }
    .btn-reservar:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
    .error {
      color: red;
      margin-top: 10px;
    }
    .exito {
      color: green;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="formulario-reserva">
    <h1>Crear Reserva</h1>

    <form id="formReserva">
      <h2>Datos de Contacto</h2>

      <div class="campo">
        <label for="nombre">Nombre Completo *</label>
        <input type="text" id="nombre" name="nombre" required minlength="3">
      </div>

      <div class="campo">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="campo">
        <label for="telefono">Teléfono *</label>
        <input type="tel" id="telefono" name="telefono" required minlength="8"
               placeholder="+54 9 11 1234-5678">
      </div>

      <h2>Dirección de Envío (Opcional)</h2>

      <div class="campo">
        <label for="direccion">Dirección</label>
        <input type="text" id="direccion" name="direccion">
      </div>

      <div class="campo">
        <label for="ciudad">Ciudad</label>
        <input type="text" id="ciudad" name="ciudad">
      </div>

      <div class="campo">
        <label for="provincia">Provincia</label>
        <input type="text" id="provincia" name="provincia">
      </div>

      <div class="campo">
        <label for="codigoPostal">Código Postal</label>
        <input type="text" id="codigoPostal" name="codigoPostal">
      </div>

      <div class="campo">
        <label for="comentarios">Comentarios Adicionales</label>
        <textarea id="comentarios" name="comentarios" rows="4"
                  placeholder="Horarios preferidos, instrucciones especiales, etc."></textarea>
      </div>

      <button type="submit" class="btn-reservar" id="btnReservar">
        Crear Reserva
      </button>

      <div id="mensaje"></div>
    </form>
  </div>

  <script>
    // Obtener carrito de localStorage
    const carrito = JSON.parse(localStorage.getItem('carrito') || '[]');

    // Manejar envío del formulario
    document.getElementById('formReserva').addEventListener('submit', async (e) => {
      e.preventDefault();

      const btnReservar = document.getElementById('btnReservar');
      const mensaje = document.getElementById('mensaje');

      // Deshabilitar botón
      btnReservar.disabled = true;
      btnReservar.textContent = 'Procesando...';
      mensaje.textContent = '';

      try {
        // Preparar datos
        const formData = new FormData(e.target);
        const reservationData = {
          customer_name: formData.get('nombre'),
          customer_email: formData.get('email'),
          customer_phone: formData.get('telefono'),
          shipping_address: formData.get('direccion'),
          shipping_city: formData.get('ciudad'),
          shipping_state: formData.get('provincia'),
          shipping_zip_code: formData.get('codigoPostal'),
          notes: formData.get('comentarios'),
          items: carrito.map(item => ({
            product_id: item.id,
            quantity: item.cantidad,
            price: item.precio
          }))
        };

        // Hacer request
        const response = await fetch('https://tu-api.com/api/reservations', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(reservationData)
        });

        const data = await response.json();

        if (response.ok) {
          // ÉXITO
          mensaje.className = 'exito';
          mensaje.textContent = `¡Reserva #${data.reservation_number} creada exitosamente!`;

          // Limpiar carrito
          localStorage.removeItem('carrito');

          // Redirigir después de 2 segundos
          setTimeout(() => {
            window.location.href = `/reserva-exitosa.html?numero=${data.reservation_number}`;
          }, 2000);

        } else {
          // ERROR
          mensaje.className = 'error';
          mensaje.textContent = `Error: ${data.error}`;
          btnReservar.disabled = false;
          btnReservar.textContent = 'Crear Reserva';
        }

      } catch (error) {
        mensaje.className = 'error';
        mensaje.textContent = 'Error de conexión. Por favor, intenta nuevamente.';
        btnReservar.disabled = false;
        btnReservar.textContent = 'Crear Reserva';
      }
    });

    // Validar que hay productos en el carrito
    if (carrito.length === 0) {
      document.getElementById('btnReservar').disabled = true;
      document.getElementById('mensaje').className = 'error';
      document.getElementById('mensaje').textContent = 'El carrito está vacío';
    }
  </script>
</body>
</html>
```

---

## 📧 Qué Pasa Después de Crear la Reserva

### 1. El Cliente Recibe Email
```
Asunto: Reserva Recibida - RES20251106A3F2

¡Gracias por tu reserva!

Hola Juan Pérez,

Hemos recibido tu reserva #RES20251106A3F2 por un total de $38,500.00.

Detalles de tu reserva:
[Tabla con productos, cantidades y precios]

Próximos pasos:
Nuestro equipo revisará tu reserva y se comunicará contigo en las
próximas 24-48 horas para confirmar disponibilidad y coordinar el
pago y entrega.
```

### 2. Los Admins Reciben Email de Notificación
Se envía un email de notificación a **3 direcciones**:
- info@decohomesinrival.com.ar
- federiconjg@gmail.com
- Franconico25@gmail.com

El email incluye todos los detalles de la reserva.

### 3. El Admin Revisa y Confirma
El admin revisará la reserva desde el panel de administración y:
- **Si CONFIRMA:** El cliente recibe un email con instrucciones de pago/entrega
- **Si RECHAZA:** El cliente no recibe email automático (el admin debe contactar manualmente)

---

## ✅ Checklist de Implementación Frontend

- [ ] Crear formulario con campos obligatorios (nombre, email, teléfono)
- [ ] Agregar campos opcionales (dirección, ciudad, comentarios)
- [ ] Validar datos del formulario antes de enviar
- [ ] Obtener productos del carrito
- [ ] Hacer POST request a /api/reservations
- [ ] Manejar respuesta exitosa (mostrar número de reserva)
- [ ] Manejar errores (mostrar mensajes claros)
- [ ] Limpiar carrito después de crear reserva
- [ ] Crear página de éxito con número de reserva
- [ ] Agregar loading state durante el proceso
- [ ] Deshabilitar botón mientras procesa

---

## 🚨 Errores Comunes

### "El carrito está vacío"
- **Causa:** Array `items` está vacío o no se envió
- **Solución:** Verificar que hay productos en el carrito antes de mostrar el formulario

### "Email inválido"
- **Causa:** El formato del email no es válido
- **Solución:** Usar validación HTML5 con `type="email"` o regex

### "Producto no encontrado"
- **Causa:** El `product_id` no existe en la base de datos
- **Solución:** Verificar que los IDs de productos sean correctos

### Error de CORS
- **Causa:** El API no permite requests desde tu dominio
- **Solución:** Configurar CORS en el backend

---

## 📱 Página de Éxito

### HTML Ejemplo
```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reserva Exitosa</title>
  <style>
    .exito-container {
      max-width: 600px;
      margin: 50px auto;
      padding: 30px;
      text-align: center;
      border: 2px solid #4CAF50;
      border-radius: 8px;
    }
    .icono-exito {
      font-size: 64px;
      color: #4CAF50;
    }
    .numero-reserva {
      font-size: 32px;
      font-weight: bold;
      color: #333;
      margin: 20px 0;
    }
  </style>
</head>
<body>
  <div class="exito-container">
    <div class="icono-exito">✓</div>
    <h1>¡Reserva Creada Exitosamente!</h1>
    <div class="numero-reserva" id="numeroReserva"></div>
    <p>Te hemos enviado un email de confirmación.</p>
    <p><strong>Nos comunicaremos contigo en las próximas 24-48 horas</strong> para confirmar disponibilidad y coordinar el pago y entrega.</p>
    <a href="/" class="btn">Volver al Inicio</a>
  </div>

  <script>
    // Obtener número de reserva de URL
    const params = new URLSearchParams(window.location.search);
    const numero = params.get('numero');
    document.getElementById('numeroReserva').textContent = `#${numero}`;
  </script>
</body>
</html>
```

---

## 🎯 Resumen para el Cliente

1. **Llenar formulario** con datos de contacto
2. **Revisar carrito** con los productos
3. **Crear reserva** (click en botón)
4. **Recibir número de reserva** (ej: RES20251106A3F2)
5. **Recibir email** con confirmación
6. **Esperar contacto** del equipo en 24-48 horas

**NO SE PAGA NADA AÚN** - El pago se coordina después de que el admin confirme la disponibilidad.

---

**¿Necesitas algo más específico?** Esta documentación cubre todo lo necesario para implementar el formulario de reservas en el frontend.
