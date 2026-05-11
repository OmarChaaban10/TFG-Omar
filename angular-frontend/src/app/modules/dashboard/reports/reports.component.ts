import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { finalize } from 'rxjs/operators';

interface ProjectOption {
  id: number;
  name: string;
}

interface UserSummary {
  id: number;
  name: string;
  avatarUrl: string | null;
}

interface ProjectLog {
  id: number;
  action: string;
  description: string;
  details: any;
  createdAt: string;
  user: UserSummary | null;
}

@Component({
  selector: 'app-reports',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './reports.component.html',
})
export class ReportsComponent implements OnInit {
  projects: ProjectOption[] = [];
  selectedProjectId: number | null = null;
  logs: ProjectLog[] = [];
  
  isLoadingProjects = false;
  isLoadingLogs = false;
  projectsError = '';
  logsError = '';

  private readonly http = inject(HttpClient);

  ngOnInit(): void {
    this.fetchProjects();
  }

  fetchProjects(): void {
    this.isLoadingProjects = true;
    this.projectsError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ projects: any[] }>('/api/projects/all', {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(finalize(() => { this.isLoadingProjects = false; }))
      .subscribe({
        next: (res) => {
          this.projects = res.projects.map(p => ({ id: p.id, name: p.name }));
          if (this.projects.length > 0) {
            this.selectedProjectId = this.projects[0].id;
            this.fetchLogs();
          }
        },
        error: () => {
          this.projectsError = 'No se pudieron cargar los proyectos.';
        }
      });
  }

  onProjectChange(): void {
    if (this.selectedProjectId) {
      this.fetchLogs();
    }
  }

  fetchLogs(): void {
    if (!this.selectedProjectId) return;

    this.isLoadingLogs = true;
    this.logsError = '';
    this.logs = [];

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ logs: ProjectLog[] }>(`/api/projects/${this.selectedProjectId}/logs`, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(finalize(() => { this.isLoadingLogs = false; }))
      .subscribe({
        next: (res) => {
          this.logs = res.logs;
        },
        error: () => {
          this.logsError = 'No se pudieron cargar los logs del proyecto.';
        }
      });
  }

  getActionIcon(action: string): string {
    switch (action) {
      case 'project_created':
        return 'M12 4.5v15m7.5-7.5h-15'; // Plus icon
      case 'member_invited':
        return 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'; // User add
      case 'task_moved':
        return 'M13 5l7 7-7 7M5 5l7 7-7 7'; // Chevron double right
      default:
        return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; // Info icon
    }
  }
}
