// Servicio para comunicarse con la API REST del backend
// Usamos HttpClient de Angular para hacer las peticiones GET
import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Cliente, Dieta } from '../common/interfaces';

@Injectable({
  providedIn: 'root', // disponible en toda la app sin declararlo en ningún módulo
})
export class ApiService {

  // Inyectamos HttpClient con inject() en lugar de ponerlo en el constructor
  private readonly http: HttpClient = inject(HttpClient);

  // URL base de la API, cambia según el entorno (development / production)
  private readonly baseUrl: string = environment.apiUrl;

  // Obtiene los datos del cliente a partir de su código de 8 caracteres
  getCliente(codigo: string): Observable<Cliente> {
    return this.http.get<Cliente>(`${this.baseUrl}/api/cliente?codigo=${codigo}`);
  }

  // Obtiene la dieta activa del paciente (puede ser general o semanal)
  getDieta(codigo: string): Observable<Dieta> {
    return this.http.get<Dieta>(`${this.baseUrl}/api/dieta?codigo=${codigo}`);
  }

  // Obtiene la configuración general de la clínica (nombre de empresa, etc.)
  getConfig(): Observable<{ empresa: string }> {
    return this.http.get<{ empresa: string }>(`${this.baseUrl}/api/config`);
  }
}
