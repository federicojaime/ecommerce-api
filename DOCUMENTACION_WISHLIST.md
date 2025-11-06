# 💝 Documentación Completa - Wishlist (Me Gusta / Lista de Deseos)

## 📋 Índice
1. [Descripción General](#descripción-general)
2. [Base de Datos](#base-de-datos)
3. [API Endpoints](#api-endpoints)
4. [Ejemplos de Uso](#ejemplos-de-uso)
5. [Instalación](#instalación)
6. [Errores Comunes](#errores-comunes)

---

## Descripción General

La funcionalidad de **Wishlist** permite a los usuarios guardar productos favoritos para comprarlos más tarde. Cada usuario puede:

- ✅ Agregar productos a su lista de deseos
- ✅ Ver todos sus productos favoritos
- ✅ Eliminar productos de la lista
- ✅ Ver precios actualizados y descuentos
- ✅ Ver disponibilidad de stock en tiempo real

**Características:**
- Un producto solo puede estar una vez en la wishlist de cada usuario
- Se muestra el precio final (con descuento si existe)
- Calcula automáticamente el porcentaje de descuento
- Incluye URLs completas de las imágenes
- Indica si el producto está en stock

---

## Base de Datos

### Tabla: `wishlists`

```sql
CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos:**
- `id` - ID único del registro en la wishlist
- `user_id` - ID del usuario (FK a tabla users)
- `product_id` - ID del producto (FK a tabla products)
- `created_at` - Fecha cuando se agregó a la lista

**Índices:**
- `unique_user_product` - Previene duplicados (un usuario solo puede agregar un producto una vez)
- `idx_user_id` - Optimiza consultas por usuario

**Relaciones:**
- Si se elimina un usuario, se eliminan sus items de wishlist (CASCADE)
- Si se elimina un producto, se elimina de todas las wishlists (CASCADE)

---

## API Endpoints

### 📌 Base URL
```
Producción: https://decohomesinrival.com.ar/ecommerce-api/public/api
Desarrollo: http://localhost/ecommerce-api/public/api
```

### 🔒 Autenticación
Todos los endpoints requieren autenticación con token JWT:
```
Authorization: Bearer {token}
```

---

## 1️⃣ Ver Mi Lista de Deseos

### Request
```http
GET /api/wishlist
```

### Headers
```json
{
  "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Response 200 OK
```json
{
  "total": 3,
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
      "name": "Espejo Decorativo Redondo",
      "slug": "espejo-decorativo-redondo",
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
    },
    {
      "wishlist_id": 8,
      "product_id": 30,
      "name": "Lámpara de Pie Industrial",
      "slug": "lampara-pie-industrial",
      "sku": "LAMP-003",
      "price": "8500.00",
      "sale_price": "6800.00",
      "final_price": "6800.00",
      "discount_percentage": 20,
      "stock": 0,
      "status": "active",
      "in_stock": false,
      "image_path": "products/lamp003.jpg",
      "image_url": "https://decohomesinrival.com.ar/ecommerce-api/public/uploads/products/lamp003.jpg",
      "added_at": "2025-10-22 09:15:00"
    }
  ]
}
```

### Descripción de Campos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total` | Integer | Total de productos en la wishlist |
| `wishlist_id` | Integer | ID del registro en la wishlist (usar para eliminar) |
| `product_id` | Integer | ID del producto |
| `name` | String | Nombre del producto |
| `slug` | String | URL amigable del producto |
| `sku` | String | Código SKU del producto |
| `price` | String | Precio original del producto |
| `sale_price` | String/null | Precio en oferta (null si no hay oferta) |
| `final_price` | String | Precio final a mostrar (sale_price o price) |
| `discount_percentage` | Integer | Porcentaje de descuento (0 si no hay) |
| `stock` | Integer | Cantidad disponible en inventario |
| `status` | String | Estado del producto (active/inactive) |
| `in_stock` | Boolean | true si stock > 0 y status = active |
| `image_path` | String | Ruta relativa de la imagen |
| `image_url` | String | URL completa de la imagen |
| `added_at` | Timestamp | Fecha cuando se agregó a la wishlist |

### Errores

**401 Unauthorized**
```json
{
  "error": "Unauthorized"
}
```

---

## 2️⃣ Agregar Producto a Lista de Deseos

### Request
```http
POST /api/wishlist
```

### Headers
```json
{
  "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGc...",
  "Content-Type": "application/json"
}
```

### Body
```json
{
  "product_id": 27
}
```

### Response 201 Created
```json
{
  "message": "Product added to wishlist"
}
```

### Errores

**400 Bad Request** - Falta product_id
```json
{
  "error": "Product ID is required"
}
```

**404 Not Found** - Producto no existe
```json
{
  "error": "Product not found"
}
```

**401 Unauthorized** - Token inválido o expirado
```json
{
  "error": "Unauthorized"
}
```

**Nota:** Si el producto ya está en la wishlist, la operación es idempotente (no genera error, simplemente no hace nada y devuelve 201).

---

## 3️⃣ Eliminar de Lista de Deseos

### Request
```http
DELETE /api/wishlist/{product_id}
```

**Ejemplo:**
```http
DELETE /api/wishlist/27
```

### Headers
```json
{
  "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Response 200 OK
```json
{
  "message": "Product removed from wishlist"
}
```

### Errores

**404 Not Found** - El producto no está en la wishlist
```json
{
  "error": "Item not found in wishlist"
}
```

**401 Unauthorized**
```json
{
  "error": "Unauthorized"
}
```

---

## Ejemplos de Uso

### 🔷 JavaScript Vanilla

#### Ver Wishlist
```javascript
async function getWishlist() {
  const token = localStorage.getItem('token');

  try {
    const response = await fetch('https://decohomesinrival.com.ar/ecommerce-api/public/api/wishlist', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (!response.ok) {
      throw new Error('Error al obtener wishlist');
    }

    const data = await response.json();
    console.log(`Tienes ${data.total} productos favoritos`);

    // Mostrar productos
    data.items.forEach(item => {
      console.log(`${item.name} - $${item.final_price}`);
      if (item.discount_percentage > 0) {
        console.log(`¡${item.discount_percentage}% de descuento!`);
      }
    });

    return data;
  } catch (error) {
    console.error('Error:', error);
  }
}
```

#### Agregar a Wishlist
```javascript
async function addToWishlist(productId) {
  const token = localStorage.getItem('token');

  if (!token) {
    alert('Debes iniciar sesión para agregar favoritos');
    window.location.href = '/login';
    return;
  }

  try {
    const response = await fetch('https://decohomesinrival.com.ar/ecommerce-api/public/api/wishlist', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ product_id: productId })
    });

    if (response.ok) {
      alert('¡Producto agregado a favoritos!');
      // Cambiar icono de corazón
      document.querySelector(`#heart-${productId}`).classList.add('active');
    } else {
      const error = await response.json();
      alert(error.error);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error al agregar a favoritos');
  }
}
```

#### Eliminar de Wishlist
```javascript
async function removeFromWishlist(productId) {
  const token = localStorage.getItem('token');

  if (!confirm('¿Quieres eliminar este producto de tus favoritos?')) {
    return;
  }

  try {
    const response = await fetch(`https://decohomesinrival.com.ar/ecommerce-api/public/api/wishlist/${productId}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      alert('Producto eliminado de favoritos');
      // Eliminar de la UI
      document.querySelector(`#wishlist-item-${productId}`).remove();
    } else {
      const error = await response.json();
      alert(error.error);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}
```

---

### ⚛️ React

```javascript
import { useState, useEffect } from 'react';

function Wishlist() {
  const [wishlist, setWishlist] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const API_URL = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

  useEffect(() => {
    fetchWishlist();
  }, []);

  const fetchWishlist = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_URL}/wishlist`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (!response.ok) throw new Error('Error al cargar wishlist');

      const data = await response.json();
      setWishlist(data.items);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const removeFromWishlist = async (productId) => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_URL}/wishlist/${productId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        setWishlist(wishlist.filter(item => item.product_id !== productId));
      }
    } catch (err) {
      alert('Error al eliminar producto');
    }
  };

  const addToCart = async (productId) => {
    // Implementar lógica de agregar al carrito
    console.log('Agregar al carrito:', productId);
  };

  if (loading) return <div className="loading">Cargando...</div>;
  if (error) return <div className="error">{error}</div>;

  return (
    <div className="wishlist-container">
      <h1>Mis Favoritos ({wishlist.length})</h1>

      {wishlist.length === 0 ? (
        <div className="empty-wishlist">
          <p>No tienes productos favoritos todavía</p>
          <a href="/productos">Ver productos</a>
        </div>
      ) : (
        <div className="wishlist-grid">
          {wishlist.map(item => (
            <div key={item.wishlist_id} className="wishlist-card">
              {/* Imagen */}
              <div className="product-image">
                <img src={item.image_url} alt={item.name} />
                {item.discount_percentage > 0 && (
                  <span className="discount-badge">
                    -{item.discount_percentage}%
                  </span>
                )}
              </div>

              {/* Info del producto */}
              <div className="product-info">
                <h3>{item.name}</h3>
                <p className="sku">SKU: {item.sku}</p>

                {/* Precios */}
                <div className="prices">
                  {item.sale_price ? (
                    <>
                      <span className="original-price">${item.price}</span>
                      <span className="sale-price">${item.final_price}</span>
                    </>
                  ) : (
                    <span className="regular-price">${item.price}</span>
                  )}
                </div>

                {/* Stock */}
                <div className="stock-info">
                  {item.in_stock ? (
                    <span className="in-stock">
                      ✓ En stock ({item.stock} disponibles)
                    </span>
                  ) : (
                    <span className="out-of-stock">✗ Sin stock</span>
                  )}
                </div>

                {/* Fecha agregado */}
                <p className="added-date">
                  Agregado: {new Date(item.added_at).toLocaleDateString('es-AR')}
                </p>
              </div>

              {/* Acciones */}
              <div className="actions">
                <button
                  onClick={() => addToCart(item.product_id)}
                  disabled={!item.in_stock}
                  className="btn-add-cart"
                >
                  {item.in_stock ? 'Agregar al carrito' : 'Sin stock'}
                </button>
                <button
                  onClick={() => removeFromWishlist(item.product_id)}
                  className="btn-remove"
                >
                  ❤️ Eliminar de favoritos
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default Wishlist;
```

### Componente: Botón "Me Gusta" en Producto

```javascript
import { useState, useEffect } from 'react';

function WishlistButton({ productId }) {
  const [isInWishlist, setIsInWishlist] = useState(false);
  const [loading, setLoading] = useState(false);

  const API_URL = 'https://decohomesinrival.com.ar/ecommerce-api/public/api';

  useEffect(() => {
    checkIfInWishlist();
  }, [productId]);

  const checkIfInWishlist = async () => {
    try {
      const token = localStorage.getItem('token');
      if (!token) return;

      const response = await fetch(`${API_URL}/wishlist`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });

      if (response.ok) {
        const data = await response.json();
        const found = data.items.some(item => item.product_id === productId);
        setIsInWishlist(found);
      }
    } catch (err) {
      console.error('Error checking wishlist:', err);
    }
  };

  const toggleWishlist = async () => {
    const token = localStorage.getItem('token');

    if (!token) {
      alert('Debes iniciar sesión');
      return;
    }

    setLoading(true);

    try {
      if (isInWishlist) {
        // Eliminar
        const response = await fetch(`${API_URL}/wishlist/${productId}`, {
          method: 'DELETE',
          headers: { 'Authorization': `Bearer ${token}` }
        });

        if (response.ok) {
          setIsInWishlist(false);
        }
      } else {
        // Agregar
        const response = await fetch(`${API_URL}/wishlist`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ product_id: productId })
        });

        if (response.ok) {
          setIsInWishlist(true);
        }
      }
    } catch (err) {
      alert('Error al actualizar favoritos');
    } finally {
      setLoading(false);
    }
  };

  return (
    <button
      onClick={toggleWishlist}
      disabled={loading}
      className={`wishlist-btn ${isInWishlist ? 'active' : ''}`}
      title={isInWishlist ? 'Eliminar de favoritos' : 'Agregar a favoritos'}
    >
      {loading ? '...' : (isInWishlist ? '❤️' : '🤍')}
    </button>
  );
}

export default WishlistButton;
```

### CSS Ejemplo

```css
/* Botón de favoritos */
.wishlist-btn {
  background: none;
  border: 2px solid #ddd;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.3s;
}

.wishlist-btn:hover {
  transform: scale(1.1);
  border-color: #ff6b6b;
}

.wishlist-btn.active {
  background: #ff6b6b;
  border-color: #ff6b6b;
  color: white;
}

/* Grid de wishlist */
.wishlist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  padding: 20px;
}

.wishlist-card {
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 15px;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.product-image {
  position: relative;
  width: 100%;
  height: 250px;
  overflow: hidden;
  border-radius: 8px;
  margin-bottom: 15px;
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.discount-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #ff6b6b;
  color: white;
  padding: 5px 10px;
  border-radius: 20px;
  font-weight: bold;
}

.prices {
  margin: 10px 0;
}

.original-price {
  text-decoration: line-through;
  color: #999;
  margin-right: 10px;
}

.sale-price {
  color: #ff6b6b;
  font-weight: bold;
  font-size: 1.2em;
}

.in-stock {
  color: #28a745;
  font-weight: 500;
}

.out-of-stock {
  color: #dc3545;
  font-weight: 500;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 15px;
}

.btn-add-cart {
  background: #007bff;
  color: white;
  border: none;
  padding: 10px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
}

.btn-add-cart:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.btn-remove {
  background: white;
  color: #dc3545;
  border: 1px solid #dc3545;
  padding: 10px;
  border-radius: 5px;
  cursor: pointer;
}
```

---

## Instalación

### PASO 1: Crear la Tabla en la Base de Datos

⚠️ **IMPORTANTE**: Ejecutar este SQL en phpMyAdmin

```sql
CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cómo hacerlo:**
1. Abrir phpMyAdmin
2. Seleccionar base de datos: `u565673608_sinrival`
3. Ir a pestaña "SQL"
4. Copiar y pegar el SQL de arriba
5. Clic en "Continuar"

### PASO 2: Verificar Archivos del Backend

Asegúrate de que estos archivos existen en el servidor:

- ✅ `/src/Controllers/WishlistController.php`
- ✅ `/public/index.php` (con las rutas de wishlist)

### PASO 3: Verificar Rutas

En `public/index.php` deben existir estas rutas:

```php
// Dentro del grupo protegido con AuthMiddleware
$group->get('/wishlist', function (Request $request, Response $response) use ($database) {
    $controller = new WishlistController($database);
    return $controller->getWishlist($request, $response);
});

$group->post('/wishlist', function (Request $request, Response $response) use ($database) {
    $controller = new WishlistController($database);
    return $controller->addItem($request, $response);
});

$group->delete('/wishlist/{product_id}', function (Request $request, Response $response, array $args) use ($database) {
    $controller = new WishlistController($database);
    return $controller->removeItem($request, $response, $args);
});
```

---

## Errores Comunes

### ❌ Error: "Table wishlists doesn't exist"

**Causa:** La tabla no se creó en la base de datos.

**Solución:** Ejecutar el SQL del PASO 1 en phpMyAdmin.

---

### ❌ Error: "Unauthorized" (401)

**Causa:** Token JWT inválido, expirado o no enviado.

**Solución:**
1. Verificar que el token existe: `localStorage.getItem('token')`
2. Verificar que se envía en el header: `Authorization: Bearer {token}`
3. Si expiró, hacer login nuevamente

---

### ❌ Error: "Product not found" (404)

**Causa:** El `product_id` no existe en la tabla `products`.

**Solución:** Verificar que el producto existe antes de agregarlo.

---

### ❌ Error: Producto duplicado

**Causa:** La tabla tiene `UNIQUE KEY unique_user_product` que previene duplicados.

**Solución:** Esto es comportamiento esperado. El endpoint retorna 201 aunque ya exista.

---

## 📊 Casos de Uso

### 1. Usuario guarda productos para comprar después
```javascript
// En la página de producto
<button onclick="addToWishlist(25)">💝 Guardar para después</button>
```

### 2. Usuario compara precios en su wishlist
```javascript
// Ordenar por precio
wishlist.sort((a, b) => parseFloat(a.final_price) - parseFloat(b.final_price));
```

### 3. Notificar cuando producto en wishlist tiene descuento
```javascript
// En el backend, al actualizar precios
// Enviar notificación si final_price < price
```

### 4. Mover de wishlist a carrito
```javascript
async function moveToCart(productId) {
  await addToCart(productId);
  await removeFromWishlist(productId);
}
```

---

## 🔐 Seguridad

- ✅ Todos los endpoints requieren autenticación JWT
- ✅ Los usuarios solo pueden ver/modificar su propia wishlist
- ✅ Validación de que el producto existe antes de agregar
- ✅ Prevención de duplicados con índice único
- ✅ Cascada de eliminación si se borra usuario o producto

---

## 📈 Optimizaciones

### Índices
La tabla tiene índices para optimizar:
- Consultas por usuario (`idx_user_id`)
- Prevención de duplicados (`unique_user_product`)

### Caché (Recomendado para el futuro)
```javascript
// Guardar wishlist en localStorage para no consultar siempre
const cachedWishlist = JSON.parse(localStorage.getItem('wishlist_cache'));
```

---

## ✅ Checklist de Implementación

- [ ] Ejecutar SQL para crear tabla `wishlists`
- [ ] Verificar que WishlistController.php existe
- [ ] Verificar rutas en index.php
- [ ] Probar GET /api/wishlist
- [ ] Probar POST /api/wishlist
- [ ] Probar DELETE /api/wishlist/{id}
- [ ] Implementar botón "Me Gusta" en productos
- [ ] Crear página de wishlist en frontend
- [ ] Agregar estilos CSS
- [ ] Probar funcionalidad completa

---

## 🎯 ¡Listo para usar!

La funcionalidad de Wishlist está completamente implementada y documentada. Solo necesitas:

1. ✅ Ejecutar el SQL en la base de datos
2. ✅ Implementar el frontend con los ejemplos de arriba
3. ✅ Probar la funcionalidad

**¿Tienes dudas o necesitas ayuda con la implementación?** 🚀
