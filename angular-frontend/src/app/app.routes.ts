import { Routes } from '@angular/router';
import { LoginComponent } from './modules/landing/login/login.component';
import { ForgotPasswordComponent } from './modules/landing/forgot-password/forgot-password.component';
import { RegisterComponent } from './modules/landing/register/register.component';
import { DashboardComponent } from './modules/dashboard/dashboard.component';

export const routes: Routes = [
	{
		path: '',
		component: LoginComponent
	},
	{
		path: 'forgot-password',
		component: ForgotPasswordComponent
	},
	{
		path: 'register',
		component: RegisterComponent
	},
	{
		path: 'dashboard',
		component: DashboardComponent
	}
];
