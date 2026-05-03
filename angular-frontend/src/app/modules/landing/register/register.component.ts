import { Component, DestroyRef, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterLink, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ThemeToggleComponent } from '../../shared/theme-toggle/theme-toggle.component';

interface CheckEmailResponse {
  available: boolean;
  message: string;
}

interface RegisterResponse {
  message?: string;
}

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [RouterLink, ThemeToggleComponent],
  templateUrl: './register.component.html'
})
export class RegisterComponent {
  name = '';
  email = '';
  password = '';
  confirmPassword = '';
  successMessage = '';
  errorMessage = '';
  emailErrorMessage = '';
  isSubmitting = false;
  isCheckingEmail = false;
  selectedFile: File | null = null;
  avatarPreview: string | null = null;

  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  checkEmail(): void {
    if (!this.email.trim()) {
      this.emailErrorMessage = '';
      return;
    }

    this.isCheckingEmail = true;
    this.http.post<CheckEmailResponse>('/api/check-email', { email: this.email.trim() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isCheckingEmail = false;
          this.emailErrorMessage = response.available ? '' : response.message;
        },
        error: () => {
          this.isCheckingEmail = false;
          this.emailErrorMessage = '';
        }
      });
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
      return;
    }

    if (!file.type.match(/image\/(jpeg|png|webp)/)) {
      this.errorMessage = 'Formato de imagen no válido. Usa JPG, PNG o WEBP.';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      this.errorMessage = 'La imagen no puede superar los 5MB.';
      return;
    }

    this.selectedFile = file;
    this.errorMessage = '';

    const reader = new FileReader();
    reader.onload = () => {
      this.avatarPreview = reader.result as string;
    };
    reader.readAsDataURL(file);
  }

  register(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (this.emailErrorMessage) {
      this.errorMessage = 'Por favor, resuelve los errores antes de continuar.';
      return;
    }

    if (!this.name.trim() || !this.email.trim() || !this.password || !this.confirmPassword) {
      this.errorMessage = 'Por favor completa todos los campos.';
      return;
    }

    if (this.password !== this.confirmPassword) {
      this.errorMessage = 'Las contraseñas no coinciden.';
      return;
    }

    this.isSubmitting = true;

    const formData = new FormData();
    formData.append('name', this.name.trim());
    formData.append('email', this.email.trim());
    formData.append('password', this.password);

    if (this.selectedFile) {
      formData.append('avatar', this.selectedFile);
    }

    this.http
      .post<RegisterResponse>('/api/register', formData)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.successMessage = 'Cuenta creada correctamente. Redirigiendo al inicio de sesión...';
          this.resetForm();
          this.isSubmitting = false;
          setTimeout(() => this.router.navigate(['/']), 2000);
        },
        error: (error) => {
          if (error.status === 409) {
            this.emailErrorMessage = error?.error?.message ?? 'Este correo ya está registrado.';
            this.errorMessage = '';
          } else {
            this.errorMessage = error?.error?.message ?? 'No se pudo crear la cuenta.';
          }
          this.isSubmitting = false;
        }
      });
  }

  private resetForm(): void {
    this.name = '';
    this.email = '';
    this.password = '';
    this.confirmPassword = '';
    this.emailErrorMessage = '';
    this.selectedFile = null;
    this.avatarPreview = null;
  }
}
