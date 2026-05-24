// Componente raíz de la aplicación
// Al ser standalone importamos directamente IonApp e IonRouterOutlet
import { Component, inject } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { ThemeService } from './services/theme.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: true,
  // En standalone declaramos aquí los componentes de Ionic que usa la plantilla
  imports: [IonApp, IonRouterOutlet],
})
export class AppComponent {
  // Inyectamos el servicio del tema con inject() en lugar de constructor
  private readonly themeService: ThemeService = inject(ThemeService);

  constructor() {
    // Al arrancar la app restauramos el tema guardado (dark/light)
    this.themeService.initTheme();
  }
}
