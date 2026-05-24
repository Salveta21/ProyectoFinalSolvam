// Pantalla de entrada de código del paciente
// Solo se muestra la primera vez; después el código queda guardado en localStorage
import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonContent,
  IonInput,
  IonButton,
  IonSpinner,
} from '@ionic/angular/standalone';
import { DietaStateService } from '../../services/dieta-state.service';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-codigo',
  templateUrl: './codigo.page.html',
  styleUrls: ['./codigo.page.scss'],
  standalone: true,
  // Importamos los componentes de Ionic y los módulos de Angular que usamos
  imports: [CommonModule, FormsModule, IonContent, IonInput, IonButton, IonSpinner],
})
export class CodigoPage implements OnInit {

  // Inyectamos los servicios con inject() en lugar de constructor
  private readonly state: DietaStateService = inject(DietaStateService);
  private readonly router: Router = inject(Router);
  private readonly api: ApiService = inject(ApiService);

  // Variables que controlan el formulario
  codigo: string = '';
  loading: boolean = false;
  error: string = '';
  // Nombre de la empresa que se muestra en la pantalla de bienvenida
  nombreEmpresa: string = '';

  ngOnInit(): void {
    // Al cargar la página pedimos el nombre de la clínica a la API
    this.api.getConfig().subscribe({
      next: (config) => this.nombreEmpresa = config.empresa,
      // Si falla, usamos un nombre por defecto
      error: () => this.nombreEmpresa = 'Sans Clinique',
    });
  }

  // Método que se ejecuta al pulsar el botón "Entrar"
  async entrar(): Promise<void> {
    const code = this.codigo.trim().toUpperCase();

    // Validamos que el código tenga exactamente 8 caracteres
    if (code.length !== 8) {
      this.error = 'El código debe tener 8 caracteres';
      return;
    }

    this.loading = true;
    this.error = '';

    // Intentamos cargar los datos del paciente con ese código
    const ok = await this.state.load(code);
    this.loading = false;

    if (ok) {
      // Si todo fue bien, navegamos a la pantalla principal (tab Hoy)
      this.router.navigate(['/tabs/hoy'], { replaceUrl: true });
    } else {
      // Si no, mostramos el error que devolvió el servicio
      this.state.error$.subscribe(e => this.error = e ?? 'Código no válido').unsubscribe();
    }
  }
}
