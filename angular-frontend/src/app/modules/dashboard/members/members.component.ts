import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { finalize } from 'rxjs/operators';
import { InviteMemberModalComponent } from '../invite-member-modal/invite-member-modal.component';

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
  selector: 'app-members',
  standalone: true,
  imports: [CommonModule, FormsModule, InviteMemberModalComponent],
  templateUrl: './members.component.html',
})
export class MembersComponent implements OnInit {
  projects: ProjectFull[] = [];
  selectedProjectId = 0;
  currentUserId: number | null = null;
  isLoadingMembers = false;
  membersError = '';
  actionError = '';
  showInviteModal = false;
  memberPendingRemoval: ProjectMemberDetail | null = null;
  isRemovingMember = false;
  updatingRoleMemberIds = new Set<number>();

  private readonly http = inject(HttpClient);

  ngOnInit(): void {
    this.fetchProjects();
  }

  get selectedProject(): ProjectFull | null {
    return this.projects.find(project => project.id === this.selectedProjectId) ?? null;
  }

  get canManageSelectedProject(): boolean {
    const project = this.selectedProject;
    if (!project) return false;

    return project.myRole === 'Admin';
  }

  get projectMembers(): ProjectMemberDetail[] {
    return this.selectedProject?.members ?? [];
  }

  fetchProjects(): void {
    this.isLoadingMembers = true;
    this.membersError = '';
    this.actionError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ projects: ProjectFull[]; currentUserId: number }>('/api/projects/all', {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(finalize(() => { this.isLoadingMembers = false; }))
      .subscribe({
        next: (res) => {
          this.projects = res.projects;
          this.currentUserId = res.currentUserId;
          if (!this.selectedProjectId || !this.projects.some(project => project.id === this.selectedProjectId)) {
            this.selectedProjectId = this.projects[0]?.id ?? 0;
          }
        },
        error: () => {
          this.membersError = 'No se pudieron cargar los miembros.';
        }
      });
  }

  openInviteModal(): void {
    if (!this.canManageSelectedProject) return;

    this.showInviteModal = true;
  }

  closeInviteModal(): void {
    this.showInviteModal = false;
  }

  handleMemberInvited(): void {
    this.fetchProjects();
  }

  openRemoveConfirm(member: ProjectMemberDetail): void {
    if (!this.canEditMember(member)) return;

    this.memberPendingRemoval = member;
    this.actionError = '';
  }

  closeRemoveConfirm(): void {
    if (this.isRemovingMember) return;

    this.memberPendingRemoval = null;
  }

  removePendingMember(): void {
    const project = this.selectedProject;
    const member = this.memberPendingRemoval;
    if (!project || !member || this.isRemovingMember) return;

    this.isRemovingMember = true;
    this.actionError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.delete(`/api/projects/${project.id}/members/${member.id}`, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(finalize(() => { this.isRemovingMember = false; }))
      .subscribe({
        next: () => {
          this.memberPendingRemoval = null;
          this.fetchProjects();
        },
        error: (err) => {
          this.actionError = err.error?.message ?? 'No se pudo eliminar al miembro.';
        }
      });
  }

  updateMemberRole(member: ProjectMemberDetail, role: string): void {
    const project = this.selectedProject;
    if (!project || !this.canEditMember(member) || member.role === role) return;

    this.updatingRoleMemberIds.add(member.id);
    this.actionError = '';

    const previousRole = member.role;
    member.role = role;

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.put(`/api/projects/${project.id}/members/${member.id}/role`,
      { role },
      { headers: { Authorization: `Bearer ${token}` } }
    )
      .pipe(finalize(() => { this.updatingRoleMemberIds.delete(member.id); }))
      .subscribe({
        next: () => {
          this.fetchProjects();
        },
        error: (err) => {
          member.role = previousRole;
          this.actionError = err.error?.message ?? 'No se pudo actualizar el rol.';
        }
      });
  }

  canEditMember(member: ProjectMemberDetail): boolean {
    return this.canManageSelectedProject
      && member.role !== 'owner'
      && member.id !== this.currentUserId;
  }

  isUpdatingRole(memberId: number): boolean {
    return this.updatingRoleMemberIds.has(memberId);
  }

  getInitials(name: string): string {
    return name
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('');
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
}
