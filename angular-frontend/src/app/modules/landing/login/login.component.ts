import { Component, ChangeDetectorRef, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NgIf } from '@angular/common';
import { RouterLink, Router } from '@angular/router';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';

@Component({
  selector: 'app-login',
  standalone: true,
  templateUrl: './login.component.html',
  imports: [RouterLink, NgIf, ReactiveFormsModule]
})
export class LoginComponent implements OnInit {
  loginForm!: FormGroup;
  showPassword = false;
  errorMessage = '';
  isSubmitting = false;

  constructor(
    private readonly fb: FormBuilder,
    private readonly http: HttpClient,
    private readonly router: Router,
    private readonly cdr: ChangeDetectorRef
  ) {}

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
    this.http
      .post<{ token: string }>('/api/login', {
        email: email.trim(),
        password: password
      })
      .subscribe({
        next: (response) => {
          localStorage.removeItem('jwt_token');
          sessionStorage.removeItem('jwt_token');

          if (rememberMe) {
            localStorage.setItem('jwt_token', response.token);
          } else {
            sessionStorage.setItem('jwt_token', response.token);
          }
          this.isSubmitting = false;
          this.cdr.detectChanges();
          this.router.navigate(['/dashboard']);
        },
        error: (error) => {
          let msg = error?.error?.message;
          if (msg === 'Invalid credentials.') {
            msg = 'Correo electrónico o contraseña incorrectos.';
          }
          this.errorMessage = msg || 'Ha ocurrido un error inesperado al iniciar sesión.';
          this.isSubmitting = false;
          this.cdr.detectChanges();
        }
      });
  }
}
