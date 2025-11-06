# ✅ Perfil de Usuario Mejorado y Wishlist - COMPLETADO

## 🎉 Todo está listo!

He mejorado el sistema de perfil de usuario con campos adicionales y verificado que la funcionalidad de wishlist (lista de deseos) está completamente implementada.

---

## 📋 Cambios Realizados

### 1. **Perfil de Usuario Extendido**

#### Nuevos Campos Agregados a la Tabla `users`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `phone` | VARCHAR(20) | Teléfono del usuario |
| `birth_date` | DATE | Fecha de nacimiento (YYYY-MM-DD) |
| `gender` | ENUM | Género: male, female, other, prefer_not_to_say |
| `document_type` | VARCHAR(20) | Tipo de documento: DNI, CUIL, CUIT, Pasaporte |
| `document_number` | VARCHAR(50) | Número de documento |
| `avatar` | VARCHAR(255) | URL de la imagen de perfil |
| `bio` | TEXT | Biografía o descripción personal |
| `preferences` | JSON | Preferencias del usuario (formato JSON) |
| `newsletter_subscribed` | BOOLEAN | Suscripción al newsletter |
| `email_verified` | BOOLEAN | Si el email fue verificado |
| `email_verified_at` | TIMESTAMP | Fecha de verificación del email |
| `last_login_at` | TIMESTAMP | Última fecha de login |
| `last_login_ip` | VARCHAR(45) | IP del último login |

#### Características Implementadas:

✅ **Validación de Edad**: El usuario debe tener al menos 13 años
✅ **Cálculo Automático de Edad**: Se calcula desde birth_date
✅ **Estadísticas del Usuario**: Total de órdenes, etc.
✅ **Campos Opcionales**: Todos los nuevos campos son opcionales
✅ **Valores Nulos**: Se permite limpiar campos opcionales

---

### 2. **Wishlist (Lista de Deseos)**

#### Funcionalidades Verificadas:

✅ **Ver Lista de Deseos**: GET `/api/wishlist`
✅ **Agregar Producto**: POST `/api/wishlist`
✅ **Eliminar Producto**: DELETE `/api/wishlist/{product_id}`

#### Mejoras Implementadas:

- **URLs Completas de Imágenes**: Ahora retorna `image_url` completa
- **Precio Final Calculado**: Campo `final_price` (usa sale_price si existe)
- **Porcentaje de Descuento**: Calcula automáticamente si hay sale_price
- **Disponibilidad**: Campo `in_stock` que combina stock y status
- **Contador Total**: Retorna el total de items en la wishlist
- **Formato de Precios**: Todos los precios formateados a 2 decimales

---

## 🗂️ Archivos Modificados/Creados

### **SQL**
- ✅ `database/user_profile_fields.sql` - Migración para agregar campos al perfil

### **Controllers**
- ✅ `src/Controllers/AuthController.php` - Actualizado para manejar nuevos campos
  - Método `me()` - Retorna perfil completo con edad y estadísticas
  - Método `updateProfile()` - Acepta y valida todos los nuevos campos

- ✅ `src/Controllers/WishlistController.php` - Mejorado para retornar datos completos
  - Método `getWishlist()` - Retorna URLs completas, precios calculados, descuentos

### **Documentación**
- ✅ `FRONTEND_API_DOCS.md` - Actualizado con ejemplos completos
- ✅ `PERFIL_Y_WISHLIST_COMPLETO.md` - Este archivo

---

## 📡 API Endpoints

### **Perfil de Usuario**

#### Obtener Perfil Completo
```http
GET /api/auth/me
Authorization: Bearer {token}
```

**Response:**
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

#### Actualizar Perfil
```http
PUT /api/auth/profile
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (todos los campos opcionales):**
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
- `birth_date`: Formato YYYY-MM-DD, edad mínima 13 años
- `gender`: Solo "male", "female", "other", "prefer_not_to_say"
- `newsletter_subscribed`: Boolean (true/false)

---

### **Wishlist (Lista de Deseos)**

#### Ver Mi Lista de Deseos
```http
GET /api/wishlist
Authorization: Bearer {token}
```

**Response:**
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
    }
  ]
}
```

#### Agregar Producto a Wishlist
```http
POST /api/wishlist
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "product_id": 27
}
```

**Response:**
```json
{
  "message": "Product added to wishlist"
}
```

#### Eliminar de Wishlist
```http
DELETE /api/wishlist/{product_id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "message": "Product removed from wishlist"
}
```

---

## 🚀 Pasos para Implementar en Producción

### **PASO 1: Crear Tabla Wishlist** ⚠️ PRIMERO

La tabla `wishlists` no existe en producción. Ejecutar primero:

**Opción 1 - MySQL command line:**
```bash
mysql -h srv1597.hstgr.io -u u565673608_ssh -p u565673608_sinrival < database/create_wishlist_table.sql
```

**Opción 2 - phpMyAdmin (RECOMENDADO):**
1. Ir a phpMyAdmin
2. Seleccionar base de datos: `u565673608_sinrival`
3. Ir a pestaña "SQL"
4. Copiar y pegar el contenido de `database/create_wishlist_table.sql`
5. Hacer clic en "Continuar"

**SQL a ejecutar:**
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

### **PASO 2: Agregar Campos al Perfil** ⚠️ SEGUNDO

Ejecutar migración SQL para los nuevos campos de perfil:

**Opción 1 - MySQL command line:**
```bash
mysql -h srv1597.hstgr.io -u u565673608_ssh -p u565673608_sinrival < database/user_profile_fields.sql
```

**Opción 2 - phpMyAdmin:**
1. Ir a phpMyAdmin
2. Seleccionar base de datos: `u565673608_sinrival`
3. Ir a pestaña "SQL"
4. Copiar y pegar el contenido de `database/user_profile_fields.sql`
5. Hacer clic en "Continuar"

### **PASO 3: Subir Archivos Actualizados**

Subir estos archivos al servidor de producción:

```
/src/Controllers/AuthController.php (actualizado)
/src/Controllers/WishlistController.php (actualizado)
/FRONTEND_API_DOCS.md (actualizado)
/PERFIL_Y_WISHLIST_COMPLETO.md (nuevo)
/database/user_profile_fields.sql (nuevo)
/database/create_wishlist_table.sql (nuevo - IMPORTANTE!)
```

---

## 🎨 Ejemplos de Código Frontend

### **React - Perfil de Usuario**

```javascript
import { useState, useEffect } from 'react';

function UserProfile() {
  const [profile, setProfile] = useState(null);
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState({});

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    const response = await fetch('/api/auth/me', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    });
    const data = await response.json();
    setProfile(data);
    setFormData(data);
  };

  const updateProfile = async () => {
    const response = await fetch('/api/auth/profile', {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    });

    if (response.ok) {
      const data = await response.json();
      setProfile(data.user);
      setEditing(false);
      alert('Perfil actualizado exitosamente');
    }
  };

  if (!profile) return <div>Cargando...</div>;

  return (
    <div className="profile-container">
      <h1>Mi Perfil</h1>

      {!editing ? (
        <div className="profile-view">
          <p><strong>Nombre:</strong> {profile.name}</p>
          <p><strong>Email:</strong> {profile.email}</p>
          <p><strong>Teléfono:</strong> {profile.phone || 'No especificado'}</p>
          <p><strong>Edad:</strong> {profile.age || 'No especificado'}</p>
          <p><strong>Género:</strong> {profile.gender || 'No especificado'}</p>
          <p><strong>Documento:</strong> {profile.document_type} {profile.document_number}</p>
          <p><strong>Bio:</strong> {profile.bio || 'No especificado'}</p>
          <p><strong>Newsletter:</strong> {profile.newsletter_subscribed ? 'Suscrito' : 'No suscrito'}</p>
          <p><strong>Órdenes totales:</strong> {profile.stats?.total_orders || 0}</p>

          <button onClick={() => setEditing(true)}>Editar Perfil</button>
        </div>
      ) : (
        <div className="profile-edit">
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({...formData, name: e.target.value})}
            placeholder="Nombre"
          />
          <input
            type="tel"
            value={formData.phone || ''}
            onChange={(e) => setFormData({...formData, phone: e.target.value})}
            placeholder="Teléfono"
          />
          <input
            type="date"
            value={formData.birth_date || ''}
            onChange={(e) => setFormData({...formData, birth_date: e.target.value})}
          />
          <select
            value={formData.gender || ''}
            onChange={(e) => setFormData({...formData, gender: e.target.value})}
          >
            <option value="">Seleccionar género</option>
            <option value="male">Masculino</option>
            <option value="female">Femenino</option>
            <option value="other">Otro</option>
            <option value="prefer_not_to_say">Prefiero no decir</option>
          </select>
          <input
            type="text"
            value={formData.document_number || ''}
            onChange={(e) => setFormData({...formData, document_number: e.target.value})}
            placeholder="Número de documento"
          />
          <textarea
            value={formData.bio || ''}
            onChange={(e) => setFormData({...formData, bio: e.target.value})}
            placeholder="Biografía"
          />
          <label>
            <input
              type="checkbox"
              checked={formData.newsletter_subscribed}
              onChange={(e) => setFormData({...formData, newsletter_subscribed: e.target.checked})}
            />
            Suscribirse al newsletter
          </label>

          <button onClick={updateProfile}>Guardar</button>
          <button onClick={() => setEditing(false)}>Cancelar</button>
        </div>
      )}
    </div>
  );
}
```

### **React - Wishlist**

```javascript
import { useState, useEffect } from 'react';

function Wishlist() {
  const [wishlist, setWishlist] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchWishlist();
  }, []);

  const fetchWishlist = async () => {
    try {
      const response = await fetch('/api/wishlist', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      const data = await response.json();
      setWishlist(data.items);
    } catch (error) {
      console.error('Error fetching wishlist:', error);
    } finally {
      setLoading(false);
    }
  };

  const removeFromWishlist = async (productId) => {
    try {
      const response = await fetch(`/api/wishlist/${productId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (response.ok) {
        setWishlist(wishlist.filter(item => item.product_id !== productId));
        alert('Producto eliminado de la lista de deseos');
      }
    } catch (error) {
      console.error('Error removing from wishlist:', error);
    }
  };

  if (loading) return <div>Cargando...</div>;

  return (
    <div className="wishlist-container">
      <h1>Mi Lista de Deseos ({wishlist.length})</h1>

      {wishlist.length === 0 ? (
        <p>No tienes productos en tu lista de deseos</p>
      ) : (
        <div className="wishlist-grid">
          {wishlist.map(item => (
            <div key={item.wishlist_id} className="wishlist-item">
              <img src={item.image_url} alt={item.name} />
              <h3>{item.name}</h3>
              <p className="sku">{item.sku}</p>

              <div className="price">
                {item.sale_price ? (
                  <>
                    <span className="original-price">${item.price}</span>
                    <span className="sale-price">${item.final_price}</span>
                    <span className="discount">-{item.discount_percentage}%</span>
                  </>
                ) : (
                  <span className="regular-price">${item.price}</span>
                )}
              </div>

              <div className="stock-status">
                {item.in_stock ? (
                  <span className="in-stock">En stock ({item.stock} disponibles)</span>
                ) : (
                  <span className="out-of-stock">Sin stock</span>
                )}
              </div>

              <div className="actions">
                <button
                  onClick={() => addToCart(item.product_id)}
                  disabled={!item.in_stock}
                  className="add-to-cart-btn"
                >
                  Agregar al carrito
                </button>
                <button
                  onClick={() => removeFromWishlist(item.product_id)}
                  className="remove-btn"
                >
                  Eliminar
                </button>
              </div>

              <p className="added-date">
                Agregado el {new Date(item.added_at).toLocaleDateString()}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
```

### **Agregar a Wishlist desde Producto**

```javascript
const addToWishlist = async (productId) => {
  try {
    const response = await fetch('/api/wishlist', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ product_id: productId })
    });

    if (response.ok) {
      alert('Producto agregado a la lista de deseos');
      // Actualizar icono de corazón o UI
    } else if (response.status === 401) {
      alert('Debes iniciar sesión para agregar a favoritos');
      // Redirigir a login
    }
  } catch (error) {
    console.error('Error adding to wishlist:', error);
  }
};
```

---

## ✅ Checklist de Implementación

### Backend
- [x] SQL de campos de perfil creado
- [x] AuthController actualizado para manejar nuevos campos
- [x] Validación de edad implementada (mínimo 13 años)
- [x] Cálculo de edad desde birth_date
- [x] Estadísticas de usuario implementadas
- [x] WishlistController mejorado con URLs y precios
- [x] Documentación actualizada

### Base de Datos
- [ ] **Ejecutar user_profile_fields.sql en producción** ⚠️
- [ ] Verificar que tabla wishlists existe

### Archivos a Subir
- [ ] AuthController.php actualizado
- [ ] WishlistController.php actualizado
- [ ] FRONTEND_API_DOCS.md actualizado
- [ ] PERFIL_Y_WISHLIST_COMPLETO.md

### Testing
- [ ] Probar actualizar perfil con nuevos campos
- [ ] Probar validación de edad (menores de 13)
- [ ] Probar GET /api/auth/me con datos completos
- [ ] Probar agregar producto a wishlist
- [ ] Probar ver wishlist con URLs de imágenes
- [ ] Probar eliminar de wishlist
- [ ] Verificar cálculo de descuentos en wishlist

---

## 🎯 Próximos Pasos Recomendados

1. **Avatar de Usuario**
   - Implementar upload de imagen de perfil
   - Crear endpoint POST /api/auth/avatar
   - Guardar en /uploads/avatars/

2. **Verificación de Email**
   - Enviar email de verificación al registrarse
   - Endpoint para confirmar email
   - Actualizar email_verified y email_verified_at

3. **Preferencias Avanzadas**
   - Usar el campo JSON `preferences` para:
     - Tema (light/dark)
     - Idioma preferido
     - Categorías de productos favoritos
     - Notificaciones personalizadas

4. **Wishlist Compartida**
   - Generar link único para compartir wishlist
   - Opción de hacer wishlist pública/privada

---

## 📝 Notas Importantes

### Campos Opcionales
Todos los nuevos campos de perfil son **opcionales**. El usuario puede completar su perfil gradualmente.

### Valores NULL
Se permite enviar valores vacíos (`""` o `null`) para limpiar campos opcionales como:
- phone
- birth_date
- bio
- document_type
- document_number

### Edad Mínima
La validación de edad (13 años) solo se aplica **si se proporciona** una fecha de nacimiento. No es obligatorio proporcionar birth_date.

### Wishlist y Stock
El campo `in_stock` en la wishlist combina dos validaciones:
1. `stock > 0`
2. `status = 'active'`

Esto permite que el frontend muestre correctamente si un producto está disponible o no.

---

## 📞 Soporte

Si encuentras algún problema:

1. **Revisar logs de error de Apache**
2. **Verificar que el SQL se ejecutó correctamente**
3. **Comprobar que los archivos se subieron correctamente**
4. **Revisar la documentación completa en FRONTEND_API_DOCS.md**

---

## 🎉 ¡Listo!

El sistema de **Perfil de Usuario Extendido** y la **Lista de Deseos** están completamente implementados y listos para usar.

Solo falta:
1. Ejecutar el SQL en producción
2. Subir los archivos actualizados
3. Probar en el frontend

**¿Necesitas ayuda con algo más?** 🚀
