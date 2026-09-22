/// Roles definidos en la tabla `roles` del esquema.
enum Rol {
  estudiante,
  personalAdministrativo,
  responsable,
  administrador,
  desconocido;

  /// Traduce el nombre que envía el backend al valor del enum.
  static Rol desdeNombre(String? nombre) {
    switch (nombre) {
      case 'estudiante':
        return Rol.estudiante;
      case 'personal_administrativo':
        return Rol.personalAdministrativo;
      case 'responsable':
        return Rol.responsable;
      case 'administrador':
        return Rol.administrador;
      default:
        return Rol.desconocido;
    }
  }

  String get etiqueta {
    switch (this) {
      case Rol.estudiante:
        return 'Estudiante';
      case Rol.personalAdministrativo:
        return 'Personal administrativo';
      case Rol.responsable:
        return 'Responsable';
      case Rol.administrador:
        return 'Administrador';
      case Rol.desconocido:
        return 'Sin rol asignado';
    }
  }
}

/// Usuario autenticado, tal como lo devuelve `GET /auth/me`.
class Usuario {
  const Usuario({
    required this.id,
    required this.nombre,
    required this.email,
    required this.rol,
  });

  final int id;
  final String nombre;
  final String email;
  final Rol rol;

  /// Acepta el rol como objeto anidado (`rol: {nombre: ...}`) o como cadena
  /// plana (`rol: "estudiante"`), porque el backend todavía puede cambiar la
  /// forma de la respuesta.
  factory Usuario.desdeJson(Map<String, dynamic> json) {
    final rolCrudo = json['rol'];
    final nombreRol = rolCrudo is Map
        ? rolCrudo['nombre'] as String?
        : rolCrudo as String?;

    return Usuario(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      email: json['email'] as String? ?? '',
      rol: Rol.desdeNombre(nombreRol),
    );
  }

  /// Iniciales para el avatar, a partir del nombre y el primer apellido.
  String get iniciales {
    final partes = nombre.trim().split(RegExp(r'\s+'))
      ..removeWhere((parte) => parte.isEmpty);
    if (partes.isEmpty) return '?';
    if (partes.length == 1) return partes.first[0].toUpperCase();
    return (partes.first[0] + partes[1][0]).toUpperCase();
  }
}
