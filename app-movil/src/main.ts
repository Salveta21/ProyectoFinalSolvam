// Punto de entrada de la aplicación
// Usamos bootstrapApplication en lugar de NgModule (modo standalone, como en Ejercicio_07)
import { bootstrapApplication } from '@angular/platform-browser';
import { RouteReuseStrategy, provideRouter, withPreloading, PreloadAllModules } from '@angular/router';
import { IonicRouteStrategy, provideIonicAngular } from '@ionic/angular/standalone';
import { provideHttpClient, withFetch } from '@angular/common/http';

import { routes } from './app/app.routes';
import { AppComponent } from './app/app.component';

bootstrapApplication(AppComponent, {
  providers: [
    // Le decimos a Ionic que use su estrategia de rutas
    { provide: RouteReuseStrategy, useClass: IonicRouteStrategy },
    // Inicializamos Ionic en modo standalone
    provideIonicAngular(),
    // Registramos las rutas de la aplicación con precarga
    provideRouter(routes, withPreloading(PreloadAllModules)),
    // Habilitamos las peticiones HTTP con la API Fetch moderna
    provideHttpClient(withFetch()),
  ],
});
