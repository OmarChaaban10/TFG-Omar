import { Component, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';

@Component({
  selector: 'app-create-project-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './create-project-modal.component.html',
})
export class CreateProjectModalComponent {
  @Output() closed = new EventEmitter<void>();
  @Output() projectCreated = new EventEmitter<void>();

  newProjectName = '';
  newProjectDescription = '';
  newProjectColor = '';
  isCreating = false;
  createError = '';

  readonly colorPresets = [
    '#f97316', '#3b82f6', '#10b981', '#a855f7',
    '#ef4444', '#eab308', '#06b6d4', '#ec4899',
  ];

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  close(): void {
    this.closed.emit();
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
          this.projectCreated.emit();
        },
        error: (err) => {
          this.createError = err.error?.message ?? 'Error al crear el proyecto.';
        }
      });
  }
}
