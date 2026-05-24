// Servicio para gestionar el modo oscuro (dark mode) de la aplicación
// Guarda la preferencia en localStorage para que se recuerde entre sesiones
import { Injectable } from '@angular/core';

// Clave que usamos para guardar la preferencia en localStorage
const STORAGE_KEY = 'sc_dark_mode';

@Injectable({
  providedIn: 'root',
})
export class ThemeService {

  // Variable que indica si el modo oscuro está activo
  isDark = false;

  // Se llama al arrancar la app para restaurar la preferencia guardada
  initTheme(): void {
    const guardado = localStorage.getItem(STORAGE_KEY);
    this.isDark = guardado === 'true';
    this.aplicar();
  }

  // Cambia el tema y guarda la nueva preferencia
  setDark(valor: boolean): void {
    this.isDark = valor;
    localStorage.setItem(STORAGE_KEY, String(valor));
    this.aplicar();
  }

  // Aplica o quita la clase 'dark' del body para activar los estilos del tema oscuro
  private aplicar(): void {
    document.body.classList.toggle('dark', this.isDark);
  }
}
