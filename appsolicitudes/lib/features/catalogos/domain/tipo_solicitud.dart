/// Entrada del catálogo `tipos_solicitud`: mantenimiento, soporte
/// tecnológico, infraestructura, equipamiento y otros.
class TipoSolicitud {
  const TipoSolicitud({
    required this.id,
    required this.nombre,
    this.descripcion,
  });

  final int id;
  final String nombre;
  final String? descripcion;

  factory TipoSolicitud.desdeJson(Map<String, dynamic> json) {
    return TipoSolicitud(
      id: json['id'] as int,
      nombre: json['nombre'] as String? ?? '',
      descripcion: json['descripcion'] as String?,
    );
  }

  /// Los nombres llegan en minúscula y con guion bajo desde la base de datos.
  String get etiqueta {
    if (nombre.isEmpty) return '';
    final palabras = nombre.replaceAll('_', ' ');
    return palabras[0].toUpperCase() + palabras.substring(1);
  }

  @override
  bool operator ==(Object other) =>
      other is TipoSolicitud && other.id == id;

  @override
  int get hashCode => id.hashCode;
}
