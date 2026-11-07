<?php
return [
    'app_name'        => 'ICEANOTES',
    'logout'          => 'Cerrar sesión',
    'admin_badge'     => 'admin',

    // Navegación
    'nav_home'        => 'Inicio',
    'nav_ecoles'      => 'Escuelas',
    'nav_filieres'    => 'Carreras',
    'nav_promotions'  => 'Promociones',
    'nav_modules'     => 'Módulos',
    'nav_professeurs' => 'Profesores',
    'nav_etudiants'   => 'Estudiantes',
    'nav_notes'       => 'Notas',
    'nav_note_types'  => 'Tipos de nota',

    // Tipos de usuario
    'type_admin'       => 'Administrador',
    'type_superviseur' => 'Supervisor',
    'type_user'        => 'Usuario',
    'type_guest'       => 'Invitado',
    'protected_badge'  => 'Protegido',

    // Genéricos
    'filter'          => 'Filtrar',
    'reset'           => 'Reiniciar',
    'search'          => 'Buscar…',
    'per_page'        => ':n / pág',
    'actions'         => 'Acciones',
    'edit'            => 'Editar',
    'delete'          => 'Eliminar',
    'activate'        => 'Activar',
    'deactivate'      => 'Desactivar',
    'active'          => 'Activo',
    'inactive'        => 'Inactivo',
    'save'            => 'Guardar',
    'cancel'          => 'Cancelar',
    'add'             => 'Añadir',
    'confirm_delete'  => '¿Confirmar eliminación? Esta acción es irreversible.',
    'confirm_delete_2'=> '¿Está seguro? Haga clic de nuevo para confirmar.',

    // Login
    'login_title'          => 'Iniciar sesión',
    'login_heading'        => 'INICIAR SESIÓN',
    'login_subheading'     => 'Introduzca sus credenciales para acceder a su espacio personal.',
    'login_identifier'     => 'Usuario',
    'login_password'       => 'Contraseña',
    'login_remember'       => 'Recordarme',
    'login_submit'         => 'Iniciar sesión',
    'login_show_password'  => 'Mostrar contraseña',
    'login_hide_password'  => 'Ocultar contraseña',

    // Marca
    'app_full_name'   => 'INSTITUT CATHOLIQUE EUROPÉEN DES AMÉRIQUES',
    'app_brand'       => 'ICEA NOTES',
    'app_tagline'     => 'Su plataforma unificada para la calificación, el seguimiento y las trayectorias de los estudiantes.',
    'app_copyright'   => 'ICEANOTES. Todos los derechos reservados.',

    // Inicio
    'accueil_title'     => 'Todas las notas',
    'accueil_title_prof'=> 'Mis notas',
    'notes_total'       => ':n nota(s)',
    'add_note'          => '+ Añadir una nota',

    // Escuelas (badges + CRUD)
    'ecole'                => 'Escuela',
    'ecoles'               => 'Escuelas',
    'ecoles_title'         => 'Escuelas',
    'ecoles_count'         => ':n escuela(s)',
    'ecole_nom'            => 'Nombre de la escuela',
    'ecole_description'    => 'Descripción (opcional)',
    'ecole_color'          => 'Color',
    'ecole_add'            => 'Añadir una escuela',
    'ecole_filieres_count' => 'Carreras',
    'ecole_delete_blocked' => 'No se puede: hay carreras vinculadas a esta escuela.',

    // Carreras
    'filieres_title'    => 'Carreras',
    'filieres_count'    => ':n carrera(s)',
    'filiere_nom'       => 'Nombre de la carrera',
    'section'           => 'Sección',
    'promotions_count'  => 'Promociones',

    // Promociones
    'promotions_title'    => 'Promociones',
    'promotions_count'    => ':n promoción(es)',
    'promotion_nom'       => 'Nivel (L1, L2, L3, Master…)',
    'promotion_promo'     => 'Nombre de la promo (ej.: Lilo y Stitch — opcional)',
    'promotion_annee'     => 'Año (ej.: 2026-2027)',
    'promotion_filiere'   => 'Carrera',
    'promotion_ordre'     => 'Orden',
    'promotion_label'     => 'Promoción',
    'promo_label'         => 'Promo',
    'annee'               => 'Año',
    'filiere'             => 'Carrera',
    'diplome'             => 'Nivel',
    'promo'               => 'Promo',
    'modules_count'       => 'Módulos',
    'etudiants_count'     => 'Estudiantes',

    // Profesores
    'professeurs_title' => 'Profesores',
    'professeurs_count' => ':n profesor(es)',
    'prof_prenom'       => 'Nombre',
    'prof_nom'          => 'Apellido',
    'prof_email'        => 'nombre.apellido@icea-edu.fr',
    'prof_password'     => 'Contraseña (por defecto: icea)',
    'add_professeur'    => 'Añadir un profesor',

    // Módulos
    'modules_title'       => 'Módulos',
    'modules_title_prof'  => 'Módulos — mis módulos',
    'modules_page_count'  => ':n módulo(s)',
    'module_nom'          => 'Nombre del módulo',
    'niveau'              => 'Nivel',
    'professeur'          => 'Profesor',
    'add_module'          => 'Añadir módulo',

    // Estudiantes
    'etudiants_title'      => 'Estudiantes',
    'etudiants_page_count' => ':n estudiante(s)',
    'etudiant_prenom'      => 'Nombre',
    'etudiant_nom'         => 'Apellido',
    'add_etudiant'         => 'Añadir un estudiante',

    // Notas
    'notes_title'       => 'Notas',
    'notes_title_prof'  => 'Notas — mis módulos',
    'notes_page_count'  => ':n nota(s)',
    'etudiant'          => 'Estudiante',
    'module'            => 'Módulo',
    'type'              => 'Tipo',
    'note'              => 'Nota',
    'date'              => 'Fecha',
    'add_note_btn'      => 'Añadir nota',
    'note_slash'        => 'Nota /20',
    'error_note_range'  => 'La nota debe estar entre 0 y 20.',
    'error_note_exists' => 'Esta nota ya existe.',
    'error_champs'      => 'Campos inválidos.',
    'error_prof_module' => 'Solo puede calificar en sus módulos.',
    'error_filiere'     => 'El módulo y el estudiante no están en la misma carrera.',

    // Tipos de nota
    'note_types_title'       => 'Tipos de nota',
    'note_types_count'       => ':n tipo(s)',
    'note_type_nom'          => 'Nombre (ej.: CC1, Examen…)',
    'note_type_sur'          => 'Sobre',
    'note_type_ordre'        => 'Orden',
    'note_type_coef'         => 'Coef',
    'notes_count'            => 'Notas',
    'moyenne'                => 'Promedio',
    'no_students_or_modules' => 'Ningún estudiante o módulo coincidente.',
    'no_module_for_level'    => 'Ningún módulo para este nivel',

    // Estados
    'ok_add'            => 'Añadido.',
    'ok_edit'           => 'Actualizado.',
    'ok_delete'         => 'Eliminado.',
    'ok_toggle'         => 'Estado actualizado.',
    'error_generic'     => 'Se produjo un error.',
];