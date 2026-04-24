import { inject } from '@angular/core';
import { Router, type CanActivateFn } from '@angular/router';

export const authGuard: CanActivateFn = () => {
  const router = inject(Router);
  
  // Comprobamos si hay un token guardado
  const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
  
  if (token) {
    return true;
  }
  
  // Si no hay token, lo mandamos a la página de inicio de sesión
  return router.createUrlTree(['/']);
};
