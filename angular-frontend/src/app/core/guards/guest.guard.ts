import { inject } from '@angular/core';
import { Router, type CanActivateFn } from '@angular/router';

export const guestGuard: CanActivateFn = () => {
  const router = inject(Router);
  
  // Comprobamos si hay un token guardado
  const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
  
  if (token) {
    // Si el usuario ya está logeado, lo mandamos al dashboard
    return router.createUrlTree(['/dashboard']);
  }
  
  return true;
};
