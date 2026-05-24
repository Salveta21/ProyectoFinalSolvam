// Servicio de estado global: guarda el cliente y la dieta cargada
// Así los tres tabs comparten los mismos datos sin tener que pedir a la API cada vez
import { inject, Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { ApiService } from './api.service';
import { StorageService } from './storage.service';
import { Cliente, Dieta } from '../common/interfaces';

@Injectable({
  providedIn: 'root',
})
export class DietaStateService {

  // Inyectamos los servicios que necesitamos
  private readonly api: ApiService = inject(ApiService);
  private readonly storage: StorageService = inject(StorageService);

  // BehaviorSubject: guarda el último valor emitido y lo da a nuevos suscriptores
  private _cliente = new BehaviorSubject<Cliente | null>(null);
  private _dieta   = new BehaviorSubject<Dieta | null>(null);
  private _loading = new BehaviorSubject<boolean>(false);
  private _error   = new BehaviorSubject<string | null>(null);

  // Observables públicos que los componentes pueden suscribir
  cliente$ = this._cliente.asObservable();
  dieta$   = this._dieta.asObservable();
  loading$ = this._loading.asObservable();
  error$   = this._error.asObservable();

  // Carga los datos del paciente desde la API y los guarda en el estado
  async load(codigo: string): Promise<boolean> {
    this._loading.next(true);
    this._error.next(null);
    try {
      // Pedimos cliente y dieta en paralelo con toPromise()
      const cliente = await this.api.getCliente(codigo).toPromise();
      const dieta   = await this.api.getDieta(codigo).toPromise();
      this._cliente.next(cliente!);
      this._dieta.next(dieta!);
      this.storage.setCodigo(codigo);
      return true;
    } catch (e: any) {
      // Si falla, mostramos el mensaje de error que devuelve la API
      const msg = e?.error?.error ?? 'Error al conectar con el servidor';
      this._error.next(msg);
      return false;
    } finally {
      // Siempre quitamos el spinner, haya ido bien o mal
      this._loading.next(false);
    }
  }

  // Limpia el estado al cerrar sesión o cambiar de paciente
  reset(): void {
    this._cliente.next(null);
    this._dieta.next(null);
    this._error.next(null);
    this.storage.clearCodigo();
  }

  // Getters síncronos para acceder al valor actual sin suscribirse
  get cliente() { return this._cliente.value; }
  get dieta()   { return this._dieta.value; }
}
