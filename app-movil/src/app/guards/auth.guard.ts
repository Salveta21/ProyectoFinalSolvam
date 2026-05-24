// Guard de autenticación: protege las rutas privadas (las tabs)
// Si el paciente no ha introducido su código, lo mandamos a la pantalla de código
import { inject, Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { StorageService } from '../services/storage.service';

@Injectable({
  providedIn: 'root',
})
export class AuthGuard implements CanActivate {

  // Inyectamos los servicios que necesitamos con inject()
  private readonly storage: StorageService = inject(StorageService);
  private readonly router: Router = inject(Router);

  canActivate(): boolean {
    // Si hay código guardado, dejamos pasar
    if (this.storage.getCodigo()) {
      return true;
    }
    // Si no hay código, redirigimos a la pantalla de entrada
    this.router.navigate(['/codigo']);
    return false;
  }
}
