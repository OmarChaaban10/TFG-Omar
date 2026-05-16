import { Component, OnInit } from '@angular/core';
import { RouterLink, Router } from '@angular/router';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { ThemeToggleComponent } from '../../shared/theme-toggle/theme-toggle.component';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  templateUrl: './login.component.html',
  imports: [RouterLink, ReactiveFormsModule, ThemeToggleComponent]
})
export class LoginComponent implements OnInit {
  loginForm!: FormGroup;
  showPassword = false;
  errorMessage = '';
  isSubmitting = false;

  constructor(
    private readonly fb: FormBuilder,
    private readonly authService: AuthService,
    private readonly router: Router,
  ) {
  }

  ngOnInit(): void {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]],
      rememberMe: [false]
    });
  }

  togglePassword(): void {
    this.showPassword = !this.showPassword;
  }

  login(): void {
    this.errorMessage = '';

    if (this.loginForm.invalid) {
      this.errorMessage = 'Por favor completa todos los campos correctamente.';
      return;
    }

    const { email, password, rememberMe } = this.loginForm.value;

    this.isSubmitting = true;
    this.authService
      .login(email.trim(), password)
      .subscribe({
        next: (response) => {
          if (response.require_2fa && response.challengeToken) {
            sessionStorage.setItem('two_factor_challenge', response.challengeToken);
            sessionStorage.setItem('two_factor_remember', rememberMe ? 'true' : 'false');
            this.isSubmitting = false;
            this.router.navigate(['/2fa-verify']);
            return;
          }

          if (!response.token) {
            this.errorMessage = 'No se pudo iniciar sesión.';
            this.isSubmitting = false;
            return;
          }

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
          if (error?.status === 401 && error?.error?.require_2fa && error?.error?.challengeToken) {
            sessionStorage.setItem('two_factor_challenge', error.error.challengeToken);
            sessionStorage.setItem('two_factor_remember', rememberMe ? 'true' : 'false');
            this.isSubmitting = false;
            this.router.navigate(['/2fa-verify']);
            return;
          }

          let msg = error?.error?.message;
          if (msg === 'Invalid credentials.') {
            msg = 'Correo electrónico o contraseña incorrectos.';
          }
          this.errorMessage = msg || 'Ha ocurrido un error inesperado al iniciar sesión.';
          this.isSubmitting = false;
        }
      });
  }
}
