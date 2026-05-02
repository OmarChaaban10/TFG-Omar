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

  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.fetchDashboardData();
    this.initSearchListener();
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
}
