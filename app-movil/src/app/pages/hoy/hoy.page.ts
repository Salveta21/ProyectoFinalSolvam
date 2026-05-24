// Página "Hoy": muestra la toma activa en el momento actual
// Detecta automáticamente qué toma toca según la hora y las franjas horarias de la BD
import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader, IonToolbar, IonTitle, IonContent,
  IonRefresher, IonRefresherContent,
  IonSpinner, IonIcon, IonButton,
  IonCard, IonCardHeader, IonCardTitle, IonCardContent,
  IonChip,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { alertCircleOutline } from 'ionicons/icons';
import { DietaStateService } from '../../services/dieta-state.service';
import { StorageService } from '../../services/storage.service';
import { DietaGeneral, DietaSemanal, TomaDia } from '../../common/interfaces';

// Etiquetas legibles para cada toma
const TOMA_LABELS: Record<string, string> = {
  desayuno:     'Desayuno',
  media_manana: 'Media mañana',
  almuerzo:     'Almuerzo',
  comida:       'Comida',
  merienda:     'Merienda',
  cena:         'Cena',
};

// Emojis decorativos para cada toma
const TOMA_ICONOS: Record<string, string> = {
  desayuno: '☀️', media_manana: '☕', almuerzo: '🥗',
  comida: '🍽️', merienda: '🍎', cena: '🌙',
};

// Nombres de los días de la semana (índice 0 sin usar, 1=Lunes...7=Domingo)
const DIA_LABELS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

@Component({
  selector: 'app-hoy',
  templateUrl: './hoy.page.html',
  styleUrls: ['./hoy.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonHeader, IonToolbar, IonTitle, IonContent,
    IonRefresher, IonRefresherContent,
    IonSpinner, IonIcon, IonButton,
    IonCard, IonCardHeader, IonCardTitle, IonCardContent,
    IonChip,
  ],
})
export class HoyPage implements OnInit, OnDestroy {

  // Inyectamos los servicios con inject()
  private readonly state: DietaStateService = inject(DietaStateService);
  private readonly storage: StorageService = inject(StorageService);

  // Variables de estado de la carga
  loading: boolean = false;
  error: string | null = null;
  clienteNombre: string = '';

  // Variables para dieta general
  dietaGeneral: DietaGeneral | null = null;
  categorias: string[] = [];
  porCategoria: Record<string, string[]> = {};

  // Variables para dieta semanal
  diaActual: number = 1;
  diaLabel: string = '';
  tomaActual: string = '';
  tomaLabel: string = '';
  tomaData: TomaDia | null = null;
  tomasActivas: string[] = [];

  // Tipo de dieta cargada ('general' o 'semanal')
  tipo: 'general' | 'semanal' | null = null;

  // Agua y suplementos (aplican a ambos tipos de dieta)
  dietaAgua: string = '';
  dietaComplementos: string = '';

  // Exponemos los mapas de etiquetas e iconos a la plantilla
  readonly tomaLabels = TOMA_LABELS;
  readonly tomaIconos = TOMA_ICONOS;

  // Franjas horarias para detectar la toma activa (cargadas desde la API)
  private franjas: Array<{ toma: string; desde: number; hasta: number }> = [];
  // Timer para actualizar la toma activa cada minuto
  private tomaTimer: any = null;

  ngOnInit(): void {
    // Registramos los iconos de ionicons que usamos en la plantilla
    addIcons({ alertCircleOutline });
    this.cargar();
  }

  // Se ejecuta cada vez que se navega a esta tab (Ionic lifecycle)
  async ionViewWillEnter(): Promise<void> {
    if (!this.state.dieta) await this.cargar();
    this.iniciarTimer();
  }

  // Paramos el timer al salir para no desperdiciar recursos
  ionViewWillLeave(): void {
    this.detenerTimer();
  }

  ngOnDestroy(): void {
    this.detenerTimer();
  }

  // Carga los datos del paciente desde el estado global (o los pide a la API si no hay)
  async cargar(): Promise<void> {
    const codigo = this.storage.getCodigo();
    if (!codigo) return;
    this.loading = true;
    const ok = await this.state.load(codigo);
    this.loading = false;
    if (!ok) {
      this.error = 'No se pudo cargar la dieta';
      return;
    }
    this.renderizar();
  }

  // Rellena las variables del componente con los datos cargados
  private renderizar(): void {
    const cliente = this.state.cliente;
    const dieta   = this.state.dieta;
    if (!cliente || !dieta) return;

    this.clienteNombre      = cliente.nombre;
    this.tipo               = dieta.tipo;
    this.dietaAgua          = dieta.agua ?? '';
    this.dietaComplementos  = dieta.complementos ?? '';

    // Convertimos las franjas horarias a minutos desde medianoche para facilitar comparaciones
    if (dieta.franjas_horarias) {
      this.franjas = Object.entries(dieta.franjas_horarias).map(([toma, f]) => ({
        toma,
        desde: this.horaAMinutos(f.inicio),
        hasta: this.horaAMinutos(f.fin),
      }));
    }

    // Procesamos según el tipo de dieta
    if (dieta.tipo === 'general') {
      this.dietaGeneral = dieta as DietaGeneral;
      // Agrupamos los ingredientes por categoría para mostrarlos en tarjetas
      const mapa: Record<string, string[]> = {};
      for (const ing of this.dietaGeneral.ingredientes) {
        if (!mapa[ing.categoria]) mapa[ing.categoria] = [];
        mapa[ing.categoria].push(ing.nombre);
      }
      this.porCategoria = mapa;
      this.categorias   = Object.keys(mapa);
    }

    if (dieta.tipo === 'semanal') {
      const semanal = dieta as DietaSemanal;
      const todasTomas = ['desayuno','media_manana','almuerzo','comida','merienda','cena'];
      // Si tomas_activas es null, todas están activas
      this.tomasActivas = semanal.tomas_activas ?? todasTomas;

      // Calculamos el día actual (JS usa 0=Domingo, nosotros 1=Lunes...7=Domingo)
      const jsDay = new Date().getDay();
      this.diaActual = jsDay === 0 ? 7 : jsDay;
      this.diaLabel  = DIA_LABELS[this.diaActual];

      this.tomaActual = this.detectarTomaActual();
      this.tomaLabel  = TOMA_LABELS[this.tomaActual] ?? this.tomaActual;
      // Si no hay datos para esta toma, devolvemos arrays vacíos
      this.tomaData   = semanal.dias[this.diaActual]?.[this.tomaActual] ?? { combinaciones: [], sueltos: [] };
    }
  }

  // Devuelve la clave de la toma que corresponde a la hora actual según las franjas
  private detectarTomaActual(): string {
    const minutos = new Date().getHours() * 60 + new Date().getMinutes();
    const coincidencia = this.franjas.find(t =>
      this.tomasActivas.includes(t.toma) && minutos >= t.desde && minutos < t.hasta
    );
    // Si no hay coincidencia, mostramos la primera toma activa
    return coincidencia?.toma ?? this.tomasActivas[0] ?? 'desayuno';
  }

  // Convierte "HH:MM" a minutos desde medianoche (ej. "08:30" → 510)
  private horaAMinutos(hm: string): number {
    const [h, m] = hm.split(':').map(Number);
    return h * 60 + m;
  }

  // Inicia un intervalo que actualiza la toma activa cada minuto
  private iniciarTimer(): void {
    this.detenerTimer();
    this.tomaTimer = setInterval(() => this.tickToma(), 60_000);
  }

  private detenerTimer(): void {
    if (this.tomaTimer) {
      clearInterval(this.tomaTimer);
      this.tomaTimer = null;
    }
  }

  // Se ejecuta cada minuto: actualiza la toma activa si ha cambiado
  private tickToma(): void {
    if (this.tipo !== 'semanal' || !this.state.dieta) return;
    const semanal = this.state.dieta as DietaSemanal;

    // Comprobamos si hemos pasado a otro día
    const jsDay = new Date().getDay();
    const nuevoDia = jsDay === 0 ? 7 : jsDay;
    if (nuevoDia !== this.diaActual) {
      this.diaActual = nuevoDia;
      this.diaLabel  = DIA_LABELS[this.diaActual];
    }

    // Comprobamos si ha cambiado la toma activa
    const nuevaToma = this.detectarTomaActual();
    if (nuevaToma !== this.tomaActual) {
      this.tomaActual = nuevaToma;
      this.tomaLabel  = TOMA_LABELS[this.tomaActual] ?? this.tomaActual;
      this.tomaData   = semanal.dias[this.diaActual]?.[this.tomaActual] ?? { combinaciones: [], sueltos: [] };
    }
  }

  // Saludo según la hora del día
  get saludo(): string {
    const h = new Date().getHours();
    if (h >= 6 && h < 14) return 'Buenos días';
    if (h >= 14 && h < 21) return 'Buenas tardes';
    return 'Buenas noches';
  }

  // Devuelve la fecha de hoy formateada en español (ej. "Lunes, 19 de mayo")
  get fechaHoy(): string {
    const s = new Date().toLocaleDateString('es-ES', {
      weekday: 'long', day: 'numeric', month: 'long'
    });
    // Ponemos la primera letra en mayúscula
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  // Devuelve la etiqueta con emoji para cada categoría de ingrediente
  getCatLabel(cat: string): string {
    const emojis: Record<string, string> = {
      verdura: '🍅 Verduras', carne: '🍗 Carne', pescado: '🐟 Pescado',
      fruta: '🍏 Fruta', condimento: '🧂 Condimentos',
    };
    return emojis[cat] ?? cat;
  }

  // Pull-to-refresh: recarga los datos al bajar la pantalla
  async refrescar(event: any): Promise<void> {
    await this.cargar();
    event.target.complete();
  }
}
