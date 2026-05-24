// Página "Semana": muestra los 7 días con selector de día y todas las tomas
// El paciente puede navegar entre días y ver qué come en cada toma
import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader, IonToolbar, IonTitle, IonContent,
  IonRefresher, IonRefresherContent,
  IonSpinner,
  IonCard, IonCardHeader, IonCardTitle, IonCardSubtitle, IonCardContent,
  IonChip,
} from '@ionic/angular/standalone';
import { DietaStateService } from '../../services/dieta-state.service';
import { StorageService } from '../../services/storage.service';
import { DietaGeneral, DietaSemanal } from '../../common/interfaces';

// Etiquetas legibles para cada toma del día
const TOMA_LABELS: Record<string, string> = {
  desayuno:     'Desayuno',
  media_manana: 'Media mañana',
  almuerzo:     'Almuerzo',
  comida:       'Comida',
  merienda:     'Merienda',
  cena:         'Cena',
};

// Lista con todas las tomas en orden (la usamos si tomas_activas es null)
const TODAS_TOMAS = ['desayuno','media_manana','almuerzo','comida','merienda','cena'];

// Nombres de los días de la semana (1=Lunes...7=Domingo)
const DIA_LABELS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

@Component({
  selector: 'app-semana',
  templateUrl: './semana.page.html',
  styleUrls: ['./semana.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonHeader, IonToolbar, IonTitle, IonContent,
    IonRefresher, IonRefresherContent,
    IonSpinner,
    IonCard, IonCardHeader, IonCardTitle, IonCardSubtitle, IonCardContent,
    IonChip,
  ],
})
export class SemanaPage implements OnInit, OnDestroy {

  // Inyectamos servicios con inject()
  private readonly state: DietaStateService = inject(DietaStateService);
  private readonly storage: StorageService = inject(StorageService);

  loading: boolean = false;
  tipo: 'general' | 'semanal' | null = null;
  dietaGeneral: DietaGeneral | null = null;
  semanal: DietaSemanal | null = null;

  // Array de números del 1 al 7 para iterar los días
  dias: number[] = [1, 2, 3, 4, 5, 6, 7];
  diaLabels: string[] = DIA_LABELS;
  tomasActivas: string[] = [];
  tomaLabels: Record<string, string> = TOMA_LABELS;
  // Día seleccionado en el selector (por defecto hoy)
  diaSeleccionado: number = 1;

  // Día actual de la semana y toma activa ahora mismo (para el badge "Ahora")
  hoyDia: number = 1;
  tomaActualKey: string = '';

  // Franjas horarias para detectar la toma activa
  private franjas: Array<{ toma: string; desde: number; hasta: number }> = [];
  private tomaTimer: any = null;

  ngOnInit(): void {
    // Calculamos qué día es hoy y lo seleccionamos por defecto
    const jsDay = new Date().getDay();
    this.hoyDia = jsDay === 0 ? 7 : jsDay;
    this.diaSeleccionado = this.hoyDia;
    this.cargar();
  }

  async ionViewWillEnter(): Promise<void> {
    if (!this.state.dieta) await this.cargar();
    this.renderizar();
    this.iniciarTimer();
  }

  ionViewWillLeave(): void {
    this.detenerTimer();
  }

  ngOnDestroy(): void {
    this.detenerTimer();
  }

  async cargar(): Promise<void> {
    const codigo = this.storage.getCodigo();
    if (!codigo) return;
    this.loading = true;
    await this.state.load(codigo);
    this.loading = false;
    this.renderizar();
  }

  // Rellena las variables del componente con los datos de la dieta
  private renderizar(): void {
    const dieta = this.state.dieta;
    if (!dieta) return;
    this.tipo = dieta.tipo;

    // Guardamos las franjas horarias para el badge "Ahora"
    if (dieta.franjas_horarias) {
      this.franjas = Object.entries(dieta.franjas_horarias).map(([toma, f]) => ({
        toma,
        desde: this.horaAMinutos(f.inicio),
        hasta: this.horaAMinutos(f.fin),
      }));
    }

    if (dieta.tipo === 'general') {
      this.dietaGeneral = dieta as DietaGeneral;
    }

    if (dieta.tipo === 'semanal') {
      this.semanal = dieta as DietaSemanal;
      this.tomasActivas = this.semanal.tomas_activas ?? TODAS_TOMAS;
      this.detectarTomaActual();
    }
  }

  // Actualiza qué toma corresponde al momento actual
  private detectarTomaActual(): void {
    const minutos = new Date().getHours() * 60 + new Date().getMinutes();
    const coincidencia = this.franjas.find(t =>
      this.tomasActivas.includes(t.toma) && minutos >= t.desde && minutos < t.hasta
    );
    this.tomaActualKey = coincidencia?.toma ?? '';
  }

  private horaAMinutos(hm: string): number {
    const [h, m] = hm.split(':').map(Number);
    return h * 60 + m;
  }

  private iniciarTimer(): void {
    this.detenerTimer();
    this.tomaTimer = setInterval(() => this.detectarTomaActual(), 60_000);
  }

  private detenerTimer(): void {
    if (this.tomaTimer) {
      clearInterval(this.tomaTimer);
      this.tomaTimer = null;
    }
  }

  // Cambia el día seleccionado al pulsar en el selector
  seleccionarDia(dia: number): void {
    this.diaSeleccionado = dia;
  }

  // Devuelve los datos de una toma concreta de un día concreto
  getTomaDia(dia: number, toma: string) {
    return this.semanal?.dias[dia]?.[toma] ?? { combinaciones: [], sueltos: [] };
  }

  // Comprueba si una toma tiene algún dato para mostrar
  tieneDatos(dia: number, toma: string): boolean {
    const t = this.getTomaDia(dia, toma);
    return t.combinaciones.length > 0 || t.sueltos.length > 0;
  }

  // Comprueba si un día completo está vacío (sin alimentos en ninguna toma)
  diaVacio(dia: number): boolean {
    return this.tomasActivas.every(t => !this.tieneDatos(dia, t));
  }

  async refrescar(event: any): Promise<void> {
    await this.cargar();
    event.target.complete();
  }
}
