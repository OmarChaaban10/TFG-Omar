import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, EventEmitter, OnInit, Output, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs/operators';
import { Setup2faComponent } from '../setup-2fa/setup-2fa.component';

interface ProfileUser {
  id: number;
  name: string;
  email: string;
  avatarUrl: string | null;
  twoFactorEnabled: boolean;
}

interface DeleteAccountResponse {
  deleted: boolean;
  message: string;
}

@Component({
  selector: 'app-config',
  standalone: true,
  imports: [CommonModule, FormsModule, Setup2faComponent],
  templateUrl: './config.component.html',
})
export class ConfigComponent implements OnInit {
  @Output() profileUpdated = new EventEmitter<ProfileUser>();

  user: ProfileUser | null = null;
  name = '';
  email = '';
  avatarUrl: string | null = null;
  selectedAvatarFile: File | null = null;
  avatarPreviewUrl: string | null = null;
  currentPassword = '';
  newPassword = '';
  confirmPassword = '';
  isLoading = false;
  isSaving = false;
  showDeleteAccountModal = false;
  isDeletingAccount = false;
  deleteAccountPassword = '';
  deleteAccountConfirmation = '';
  error = '';
  deleteAccountError = '';
  successMessage = '';

  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  ngOnInit(): void {
    this.fetchProfile();
  }

  get initials(): string {
    return (this.name || 'Usuario')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('');
  }

  get passwordRequested(): boolean {
    return this.currentPassword !== '' || this.newPassword !== '' || this.confirmPassword !== '';
  }

  fetchProfile(): void {
    this.isLoading = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ user: ProfileUser }>('/api/users/me', {
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(finalize(() => { this.isLoading = false; }))
      .subscribe({
        next: (res) => this.applyUser(res.user),
        error: () => {
          this.error = 'No se pudo cargar tu perfil.';
        },
      });
  }

  onAvatarSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
      this.error = 'El avatar debe ser JPG, PNG o WEBP.';
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      this.error = 'El avatar no puede superar los 5MB.';
      return;
    }

    this.selectedAvatarFile = file;
    this.avatarPreviewUrl = URL.createObjectURL(file);
    this.error = '';
    this.successMessage = '';
  }

  clearAvatarSelection(): void {
    this.selectedAvatarFile = null;
    if (this.avatarPreviewUrl) {
      URL.revokeObjectURL(this.avatarPreviewUrl);
    }
    this.avatarPreviewUrl = null;
  }

  saveProfile(): void {
    const trimmedName = this.name.trim();
    if (!trimmedName) {
      this.error = 'El nombre es obligatorio.';
      return;
    }

    if (this.passwordRequested && this.newPassword !== this.confirmPassword) {
      this.error = 'La nueva contraseña y su confirmación no coinciden.';
      return;
    }

    this.isSaving = true;
    this.error = '';
    this.successMessage = '';

    const payload = new FormData();
    payload.append('name', trimmedName);
    if (this.selectedAvatarFile) {
      payload.append('avatar', this.selectedAvatarFile);
    }
    if (this.passwordRequested) {
      payload.append('currentPassword', this.currentPassword);
      payload.append('newPassword', this.newPassword);
    }

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<{ message: string; user: ProfileUser }>('/api/users/me', payload, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(finalize(() => { this.isSaving = false; }))
      .subscribe({
        next: (res) => {
          this.applyUser(res.user);
          this.currentPassword = '';
          this.newPassword = '';
          this.confirmPassword = '';
          this.clearAvatarSelection();
          this.successMessage = res.message;
          this.profileUpdated.emit(res.user);
        },
        error: (err) => {
          this.error = err.error?.message ?? 'No se pudo actualizar el perfil.';
        },
      });
  }

  openDeleteAccountModal(): void {
    this.showDeleteAccountModal = true;
    this.deleteAccountPassword = '';
    this.deleteAccountConfirmation = '';
    this.deleteAccountError = '';
  }

  closeDeleteAccountModal(): void {
    if (this.isDeletingAccount) return;

    this.showDeleteAccountModal = false;
    this.deleteAccountPassword = '';
    this.deleteAccountConfirmation = '';
    this.deleteAccountError = '';
  }

  deleteAccount(): void {
    if (this.isDeletingAccount) return;

    if (!this.deleteAccountPassword) {
      this.deleteAccountError = 'Introduce tu contraseña actual.';
      return;
    }

    if (this.deleteAccountConfirmation.trim() !== 'ELIMINAR') {
      this.deleteAccountError = 'Debes escribir "ELIMINAR" para confirmar.';
      return;
    }

    this.isDeletingAccount = true;
    this.deleteAccountError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.request<DeleteAccountResponse>('DELETE', '/api/users/me', {
      body: {
        currentPassword: this.deleteAccountPassword,
        confirmationText: this.deleteAccountConfirmation.trim(),
      },
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(finalize(() => { this.isDeletingAccount = false; }))
      .subscribe({
        next: (res) => {
          if (!res.deleted) {
            this.deleteAccountError = res.message || 'No se pudo eliminar la cuenta.';
            return;
          }

          localStorage.removeItem('jwt_token');
          sessionStorage.removeItem('jwt_token');
          sessionStorage.removeItem('selected_board_project');
          this.router.navigate(['/']);
        },
        error: (err) => {
          this.deleteAccountError = err.error?.message ?? 'No se pudo eliminar la cuenta.';
        },
      });
  }

  updateTwoFactorState(enabled: boolean): void {
    if (!this.user) return;

    this.user = { ...this.user, twoFactorEnabled: enabled };
  }

  private applyUser(user: ProfileUser): void {
    this.user = user;
    this.name = user.name;
    this.email = user.email;
    this.avatarUrl = user.avatarUrl;
  }
}
