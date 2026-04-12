import { Component, ChangeDetectorRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NgIf } from '@angular/common';
import { RouterLink, Router } from '@angular/router';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [NgIf, RouterLink],
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

  constructor(
    private readonly http: HttpClient,
    private readonly router: Router,
    private readonly cdr: ChangeDetectorRef
  ) {}

  checkEmail(): void {
    if (!this.email.trim()) {
      this.emailErrorMessage = '';
      return;
    }

    this.isCheckingEmail = true;
    this.http.post<{available: boolean, message: string}>('/api/check-email', { email: this.email.trim() })
      .subscribe({
        next: (response) => {
          this.isCheckingEmail = false;
          if (!response.available) {
            this.emailErrorMessage = response.message;
          } else {
            this.emailErrorMessage = '';
          }
          this.cdr.detectChanges();
        },
        error: () => {
          this.isCheckingEmail = false;
          this.emailErrorMessage = '';
          this.cdr.detectChanges();
        }
      });
  }

  onFileSelected(event: any): void {
    const file = event.target.files[0];
    if (file) {
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
        this.cdr.detectChanges();
      };
      reader.readAsDataURL(file);
    }
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
      .post<{ message?: string }>('/api/register', formData)
      .subscribe({
        next: (response) => {
          this.successMessage = 'Cuenta creada correctamente. Redirigiendo al inicio de sesión...';
          this.resetForm();
          this.isSubmitting = false;
          this.cdr.detectChanges();
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
          this.cdr.detectChanges();
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
