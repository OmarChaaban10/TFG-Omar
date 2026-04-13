import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [RouterLink, FormsModule, CommonModule],
  templateUrl: './forgot-password.component.html'
})
export class ForgotPasswordComponent {
  email: string = '';
  loading: boolean = false;
  successMessage: string = '';
  errorMessage: string = '';

  private http = inject(HttpClient);

  onSubmit() {
    this.successMessage = '';
    this.errorMessage = '';

    if (!this.email) {
      this.errorMessage = 'Por favor, ingresa tu correo electrónico.';
      return;
    }

    this.loading = true;
    this.http.post('/api/forgot-password', { email: this.email }).subscribe({
      next: (response: any) => {
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
