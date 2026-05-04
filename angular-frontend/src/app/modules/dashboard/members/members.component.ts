import { Component, OnInit, DestroyRef, inject } from '@angular/core';
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
  color: string | null;
  members: ProjectMemberDetail[];
}

interface SharedProject {
  id: number;
  name: string;
  color: string | null;
  roleInProject: string;
}

interface MemberFull {
  id: number;
  name: string;
  email: string;
  avatarUrl: string | null;
  sharedProjects: SharedProject[];
}

@Component({
  selector: 'app-members-view',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './members-view.component.html',
})
export class MembersViewComponent implements OnInit {
  allMembers: MemberFull[] = [];
  isLoadingMembers = false;
  membersError = '';
  expandedMemberIds: Set<number> = new Set();

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.fetchAllMembers();
  }

  fetchAllMembers(): void {
    this.isLoadingMembers = true;
    this.membersError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    // Usamos el endpoint de proyectos para extraer los miembros con los que compartimos proyectos
    this.http.get<{ projects: ProjectFull[] }>('/api/projects/all', {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isLoadingMembers = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.processMembers(res.projects);
        },
        error: () => {
          this.membersError = 'No se pudieron cargar los miembros.';
        }
      });
  }

  processMembers(projects: ProjectFull[]): void {
    const membersMap = new Map<number, MemberFull>();

    for (const project of projects) {
      for (const member of project.members) {
        if (!membersMap.has(member.id)) {
          membersMap.set(member.id, {
            id: member.id,
            name: member.name,
            email: member.email,
            avatarUrl: member.avatarUrl,
            sharedProjects: []
          });
        }
        membersMap.get(member.id)!.sharedProjects.push({
          id: project.id,
          name: project.name,
          color: project.color,
          roleInProject: member.role
        });
      }
    }

    // Ordenar miembros por nombre alfabéticamente
    this.allMembers = Array.from(membersMap.values()).sort((a, b) => a.name.localeCompare(b.name));
  }

  toggleMemberExpand(memberId: number): void {
    if (this.expandedMemberIds.has(memberId)) {
      this.expandedMemberIds.delete(memberId);
    } else {
      this.expandedMemberIds.add(memberId);
    }
  }

  isMemberExpanded(memberId: number): boolean {
    return this.expandedMemberIds.has(memberId);
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
