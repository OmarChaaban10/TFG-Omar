import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { ThemeToggleComponent } from '../../shared/theme-toggle/theme-toggle.component';

@Component({
  selector: 'app-two-factor-verify',
  standalone: true,
  imports: [FormsModule, RouterLink, ThemeToggleComponent],
  templateUrl: './two-factor-verify.component.html',
})
export class TwoFactorVerifyComponent implements OnInit {
  code = '';
  errorMessage = '';
  blocked = false;
  isSubmitting = false;

  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  ngOnInit(): void {
    if (!sessionStorage.getItem('two_factor_challenge')) {
      this.router.navigate(['/']);
    }
  }

  verify(): void {
    this.errorMessage = '';
    const challengeToken = sessionStorage.getItem('two_factor_challenge') ?? '';
    const rememberMe = sessionStorage.getItem('two_factor_remember') === 'true';
    const code = this.code.trim();

    if (!/^\d{6}$/.test(code)) {
      this.errorMessage = 'Introduce el código de 6 dígitos.';
      return;
    }

    this.isSubmitting = true;
    this.authService.verifyTwoFactor(challengeToken, code).subscribe({
      next: (response) => {
        if (!response.token) {
          this.errorMessage = response.message || 'Código incorrecto o caducado.';
          this.blocked = response.blocked === true;
          this.isSubmitting = false;
          return;
        }

        this.clearChallenge();
        localStorage.removeItem('jwt_token');
        sessionStorage.removeItem('jwt_token');
        sessionStorage.removeItem('selected_board_project');

        if (rememberMe) {
          localStorage.setItem('jwt_token', response.token);
        } else {
          sessionStorage.setItem('jwt_token', response.token);
        }

        this.isSubmitting = false;
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Código incorrecto o caducado.';
        this.isSubmitting = false;
      },
    });
  }

  cancel(): void {
    this.clearChallenge();
    this.router.navigate(['/']);
  }

  private clearChallenge(): void {
    sessionStorage.removeItem('two_factor_challenge');
    sessionStorage.removeItem('two_factor_remember');
  }
}
