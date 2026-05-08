import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';
import { DragDropModule, CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ThemeToggleComponent } from '../shared/theme-toggle/theme-toggle.component';

export interface Label {
  id: number;
  name: string;
  color: string;
}

export interface Assignee {
  id: number;
  name: string;
  avatarUrl: string | null;
}

export interface Card {
  id: number;
  title: string;
  description: string;
  priority: string | null;
  position: number;
  dueDate: string | null;
  assignee: Assignee | null;
  labels: Label[];
}

export interface BoardColumn {
  id: number;
  name: string;
  color: string;
  position: number;
  cards: Card[];
}

export interface Board {
  id: number;
  name: string;
  columns: BoardColumn[];
}

@Component({
  selector: 'app-board',
  standalone: true,
  imports: [CommonModule, DragDropModule, FormsModule, ThemeToggleComponent],
  templateUrl: './board.component.html'
})
export class BoardComponent implements OnInit {
  projectId = 0;
  projectName = '';
  board: Board | null = null;
  isLoading = false;
  error = '';
  searchQuery = '';
  
  // Para los id de las listas conectadas de drag & drop
  connectedLists: string[] = [];

  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private loadedProjectId: number | null = null;

  ngOnInit(): void {
    this.route.paramMap
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        const routeProjectId = Number(params.get('id'));

        if (Number.isInteger(routeProjectId) && routeProjectId > 0) {
          const projectName = this.route.snapshot.queryParamMap.get('name') ?? params.get('name') ?? '';
          this.loadProject(routeProjectId, projectName);
          return;
        }

        this.error = 'Proyecto no válido.';
      });
  }

  get boardTitle(): string {
    return this.projectName || this.board?.name || 'Tablero Kanban';
  }

  private loadProject(projectId: number, projectName: string): void {
    if (!Number.isInteger(projectId) || projectId <= 0) {
      this.error = 'Proyecto no válido.';
      this.board = null;
      return;
    }

    if (this.loadedProjectId !== projectId) {
      this.board = null;
      this.searchQuery = '';
    }

    this.projectId = projectId;
    this.projectName = projectName || this.projectName;
    this.loadedProjectId = projectId;
    this.fetchBoard();
  }

  fetchBoard(): void {
    this.isLoading = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<{ board: Board }>(`/api/projects/${this.projectId}/board`, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isLoading = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.board = res.board;
          if (!this.projectName) {
            this.projectName = res.board.name;
          }
          // Actualizamos los IDs de conexión (col-1, col-2...)
          if (this.board && this.board.columns) {
            this.connectedLists = this.board.columns.map(c => `col-${c.id}`);
          }
        },
        error: (err) => {
          this.error = err.error?.message || 'Error al cargar el tablero.';
        }
      });
  }

  backToDashboard(): void {
    this.router.navigate(['/dashboard']);
  }

  logout(): void {
    localStorage.removeItem('jwt_token');
    sessionStorage.removeItem('jwt_token');
    this.router.navigate(['/']);
  }

  drop(event: CdkDragDrop<Card[]>): void {
    if (event.previousContainer === event.container) {
      // Movimiento dentro de la misma columna
      moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
      this.persistCardMove(event.container.id, event.item.data.id, event.currentIndex);
    } else {
      // Movimiento a otra columna
      transferArrayItem(
        event.previousContainer.data,
        event.container.data,
        event.previousIndex,
        event.currentIndex,
      );
      this.persistCardMove(event.container.id, event.item.data.id, event.currentIndex);
    }
  }

  private persistCardMove(columnContainerId: string, cardId: number, newPosition: number): void {
    const columnId = parseInt(columnContainerId.replace('col-', ''), 10);
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.put(`/api/projects/${this.projectId}/board/cards/${cardId}/move`, 
      { columnId, position: newPosition },
      { headers: { Authorization: `Bearer ${token}` } }
    )
    .pipe(takeUntilDestroyed(this.destroyRef))
    .subscribe({
      next: () => {
        // Todo bien, se guardó la nueva posición
      },
      error: (err) => {
        console.error('Error al mover la tarjeta', err);
        // Idealmente aquí se podría revertir la UI o mostrar un toast
      }
    });
  }

  getPriorityColor(priority: string | null): string {
    switch(priority) {
      case 'high':
      case 'urgent':
        return 'bg-red-500 text-white';
      case 'medium':
        return 'bg-amber-500 text-white';
      case 'low':
        return 'bg-emerald-500 text-white';
      default:
        return 'bg-slate-600 text-slate-200';
    }
  }
  
  getPriorityLabel(priority: string | null): string {
    switch(priority) {
      case 'high': return 'Alta';
      case 'urgent': return 'Urgente';
      case 'medium': return 'Media';
      case 'low': return 'Baja';
      default: return 'Sin Prioridad';
    }
  }

  get filteredColumns(): BoardColumn[] {
    if (!this.board) return [];
    
    const query = this.searchQuery.toLowerCase().trim();
    if (!query) return this.board.columns;
    
    // Clonación profunda básica para no alterar la matriz real con el filtro
    return this.board.columns.map(col => ({
      ...col,
      cards: col.cards.filter(c => 
        c.title.toLowerCase().includes(query) || 
        c.assignee?.name.toLowerCase().includes(query)
      )
    }));
  }
}
