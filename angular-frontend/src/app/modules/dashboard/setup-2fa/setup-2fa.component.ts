import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-setup-2fa',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './setup-2fa.component.html',
})
export class Setup2faComponent {
  @Input() enabled = false;
  @Output() changed = new EventEmitter<boolean>();

  qrCode = '';
  manualSecret = '';
  code = '';
  loading = false;
  saving = false;
  error = '';
  message = '';

  private readonly authService = inject(AuthService);

  startSetup(): void {
    this.loading = true;
    this.error = '';
    this.message = '';

    this.authService.getTwoFactorSetup().subscribe({
      next: (response) => {
        this.enabled = response.enabled;
        this.qrCode = response.qrCode ?? '';
        this.manualSecret = response.secret ?? '';
        this.message = response.message ?? '';
        this.loading = false;
      },
      error: (error) => {
        this.error = error.error?.message || 'No se pudo preparar la autenticación en dos pasos.';
        this.loading = false;
      },
    });
  }

  enable(): void {
    this.error = '';
    this.message = '';

    if (!/^\d{6}$/.test(this.code.trim())) {
      this.error = 'Introduce el código de 6 dígitos.';
      return;
    }

    this.saving = true;
    this.authService.enableTwoFactor(this.code.trim()).subscribe({
      next: (response) => {
        if (!response.twoFactorEnabled) {
          this.error = response.message || 'Código 2FA no válido.';
          this.saving = false;
          return;
        }

        this.enabled = response.twoFactorEnabled;
        this.qrCode = '';
        this.manualSecret = '';
        this.code = '';
        this.message = response.message;
        this.saving = false;
        this.changed.emit(true);
      },
      error: (error) => {
        this.error = error.error?.message || 'No se pudo activar la autenticación en dos pasos.';
        this.saving = false;
      },
    });
  }

  disable(): void {
    this.saving = true;
    this.error = '';
    this.message = '';

    this.authService.disableTwoFactor().subscribe({
      next: (response) => {
        this.enabled = response.twoFactorEnabled;
        this.qrCode = '';
        this.manualSecret = '';
        this.code = '';
        this.message = response.message;
        this.saving = false;
        this.changed.emit(false);
      },
      error: (error) => {
        this.error = error.error?.message || 'No se pudo desactivar la autenticación en dos pasos.';
        this.saving = false;
      },
    });
  }
}
