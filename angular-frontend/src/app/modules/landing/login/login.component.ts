import { Component, ChangeDetectorRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NgIf } from '@angular/common';
import { RouterLink, Router } from '@angular/router';

@Component({
  selector: 'app-login',
  standalone: true,
  templateUrl: './login.component.html',
  imports: [RouterLink, NgIf]
})
export class LoginComponent {
  email = '';
  password = '';
  rememberMe = false;
  errorMessage = '';
  isSubmitting = false;

  constructor(
    private readonly http: HttpClient,
    private readonly router: Router,
    private readonly cdr: ChangeDetectorRef
  ) {}

  login(): void {
    this.errorMessage = '';

    if (!this.email.trim() || !this.password) {
      this.errorMessage = 'Por favor completa todos los campos.';
      return;
    }

    this.isSubmitting = true;
    this.http
      .post<{ token: string }>('/api/login', {
        email: this.email.trim(),
        password: this.password
      })
      .subscribe({
        next: (response) => {
          if (this.rememberMe) {
            localStorage.setItem('jwt_token', response.token);
          } else {
            sessionStorage.setItem('jwt_token', response.token);
          }
          this.isSubmitting = false;
          this.cdr.detectChanges();
          this.router.navigate(['/dashboard']);
        },
        error: (error) => {
          this.errorMessage = error?.error?.message ?? 'Credenciales incorrectas. Inténtalo de nuevo.';
          this.isSubmitting = false;
          this.cdr.detectChanges();
        }
      });
  }
}
