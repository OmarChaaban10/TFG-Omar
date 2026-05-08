# TaskHive - Gestor de Tareas Avanzado

**Autor:** Omar Mounder Chaaban Yaber  
**Proyecto:** Trabajo de Fin de Grado (TFG) - 2º Año de Desarrollo de Aplicaciones Web (DAW)

**TaskHive** es una aplicación web completa y moderna para la gestión de proyectos y tareas, diseñada con un enfoque profesional, interactivo y centrado en la experiencia de usuario. Cuenta con autenticación segura, modos claro/oscuro, paneles de administración, sistema de reportes en tiempo real y asignación dinámica de roles.

---

## 🚀 Stack Tecnológico

**Frontend:**
- **Framework:** Angular 21
- **Estilos:** Tailwind CSS 4
- **Lenguaje:** TypeScript 5

**Backend:**
- **Framework:** Symfony 7
- **Lenguaje:** PHP 8.3
- **Seguridad:** JWT (JSON Web Tokens) para autenticación

**Base de Datos:**
- PostgreSQL 17

**Containerización:**
- Docker & Docker Compose

---

## ✨ Características Principales

- ✅ **Autenticación Segura:** Sistema de login y registro protegido con JWT.
- ✅ **Gestión de Usuarios y Roles:** Control de permisos de acceso (Propietario, Admin, Gestor, Miembro).
- ✅ **Gestor de Proyectos y Tareas:** Organización visual de tareas, asignación a miembros e indicadores de progreso.
- ✅ **Auditoría y Reportes:** Sistema de logs automático que registra las actividades clave en cada proyecto.
- ✅ **Diseño Premium UI/UX:** Interfaz interactiva y completamente adaptativa, con soporte nativo para Tema Claro y Tema Oscuro.
- ✅ **API RESTful:** Backend robusto y estructurado.
- ✅ **Despliegue Rápido:** Listo para levantar en segundos gracias a Docker Compose.

---

## ⚙️ Opciones de Ejecución

Existen dos maneras de levantar TaskHive:
- **`Docker Compose`:** Empaqueta frontend, backend y base de datos en contenedores. Es la forma más rápida de ver la aplicación funcionando sin instalar dependencias de desarrollo.
- **`Entorno Local`:** Instala Node.js, Angular CLI, PHP y Composer para poder ejecutar y depurar cada proyecto directamente desde tu terminal. Útil para desarrollo diario o prácticas donde necesites modificar el código y usar herramientas locales.

---

## 🛠️ Guía rápida (Entorno Local desde Cero)

### 1. Instala las herramientas base

1. **Git**  
   - Descarga desde [git-scm.com](https://git-scm.com/downloads) y acepta la instalación por defecto.
   - En Linux (Ubuntu/Debian):  
     ```bash
     sudo apt update
     sudo apt install curl git build-essential -y
     ```

2. **Node.js + npm (Angular necesita Node 20.19 o 22.x)**  
   - Se recomienda instalar [nvm](https://github.com/nvm-sh/nvm#installing-and-updating) y ejecutar:  
     ```bash
     nvm install 22
     nvm use 22
     ```  
   - Comprueba que todo está correcto: `node -v` debería mostrar `v22.x`.

3. **Angular CLI**  
   ```bash
   npm install -g @angular/cli@20
   ng version
   ```

4. **PHP 8.3 + Composer** (Para el Backend)  
   - En Linux (Debian/Ubuntu):  
     ```bash
     sudo apt update
     sudo apt install php8.3-cli php8.3-common php8.3-xml php8.3-intl php8.3-mbstring php8.3-zip php8.3-pgsql unzip curl -y
     curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
     composer --version
     ```

5. **Symfony CLI**  
   Permite lanzar el servidor de desarrollo y comandos auxiliares con facilidad.  
   ```bash
   curl -sS https://get.symfony.com/cli/installer | bash
   mv ~/.symfony*/bin/symfony /usr/local/bin/symfony  # añade sudo si no eres root
   symfony version
   ```

6. **Docker Desktop** (Para levantar la base de datos fácilmente).  
   - Instalación: [docs.docker.com/get-docker](https://docs.docker.com/desktop/setup/install/linux/debian/#install-docker-desktop)

---

### 2. Clona el repositorio

```bash
git clone <URL_REPOSITORIO>
cd tfg-omar
```

---

### 3. Instala las dependencias y prepara PostgreSQL

Levanta únicamente el contenedor de la base de datos (PostgreSQL):
```bash
docker compose up db -d
```

Prepara el **Backend**:
```bash
cd symfony-backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
```

> **Datos Demo (Opcional):** Si deseas cargar datos de prueba para ver el tablero funcionando rápidamente:
> ```bash
> php bin/console app:seed:demo
> ```

Prepara el **Frontend**:
```bash
cd ../angular-frontend
npm install
```

---

### 4. Arranca los servidores locales

**Levantar Backend:**
En una terminal nueva, sitúate en `symfony-backend` y ejecuta:
```bash
symfony server:start --no-tls --port=8000
```
*(Accede a http://localhost:8000 para verificar que la API responde).*

**Levantar Frontend:**
En otra terminal, sitúate en `angular-frontend` y ejecuta:
```bash
ng serve
```
Abre [http://localhost:4200](http://localhost:4200) y verás la interfaz de TaskHive lista para usar.

---

## 🐳 Ejecución Completa con Docker Compose (Vía Rápida)

Si prefieres no instalar PHP ni Node.js en tu máquina, puedes levantar todo el stack directamente con Docker.

### Pasos
1. Desde la raíz del proyecto (donde se encuentra `docker-compose.yml`):
   ```bash
   docker compose up -d
   ```
2. Verifica que los tres contenedores (**symfony_backend**, **angular_frontend** y **symfony_postgres**) están corriendo:
   ```bash
   docker ps
   ```
3. ¡Listo! Accede a:
   - **Frontend UI:** [http://localhost:4200](http://localhost:4200)
   - **Backend API:** [http://localhost:8000](http://localhost:8000)

### Comandos Útiles de Docker
- Parar todo el stack: `docker compose down`
- Parar y resetear base de datos: `docker compose down -v`
- Ver logs en vivo: `docker compose logs -f`

---

## ❓ Preguntas Frecuentes

- **¿Por qué insistimos en la versión exacta de Node y PHP?**  
  Angular requiere Node >= 20.19 o 22.x. Symfony aprovecha funcionalidades exclusivas de PHP 8.3, por lo que versiones anteriores fallarán.
  
- **Docker me dice que los puertos están en uso**  
  Cierra todo primero usando `docker compose down`. Si los puertos 8000/4200/5432 siguen ocupados por otros procesos, localízalos en tu sistema (`lsof -i :8000`) y detenlos manualmente.
