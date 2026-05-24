// Archivo de rutas principal de la aplicación (sustituye a app-routing.module.ts)
// Usamos loadComponent en lugar de loadChildren porque los componentes son standalone
import { Routes } from '@angular/router';
import { AuthGuard } from './guards/auth.guard';

export const routes: Routes = [
  // Ruta por defecto: redirige a las tabs
  {
    path: '',
    redirectTo: 'tabs/hoy',
    pathMatch: 'full',
  },
  // Pantalla de introducción de código del paciente (sin guard, es pública)
  {
    path: 'codigo',
    loadComponent: () => import('./pages/codigo/codigo.page').then(m => m.CodigoPage),
  },
  // Zona privada con tabs — requiere haber introducido el código
  {
    path: 'tabs',
    loadComponent: () => import('./component/tabs/tabs.component').then(m => m.TabsComponent),
    canActivate: [AuthGuard],
    // Rutas hijas: cada tab es una página standalone independiente
    children: [
      {
        path: 'hoy',
        loadComponent: () => import('./pages/hoy/hoy.page').then(m => m.HoyPage),
      },
      {
        path: 'semana',
        loadComponent: () => import('./pages/semana/semana.page').then(m => m.SemanaPage),
      },
      {
        path: 'perfil',
        loadComponent: () => import('./pages/perfil/perfil.page').then(m => m.PerfilPage),
      },
      // Si entran en /tabs sin más, los mandamos a /tabs/hoy
      {
        path: '',
        redirectTo: 'hoy',
        pathMatch: 'full',
      },
    ],
  },
];
