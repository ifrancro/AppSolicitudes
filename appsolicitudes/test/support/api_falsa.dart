import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Respuesta preparada para una ruta concreta.
class RespuestaFalsa {
  const RespuestaFalsa(this.cuerpo, {this.codigo = 200});

  final Object cuerpo;
  final int codigo;
}

/// Adaptador de Dio que responde desde un mapa en memoria en lugar de salir a
/// la red.
///
/// Permite probar el stack real de la aplicación —interceptores, repositorios
/// y providers— sin levantar el backend. La clave del mapa es la ruta, sin la
/// URL base.
class ApiFalsa implements HttpClientAdapter {
  ApiFalsa(this.respuestas);

  final Map<String, RespuestaFalsa> respuestas;

  /// Rutas efectivamente solicitadas, en orden. Sirve para verificar que una
  /// pantalla llamó al endpoint que le corresponde.
  final List<String> rutasLlamadas = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions opciones,
    Stream<Uint8List>? cuerpoPeticion,
    Future<void>? cancelacion,
  ) async {
    final ruta = opciones.path;
    rutasLlamadas.add(ruta);

    final preparada = respuestas[ruta];
    if (preparada == null) {
      // Una ruta sin preparar es un fallo de la prueba, no un 404 real.
      return ResponseBody.fromString(
        jsonEncode({'message': 'Ruta no preparada en la prueba: $ruta'}),
        404,
        headers: _cabecerasJson,
      );
    }

    return ResponseBody.fromString(
      jsonEncode(preparada.cuerpo),
      preparada.codigo,
      headers: _cabecerasJson,
    );
  }

  @override
  void close({bool force = false}) {}

  static const Map<String, List<String>> _cabecerasJson = {
    Headers.contentTypeHeader: ['application/json'],
  };
}

/// Usuario de ejemplo con rol estudiante.
const Map<String, dynamic> usuarioDeEjemplo = {
  'id': 7,
  'nombre': 'Ana Lucía Pérez',
  'email': 'ana.perez@universidad.edu',
  'rol': {'id': 1, 'nombre': 'estudiante'},
};

/// Catálogo de tipos tal como lo define el esquema.
const List<Map<String, dynamic>> tiposDeEjemplo = [
  {'id': 1, 'nombre': 'mantenimiento', 'descripcion': 'Reparaciones'},
  {'id': 2, 'nombre': 'soporte_tecnologico', 'descripcion': 'Equipos y red'},
  {'id': 3, 'nombre': 'infraestructura', 'descripcion': null},
];
