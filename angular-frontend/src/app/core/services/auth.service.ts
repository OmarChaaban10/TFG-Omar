import { HttpClient } from '@angular/common/http';
import { HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export interface LoginResponse {
  token?: string;
  require_2fa?: boolean;
  challengeToken?: string;
  expiresAt?: string;
  message?: string;
}

export interface TwoFactorSetupResponse {
  enabled: boolean;
  secret?: string;
  qrCode?: string;
  otpAuthUrl?: string;
  message?: string;
}

export interface TwoFactorStatusResponse {
  message: string;
  twoFactorEnabled: boolean;
}

export interface TwoFactorVerifyResponse {
  verified?: boolean;
  blocked?: boolean;
  token?: string;
  message?: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>('/api/login', { email, password });
  }

  getTwoFactorSetup(): Observable<TwoFactorSetupResponse> {
    return this.http.get<TwoFactorSetupResponse>('/api/users/me/2fa/setup', {
      headers: this.authHeaders(),
    });
  }

  enableTwoFactor(code: string): Observable<TwoFactorStatusResponse> {
    return this.http.post<TwoFactorStatusResponse>('/api/users/me/2fa/enable', { code }, {
      headers: this.authHeaders(),
    });
  }

  disableTwoFactor(): Observable<TwoFactorStatusResponse> {
    return this.http.post<TwoFactorStatusResponse>('/api/users/me/2fa/disable', {}, {
      headers: this.authHeaders(),
    });
  }

  verifyTwoFactor(challengeToken: string, authCode: string): Observable<TwoFactorVerifyResponse> {
    return this.http.post<TwoFactorVerifyResponse>('/api/2fa_check', { challengeToken, authCode });
  }

  private authHeaders(): HttpHeaders {
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token') || '';

    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }
}
