// Servicio para guardar y leer datos en el localStorage del navegador
// Aquí guardamos el código del paciente entre sesiones
import { Injectable } from '@angular/core';

// Clave con la que guardamos el código en el localStorage
const KEY_CODIGO = 'sc_codigo';

@Injectable({
  providedIn: 'root',
})
export class StorageService {

  // Devuelve el código guardado, o null si no hay ninguno
  getCodigo(): string | null {
    return localStorage.getItem(KEY_CODIGO);
  }

  // Guarda el código del paciente en mayúsculas
  setCodigo(codigo: string): void {
    localStorage.setItem(KEY_CODIGO, codigo.toUpperCase());
  }

  // Borra el código guardado (al cerrar sesión o cambiar de paciente)
  clearCodigo(): void {
    localStorage.removeItem(KEY_CODIGO);
  }
}
