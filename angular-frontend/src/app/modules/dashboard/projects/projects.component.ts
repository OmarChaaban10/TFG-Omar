import { Component, OnInit, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';

interface ProjectMemberDetail {
  id: number;
  name: string;
  email: string;
  avatarUrl: string | null;
  role: string;
}

interface ProjectFull {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  myRole: string;
  progress: number;
  totalTasks: number;
  doneTasks: number;
  members: ProjectMemberDetail[];
  createdAt: string;
}

@Component({
  selector: 'app-projects-view',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './projects-view.component.html',
})
export class ProjectsViewComponent implements OnInit {
  @Output() requestCreateProject = new EventEmitter<void>();

  allProjects: ProjectFull[] = [];
  isLoadingProjects = false;
  projectsError = '';
  expandedProjectIds: Set<number> = new Set();

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.fetchAllProjects();
  }

  fetchAllProjects(): void {
    this.isLoadingProjects = true;
    this.projectsError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ projects: ProjectFull[] }>('/api/projects/all', {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isLoadingProjects = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.allProjects = res.projects;
        },
        error: () => {
          this.projectsError = 'No se pudieron cargar los proyectos.';
        }
      });
  }

  toggleProjectExpand(projectId: number): void {
    if (this.expandedProjectIds.has(projectId)) {
      this.expandedProjectIds.delete(projectId);
    } else {
      this.expandedProjectIds.add(projectId);
    }
  }

  isProjectExpanded(projectId: number): boolean {
    return this.expandedProjectIds.has(projectId);
  }

  getRoleClass(role: string): string {
    switch (role) {
      case 'Admin': return 'bg-purple-500/20 text-purple-400';
      case 'Gestor': return 'bg-sky-500/20 text-sky-400';
      default: return 'bg-emerald-500/20 text-emerald-400';
    }
  }

  getRoleLabelClass(role: string): string {
    switch (role) {
      case 'owner': return 'bg-amber-500/20 text-amber-400';
      case 'admin': return 'bg-purple-500/20 text-purple-400';
      case 'manager': return 'bg-sky-500/20 text-sky-400';
      default: return 'bg-emerald-500/20 text-emerald-400';
    }
  }

  getRoleLabel(role: string): string {
    switch (role) {
      case 'owner': return 'Propietario';
      case 'admin': return 'Admin';
      case 'manager': return 'Gestor';
      default: return 'Miembro';
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

  openCreateModal(): void {
    this.requestCreateProject.emit();
  }
}
