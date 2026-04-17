import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { finalize } from 'rxjs/operators';
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard.component.html'
})
export class DashboardComponent implements OnInit {
  userName = 'Usuario';
  userInitials = 'U';
  avatarUrl: string | null = null;
  pendingTasks = 0;
  stats: any[] = [];
  recentProjects: any[] = [];
  isLoading = true;
  error = '';

  constructor(
    private http: HttpClient,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.fetchDashboardData();
  }

  fetchDashboardData(): void {
    this.isLoading = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<any>('/api/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
      .pipe(
        finalize(() => {
          this.isLoading = false;
          this.cdr.detectChanges();
        })
      )
      .subscribe({
        next: (data) => {
          this.userName = data.userName;
          this.userInitials = data.userName
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((w: string) => w.charAt(0).toUpperCase())
            .join('');
          this.avatarUrl = data.avatarUrl ?? null;
          this.pendingTasks = data.pendingTasks;
          this.stats = data.stats;
          this.recentProjects = data.recentProjects;
        },
        error: (err) => {
          console.error('Error fetching dashboard data', err);
          this.error = 'No se han podido cargar los datos del dashboard.';
        }
      });
  }
}
