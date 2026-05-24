// Interfaces de la aplicación (equivalente a models/ del proyecto anterior)
// Aquí definimos la forma de los datos que nos devuelve la API REST

// Datos básicos del cliente/paciente
export interface Cliente {
  id: number;
  nombre: string;
  apellidos: string;
  sexo: 'M' | 'F';
  peso_kg: number;
  altura_cm: number;
  objetivo: string;
}

// Un ingrediente individual con su categoría
export interface Ingrediente {
  id: number;
  nombre: string;
  categoria: string;
}

// Una combinación de plato principal + complemento opcional dentro de una toma
export interface Combinacion {
  dia: number;
  toma: string;
  principal: string;
  cantidad_principal: string;
  complemento: string | null;
  cantidad_complemento: string;
}

// Contenido de una toma en un día concreto (dieta semanal)
export interface TomaDia {
  combinaciones: Combinacion[];
  sueltos: string[];
}

// Franja horaria de una toma (ej. desayuno: 07:00-09:00)
export interface FranjaHoraria {
  inicio: string; // formato "HH:MM"
  fin: string;    // formato "HH:MM"
}

// Dieta de tipo general: mismos alimentos todos los días
export interface DietaGeneral {
  tipo: 'general';
  id: number;
  nombre: string;
  fecha: string;
  nota_huevos: string;
  condimentos: string;
  agua?: string;
  complementos?: string;
  ingredientes: Ingrediente[];
  no_permitidos: string[];
  franjas_horarias?: Record<string, FranjaHoraria>;
}

// Dieta semanal: cada día tiene sus propias tomas con ingredientes distintos
export interface DietaSemanal {
  tipo: 'semanal';
  id: number;
  nombre: string;
  fecha: string;
  tomas_activas: string[] | null;
  agua?: string;
  complementos?: string;
  // dias: clave = número de día (1=Lunes...7=Domingo), valor = mapa toma → contenido
  dias: { [dia: number]: { [toma: string]: TomaDia } };
  no_permitidos: string[];
  franjas_horarias?: Record<string, FranjaHoraria>;
}

// Tipo unión: una dieta puede ser general o semanal
export type Dieta = DietaGeneral | DietaSemanal;
