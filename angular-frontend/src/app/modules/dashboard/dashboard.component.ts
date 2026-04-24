import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';

interface DashboardStat {
  label: string;
  value: number;
}

interface RecentProject {
  id: number;
  name: string;
  role: string;
  progress: number;
}

interface DashboardResponse {
  userName: string;
  avatarUrl: string | null;
  pendingTasks: number;
  stats: DashboardStat[];
  recentProjects: RecentProject[];
}

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
  stats: DashboardStat[] = [];
  recentProjects: RecentProject[] = [];
  isLoading = true;
  error = '';

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.fetchDashboardData();
  }

  fetchDashboardData(): void {
    this.isLoading = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<DashboardResponse>('/api/dashboard', {
      headers: {
        Authorization: `Bearer ${token}`
      }
    })
      .pipe(
        finalize(() => {
          this.isLoading = false;
        }),
        takeUntilDestroyed(this.destroyRef),
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

  getRoleClass(role: string): string {
    switch (role) {
      case 'Admin': return 'bg-purple-500/20 text-purple-400';
      case 'Gestor': return 'bg-sky-500/20 text-sky-400';
      default: return 'bg-emerald-500/20 text-emerald-400';
    }
  }

  getProgressClass(progress: number): string {
    if (progress < 40) return 'bg-red-500';
    if (progress < 80) return 'bg-orange-500';
    return 'bg-emerald-500';
  }

  getTextProgressClass(progress: number): string {
    if (progress < 40) return 'text-red-500';
    if (progress < 80) return 'text-orange-500';
    return 'text-emerald-500';
  }

  getStatColor(index: number): string {
    const colors = ['text-orange-500', 'text-blue-500', 'text-emerald-500', 'text-purple-500'];
    return colors[index] ?? 'text-slate-400';
  }

  getStatBorderClass(index: number): string {
    const borders = ['border-orange-500', 'border-blue-500', 'border-emerald-500', 'border-purple-500'];
    return borders[index] ?? 'border-slate-700';
  }
}
