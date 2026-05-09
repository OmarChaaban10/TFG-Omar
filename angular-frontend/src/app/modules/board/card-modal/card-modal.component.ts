import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, DestroyRef, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';
import type { Assignee, BoardColumn, Card } from '../board.component';

type CardPriority = 'low' | 'medium' | 'high';

@Component({
  selector: 'app-card-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './card-modal.component.html',
})
export class CardModalComponent implements OnChanges {
  @Input({ required: true }) projectId!: number;
  @Input({ required: true }) columns: BoardColumn[] = [];
  @Input({ required: true }) assignees: Assignee[] = [];
  @Input() card: Card | null = null;
  @Input() initialColumnId = 0;

  @Output() closed = new EventEmitter<void>();
  @Output() saved = new EventEmitter<void>();

  title = '';
  description = '';
  columnId = 0;
  assigneeId: number | null = null;
  priority: CardPriority = 'medium';
  dueDate = '';
  labelsText = '';
  isSaving = false;
  error = '';

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  get isEditing(): boolean {
    return this.card !== null;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['card'] || changes['initialColumnId']) {
      this.resetForm();
    }
  }

  close(): void {
    this.closed.emit();
  }

  save(): void {
    const trimmedTitle = this.title.trim();
    if (!trimmedTitle) {
      this.error = 'El titulo de la tarea es obligatorio.';
      return;
    }

    if (!this.columnId) {
      this.error = 'Selecciona una columna.';
      return;
    }

    this.isSaving = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
    const payload = {
      title: trimmedTitle,
      description: this.description.trim(),
      columnId: this.columnId,
      assigneeId: this.assigneeId || null,
      priority: this.priority,
      dueDate: this.dueDate || null,
      labels: this.labelsText
        .split(',')
        .map(label => label.trim())
        .filter(Boolean),
    };

    const request = this.isEditing && this.card
      ? this.http.put(`/api/projects/${this.projectId}/board/cards/${this.card.id}`, payload, {
          headers: { Authorization: `Bearer ${token}` },
        })
      : this.http.post(`/api/projects/${this.projectId}/board/columns/${this.columnId}/cards`, payload, {
          headers: { Authorization: `Bearer ${token}` },
        });

    request
      .pipe(
        finalize(() => { this.isSaving = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.saved.emit();
        },
        error: (err) => {
          this.error = err.error?.message ?? 'No se pudo guardar la tarea.';
        },
      });
  }

  private resetForm(): void {
    this.error = '';
    this.title = this.card?.title ?? '';
    this.description = this.card?.description ?? '';
    this.columnId = this.initialColumnId || this.columns[0]?.id || 0;
    this.assigneeId = this.card?.assignee?.id ?? null;
    this.priority = this.normalizePriority(this.card?.priority);
    this.dueDate = this.card?.dueDate ? this.card.dueDate.slice(0, 10) : '';
    this.labelsText = this.card?.labels.map(label => label.name).join(', ') ?? '';
  }

  private normalizePriority(priority: string | null | undefined): CardPriority {
    if (priority === 'low' || priority === 'medium' || priority === 'high') {
      return priority;
    }

    return 'medium';
  }
}
