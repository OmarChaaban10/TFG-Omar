import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ThemeToggleComponent } from '../../shared/theme-toggle/theme-toggle.component';

interface ForgotPasswordResponse {
  message: string;
}

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [RouterLink, FormsModule, ThemeToggleComponent],
  templateUrl: './forgot-password.component.html'
})
export class ForgotPasswordComponent {
  email = '';
  loading = false;
  successMessage = '';
  errorMessage = '';

  constructor(private readonly http: HttpClient) {
  }

  onSubmit(): void {
    this.successMessage = '';
    this.errorMessage = '';

    if (!this.email) {
      this.errorMessage = 'Por favor, ingresa tu correo electrónico.';
      return;
    }

    this.loading = true;
    this.http.post<ForgotPasswordResponse>('/api/forgot-password', { email: this.email })
      .subscribe({
        next: (response) => {
          this.successMessage = response.message || 'Correo enviado exitosamente.';
          this.loading = false;
        },
        error: (error) => {
          this.errorMessage = error.error?.message || 'Error al enviar el correo.';
          this.loading = false;
        }
      });
  }
}
