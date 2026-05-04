import { Component, OnInit, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, finalize, switchMap } from 'rxjs/operators';

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

@Component({
  selector: 'app-invite-member-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './invite-member-modal.component.html',
})
export class InviteMemberModalComponent implements OnInit {
  @Output() closed = new EventEmitter<void>();
  @Output() memberInvited = new EventEmitter<void>();

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
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.initUserSearchListener();
    this.fetchParticipatingProjects();
  }

  private fetchParticipatingProjects(): void {
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

  close(): void {
    this.closed.emit();
  }

  onProjectChange(): void {
    this.inviteSearchQuery = '';
    this.inviteSearchResults = [];
    this.selectedUserToInvite = null;
    this.hasSearchedUsers = false;
  }

  onInviteSearchInput(): void {
    this.selectedUserToInvite = null;
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
          this.memberInvited.emit();
        },
        error: (err) => {
          this.inviteError = err.error?.message ?? 'Error al invitar al usuario.';
        }
      });
  }
}
