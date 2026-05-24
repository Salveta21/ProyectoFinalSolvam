// Página "Perfil": muestra los datos del paciente y permite cambiar ajustes
import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import {
  IonHeader, IonToolbar, IonTitle, IonContent,
  IonList, IonItem, IonLabel, IonIcon,
  IonSpinner, IonToggle, IonAlert,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  barbellOutline, resizeOutline, flagOutline,
  documentTextOutline, calendarOutline, listOutline,
  moonOutline, swapHorizontalOutline,
} from 'ionicons/icons';
import { DietaStateService } from '../../services/dieta-state.service';
import { ThemeService } from '../../services/theme.service';
import { Cliente } from '../../common/interfaces';

@Component({
  selector: 'app-perfil',
  templateUrl: './perfil.page.html',
  styleUrls: ['./perfil.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonHeader, IonToolbar, IonTitle, IonContent,
    IonList, IonItem, IonLabel, IonIcon,
    IonSpinner, IonToggle, IonAlert,
  ],
})
export class PerfilPage implements OnInit {

  private readonly state: DietaStateService = inject(DietaStateService);
  private readonly router: Router = inject(Router);
  public readonly theme: ThemeService = inject(ThemeService);

  cliente: Cliente | null = null;
  dietaNombre: string = '';
  dietaFecha: string = '';
  dietaTipo: string = '';

  showCambiarAlert = false;
  readonly alertButtons = [
    { text: 'Cancelar', role: 'cancel' },
    { text: 'Sí, cambiar', handler: () => this.cambiarCodigo() },
  ];

  ngOnInit(): void {
    // Registramos los iconos que vamos a usar en la plantilla
    addIcons({
      barbellOutline, resizeOutline, flagOutline,
      documentTextOutline, calendarOutline, listOutline,
      moonOutline, swapHorizontalOutline,
    });
    this.cargarPerfil();
  }

  // Se llama cada vez que se navega a esta tab
  ionViewWillEnter(): void {
    this.cargarPerfil();
  }

  // Carga los datos del cliente y la dieta desde el estado global
  private cargarPerfil(): void {
    this.cliente    = this.state.cliente;
    const dieta     = this.state.dieta;
    this.dietaNombre = dieta?.nombre ?? '';
    this.dietaFecha  = dieta?.fecha  ?? '';
    this.dietaTipo   = dieta?.tipo   ?? '';
  }

  // Formatea una fecha "YYYY-MM-DD" a "1 de mayo de 2026"
  formatearFecha(fecha: string): string {
    if (!fecha) return '';
    // Añadimos T12:00:00 para evitar problemas de zona horaria (sin la hora lo redondea al día anterior)
    const str = fecha.includes('T') ? fecha : fecha + 'T12:00:00';
    const d = new Date(str);
    if (isNaN(d.getTime())) return fecha;
    return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  confirmarCambiarCodigo(): void {
    this.showCambiarAlert = true;
  }

  // Limpia el estado y redirige a la pantalla de código
  private cambiarCodigo(): void {
    this.state.reset();
    this.router.navigate(['/codigo'], { replaceUrl: true });
  }
}
