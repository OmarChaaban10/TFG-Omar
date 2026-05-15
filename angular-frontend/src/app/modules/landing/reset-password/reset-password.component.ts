import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ThemeToggleComponent } from '../../shared/theme-toggle/theme-toggle.component';

interface ResetPasswordResponse {
  message: string;
}

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [FormsModule, RouterLink, ThemeToggleComponent],
  templateUrl: './reset-password.component.html',
})
export class ResetPasswordComponent implements OnInit {
  token = '';
  password = '';
  confirmPassword = '';
  loading = false;
  successMessage = '';
  errorMessage = '';

  constructor(
    private readonly http: HttpClient,
    private readonly route: ActivatedRoute,
    private readonly router: Router,
  ) {
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token') ?? '';
    if (!this.token) {
      this.errorMessage = 'El enlace de recuperación no es válido.';
    }
  }

  resetPassword(): void {
    this.successMessage = '';
    this.errorMessage = '';

    if (!this.token) {
      this.errorMessage = 'El enlace de recuperación no es válido.';
      return;
    }

    if (!this.password || !this.confirmPassword) {
      this.errorMessage = 'Completa los dos campos de contraseña.';
      return;
    }

    if (this.password !== this.confirmPassword) {
      this.errorMessage = 'Las contraseñas no coinciden.';
      return;
    }

    this.loading = true;

    this.http.post<ResetPasswordResponse>('/api/reset-password', {
      token: this.token,
      password: this.password,
    }).subscribe({
      next: (response) => {
        this.loading = false;
        this.successMessage = response.message;
        this.password = '';
        this.confirmPassword = '';
        setTimeout(() => this.router.navigate(['/']), 1800);
      },
      error: (error) => {
        this.loading = false;
        this.errorMessage = error.error?.message || 'No se pudo actualizar la contraseña.';
      },
    });
  }
}
