# Onboarding DAW - Gestor de Tareas

**Omar Chaaban**

Una aplicación web completa de gestión de tareas con login y panel de administración. Sistema de autenticación con JWT, roles de usuario y gestión de base de datos relacional.

## Stack Tecnológico

**Frontend:**
- Angular 19
- Tailwind CSS
- TypeScript

**Backend:**
- Symfony 6
- PHP 8
- JWT (autenticación)

**Base de Datos:**
- PostgreSQL

**Containerización:**
- Docker & Docker Compose

## Características

- ✅ Autenticación con JWT
- ✅ Gestión de usuarios y roles
- ✅ Gestor de tareas completo
- ✅ Panel de administración
- ✅ API RESTful
- ✅ Desplegable con Docker Compose

---

## Opciones de ejecución

- `Docker Compose`: empaqueta frontend, backend y base de datos en contenedores. Es la forma más rápida de ver la aplicación funcionando sin instalar dependencias de desarrollo.
- `Entorno local`: instala Node.js, Angular CLI, PHP y Composer para poder ejecutar y depurar cada proyecto desde tu terminal. Útil para desarrollo diario o prácticas donde necesites modificar código y usar herramientas locales.

---

## Guía rápida si empiezas de cero

### 1. Instala las herramientas base

1. **Git**  
   - Descarga desde [git-scm.com](https://git-scm.com/downloads) y acepta la instalación por defecto.
   - Asegúrate de haber seguido los primeros pasos de configuración del campus antes de empezar este onboarding (Dualboot o MacOs instalaciones básicas y clave SSH configurada): https://campus-codearts.com/course/section.php?id=175
   - Si estás en Ubuntu recién instalado, actualiza primero los paquetes y añade utilidades básicas:  
     ```bash
     sudo apt update
     sudo apt install curl git build-essential -y
     ```

2. **Node.js + npm (Angular necesita Node 20.19 o 22.x)**  
   - Recomendado: instala [nvm](https://github.com/nvm-sh/nvm#installing-and-updating) y luego ejecuta:  
     ```bash
     nvm install 22
     nvm use 22
     ```  
   - Comprueba que todo está correcto: `node -v` debería mostrar `v22.x`, `npm -v` debería estar en la rama 10 u 11.
    - Si estás en un contenedor/VM sin `nvm`, usa el repositorio oficial de NodeSource:  
      ```bash
      curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
      sudo apt install nodejs -y
      node -v
      npm -v
      ```

3. **Angular CLI** (para `ng serve`)  
   ```bash
   npm install -g @angular/cli@20
   ng version
   ```
   Verás la versión instalada y podrás usar `ng` en cualquier carpeta.

4. **PHP 8.3 + Composer** (necesario para Symfony)  
   - Linux (Debian/Ubuntu):  
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
   - Más opciones de instalación en [symfony.com/download](https://symfony.com/download).

6. **Docker Desktop** .  
   - [docs.docker.com/get-docker](https://docs.docker.com/desktop/setup/install/linux/debian/#install-docker-desktop)

---

### 2. Clona el repositorio

```bash
git clone <URL_REPOSITORIO>
cd <REPOSITORIO>
```

Para obtener la Url del repo, entra al mismo en GitLab y copia la Url del desplegable Code y copia la url destinada a SSH.

---

### 3. Arranca el backend (Symfony) desde la terminal

```bash
cd symfony-backend
composer install
symfony server:start --no-tls --port=8000
```

> Sin Symfony CLI usa `php -S 127.0.0.1:8000 -t public`. Asegúrate de tener PostgreSQL corriendo por Docker antes de iniciar.

---

### 4. Instala dependencias del frontend

```bash
cd ../angular-frontend
npm install
```

- Verifica que `node -v` devuelve 22.x. Si no, vuelve al paso 1 y selecciona la versión correcta con `nvm use 22`.

---

### 5. Decide cómo gestionar PostgreSQL

- **Opción rápida:** usa Docker solo para la base de datos
  ```bash
  docker compose up db -d
  ```

Comprueba que la base está en marcha antes de levantar el backend.

---

### 6. Arranca el backend en local

```bash
cd symfony-backend
symfony server:start --no-tls --port=8000
```

- Sin Symfony CLI:  
  ```bash
  php -S 127.0.0.1:8000 -t public
  ```
- Accede a `http://localhost:8000` para ver si el backend responde.
- Para detenerlo: `symfony server:stop` o `Ctrl+C` en la terminal.

---

### 7. Arranca el frontend en local

```bash
cd angular-frontend
ng serve 
```

- `ng serve` recompila en caliente; deja ese terminal abierto durante el desarrollo.
- Abre [http://localhost:4200](http://localhost:4200) y verifica que la UI se conecta al backend.

Para detener el frontend, usa `Ctrl+C`.

---

### 8. Resumen de por qué hacerlo en local

- Practicas el uso de la terminal (`git`, `npm`, `composer`, `symfony`) y entiendes mejor qué hace cada comando.
- Puedes depurar con las herramientas habituales de tu editor/IDE.
- Controlas versiones concretas (Node 22, Angular CLI 20, PHP 8.3) iguales a las del entorno Docker, evitando sorpresas.

---

## Ejecución completa con Docker Compose

Si prefieres no instalar dependencias, puedes levantar todo el stack con Docker.

### Requisitos
- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Pasos
1. Desde la raíz del proyecto:
   ```bash
   docker compose up -d
   ```
2. Verifica contenedores en ejecución:
   ```bash
   docker ps
   ```
   Deberías ver tres contenedores: **symfony_backend**, **angular_frontend** y **symfony_postgres**.
3. Accede a:
   - Frontend: [http://localhost:4200](http://localhost:4200)
   - Backend: [http://localhost:8000](http://localhost:8000)
   - PostgreSQL: puerto `5432` (usuario `symfony`, password `password`).

### Gestión de contenedores
- Parar todo: `docker compose down`
- Parar y borrar volúmenes (elimina datos base de datos): `docker compose down -v`
- Ver logs en tiempo real: `docker compose logs -f backend frontend db`

---

## Preguntas frecuentes

- **¿Por qué insistimos en la versión exacta de Node y PHP?**  
  Angular 20 requiere Node >= 20.19 o 22.x. Symfony 7.3 aprovecha funcionalidades de PHP 8.3, por lo que versiones anteriores pueden fallar.

- **¿Qué hago si `ng serve` pide permisos?**  
  Ajusta la propiedad de la carpeta a tu usuario con `sudo chown -R $USER:$USER /ruta/al/proyecto` y evita usar `sudo npm install` en Linux/Mac.

- **Docker dice que los puertos están en uso**  
  Ejecuta los contenedores con Docker Desktop y `docker compose up`, nunca con `sudo docker compose up`, porque crea recursos fuera del contexto de tu usuario. Si ya lo hiciste:
  1. Cierra todo con `sudo docker compose down` y luego `docker compose down`.
  2. Si los puertos 8000/4200/5432 siguen ocupados, busca los procesos con `lsof -i :8000` (cambia el puerto según corresponda) y elimina el PID problemático con `kill PID` o `kill -9 PID`.

---

Ahora ya sabes cómo preparar tu entorno desde cero, ejecutar la aplicación en local para desarrollo y levantar todo con Docker cuando quieras validar el despliegue completo.

Habiendo seguido estos pasos en tu máquina, ya puedes empezar a trabajar en los ejercicios de onboarding que están marcados en la tabla de issues del repositorio.
