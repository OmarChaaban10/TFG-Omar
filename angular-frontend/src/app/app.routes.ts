import { Routes } from '@angular/router';
import { LoginComponent } from './modules/landing/login/login.component';
import { ForgotPasswordComponent } from './modules/landing/forgot-password/forgot-password.component';
import { RegisterComponent } from './modules/landing/register/register.component';
import { DashboardComponent } from './modules/dashboard/dashboard.component';
import { authGuard } from './core/guards/auth.guard';
import { guestGuard } from './core/guards/guest.guard';

export const routes: Routes = [
	{
		path: '',
		component: LoginComponent,
		canActivate: [guestGuard]
	},
	{
		path: 'forgot-password',
		component: ForgotPasswordComponent,
		canActivate: [guestGuard]
	},
	{
		path: 'register',
		component: RegisterComponent,
		canActivate: [guestGuard]
	},
	{
		path: 'dashboard',
		component: DashboardComponent,
		canActivate: [authGuard]
	}
];
