// Componente shell que contiene las tres pestañas principales de la app
// Funciona como contenedor: renderiza la tab-bar y el outlet de cada pestaña
import { Component } from '@angular/core';
import {
  IonTabs,
  IonTabBar,
  IonTabButton,
  IonIcon,
  IonLabel,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { todayOutline, calendarOutline, personOutline } from 'ionicons/icons';

@Component({
  selector: 'app-tabs',
  templateUrl: './tabs.component.html',
  styleUrls: ['./tabs.component.scss'],
  standalone: true,
  // Importamos los componentes de Ionic que usa la plantilla
  imports: [IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel],
})
export class TabsComponent {

  constructor() {
    // Registramos los iconos que vamos a usar en la barra de pestañas
    addIcons({ todayOutline, calendarOutline, personOutline });
  }
}
