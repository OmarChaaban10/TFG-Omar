import { Component, OnInit, DestroyRef, inject, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, finalize, switchMap } from 'rxjs/operators';

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

interface ProjectSimple {
  id: number;
  name: string;
}

interface UserSearch {
  id: number;
  name: string;
  email: string;
  avatarUrl: string | null;
}

interface DashboardResponse {
  userName: string;
  avatarUrl: string | null;
  pendingTasks: number;
  stats: DashboardStat[];
  recentProjects: RecentProject[];
}

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
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
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
  dropdownOpen = false;

  // View state
  activeView: 'dashboard' | 'projects' = 'dashboard';

  // Projects view state
  allProjects: ProjectFull[] = [];
  isLoadingProjects = false;
  projectsError = '';
  expandedProjectIds: Set<number> = new Set();

  // Modal state
  showCreateModal = false;
  newProjectName = '';
  newProjectDescription = '';
  newProjectColor = '';
  isCreating = false;
  createError = '';

  readonly colorPresets = [
    '#f97316', '#3b82f6', '#10b981', '#a855f7',
    '#ef4444', '#eab308', '#06b6d4', '#ec4899',
  ];

  // Search state
  searchQuery = '';
  searchResults: RecentProject[] = [];
  isSearching = false;
  hasSearched = false;
  private readonly searchSubject = new Subject<string>();

  // Invite state
  showInviteModal = false;
  participatingProjects: ProjectSimple[] = [];
  selectedProjectId = 0;
  inviteSearchQuery = '';
  inviteSearchResults: UserSearch[] = [];
  selectedUserToInvite: UserSearch | null = null;
  isInviting = false;
  isSearchingUsers = false;
  hasSearchedUsers = false;
  inviteError = '';
  inviteSuccessMessage = '';
  private readonly userSearchSubject = new Subject<{ query: string, projectId: number }>();

  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.fetchDashboardData();
    this.initSearchListener();
    this.initUserSearchListener();
  }

  private initSearchListener(): void {
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.searchSubject.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap((query) => {
        const trimmed = query.trim();
        if (trimmed === '') {
          this.hasSearched = false;
          this.searchResults = [];
          this.isSearching = false;
          return [];
        }
        this.isSearching = true;
        return this.http.get<{ results: RecentProject[] }>('/api/projects/search', {
          params: { q: trimmed },
          headers: { Authorization: `Bearer ${token}` },
        });
      }),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: (res) => {
        this.searchResults = res.results;
        this.hasSearched = true;
        this.isSearching = false;
      },
      error: () => {
        this.isSearching = false;
      },
    });
  }

  onSearchInput(): void {
    this.searchSubject.next(this.searchQuery);
  }

  clearSearch(): void {
    this.searchQuery = '';
    this.searchResults = [];
    this.hasSearched = false;
  }

  private initUserSearchListener(): void {
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.userSearchSubject.pipe(
      debounceTime(300),
      distinctUntilChanged((prev, curr) => prev.query === curr.query && prev.projectId === curr.projectId),
      switchMap(({ query, projectId }) => {
        const trimmed = query.trim();
        if (trimmed === '' || projectId === 0) {
          this.hasSearchedUsers = false;
          this.inviteSearchResults = [];
          this.isSearchingUsers = false;
          return [];
        }
        this.isSearchingUsers = true;
        return this.http.get<{ results: UserSearch[] }>('/api/users/search', {
          params: { q: trimmed, projectId: projectId.toString() },
          headers: { Authorization: `Bearer ${token}` },
        });
      }),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: (res) => {
        this.inviteSearchResults = res.results;
        this.hasSearchedUsers = true;
        this.isSearchingUsers = false;
      },
      error: () => {
        this.isSearchingUsers = false;
      },
    });
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

  toggleDropdown(): void {
    this.dropdownOpen = !this.dropdownOpen;
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    const target = event.target as HTMLElement;
    if (!target.closest('#user-menu-container')) {
      this.dropdownOpen = false;
    }
  }

  logout(): void {
    localStorage.removeItem('jwt_token');
    sessionStorage.removeItem('jwt_token');
    this.router.navigate(['/']);
  }

  setView(view: 'dashboard' | 'projects'): void {
    this.activeView = view;
    if (view === 'projects' && this.allProjects.length === 0) {
      this.fetchAllProjects();
    }
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

  openCreateModal(): void {
    this.newProjectName = '';
    this.newProjectDescription = '';
    this.newProjectColor = '';
    this.createError = '';
    this.showCreateModal = true;
  }

  closeCreateModal(): void {
    this.showCreateModal = false;
  }

  selectColor(color: string): void {
    this.newProjectColor = this.newProjectColor === color ? '' : color;
  }

  createProject(): void {
    const name = this.newProjectName.trim();
    if (name === '') {
      this.createError = 'El nombre del proyecto es obligatorio.';
      return;
    }

    this.isCreating = true;
    this.createError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<{ message: string }>('/api/projects', {
      name,
      description: this.newProjectDescription.trim(),
      color: this.newProjectColor,
    }, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isCreating = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.showCreateModal = false;
          this.fetchDashboardData();
        },
        error: (err) => {
          this.createError = err.error?.message ?? 'Error al crear el proyecto.';
        }
      });
  }

  openInviteModal(): void {
    this.showInviteModal = true;
    this.selectedProjectId = 0;
    this.inviteSearchQuery = '';
    this.inviteSearchResults = [];
    this.selectedUserToInvite = null;
    this.inviteError = '';
    this.inviteSuccessMessage = '';
    this.hasSearchedUsers = false;

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
    this.http.get<{ projects: ProjectSimple[] }>('/api/projects/participating', {
      headers: { Authorization: `Bearer ${token}` }
    }).pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.participatingProjects = res.projects;
          if (this.participatingProjects.length > 0) {
            this.selectedProjectId = this.participatingProjects[0].id;
          }
        },
        error: () => {
          this.inviteError = 'No se pudieron cargar los proyectos.';
        }
      });
  }

  closeInviteModal(): void {
    this.showInviteModal = false;
  }

  onProjectChange(): void {
    this.inviteSearchQuery = '';
    this.inviteSearchResults = [];
    this.selectedUserToInvite = null;
    this.hasSearchedUsers = false;
  }

  onInviteSearchInput(): void {
    this.selectedUserToInvite = null; // Reset selection on new search
    this.userSearchSubject.next({ query: this.inviteSearchQuery, projectId: Number(this.selectedProjectId) });
  }

  selectUserToInvite(user: UserSearch): void {
    this.selectedUserToInvite = user;
    this.inviteSearchQuery = user.name;
    this.inviteSearchResults = [];
    this.hasSearchedUsers = false;
  }

  submitInvite(): void {
    if (!this.selectedUserToInvite || !this.selectedProjectId) {
      this.inviteError = 'Debes seleccionar un proyecto y un usuario.';
      return;
    }

    this.isInviting = true;
    this.inviteError = '';
    this.inviteSuccessMessage = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<{ message: string }>(`/api/projects/${this.selectedProjectId}/invite`, {
      userId: this.selectedUserToInvite.id
    }, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isInviting = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.inviteSuccessMessage = res.message;
          this.selectedUserToInvite = null;
          this.inviteSearchQuery = '';
          this.fetchDashboardData(); // Refresh to update team members count if applicable
        },
        error: (err) => {
          this.inviteError = err.error?.message ?? 'Error al invitar al usuario.';
        }
      });
  }
}
