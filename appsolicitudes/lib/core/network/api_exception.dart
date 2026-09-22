import 'package:dio/dio.dart';

/// Error de la API ya traducido a un mensaje que se puede mostrar al usuario.
///
/// La aplicación nunca muestra un `DioException` crudo: toda la capa de
/// presentación trabaja con esta clase.
class ApiException implements Exception {
  const ApiException({
    required this.mensaje,
    this.codigoHttp,
    this.erroresPorCampo = const {},
  });

  /// Texto en español, listo para un SnackBar o una pantalla de error.
  final String mensaje;

  final int? codigoHttp;

  /// Errores de validación devueltos por Laravel en una respuesta 422, con el
  /// nombre del campo como clave. Permite pintar el error bajo cada input.
  final Map<String, String> erroresPorCampo;

  /// La sesión dejó de ser válida y hay que volver a iniciarla.
  bool get esSesionExpirada => codigoHttp == 401;

  /// Construye la excepción a partir del error que emite Dio.
  factory ApiException.desdeDio(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException(
          mensaje: 'El servidor tardó demasiado en responder. '
              'Revisa tu conexión e inténtalo de nuevo.',
        );
      case DioExceptionType.connectionError:
        return const ApiException(
          mensaje: 'No se pudo conectar con el servidor. '
              'Verifica que tengas conexión a internet.',
        );
      case DioExceptionType.cancel:
        return const ApiException(mensaje: 'La petición fue cancelada.');
      case DioExceptionType.badCertificate:
        return const ApiException(
          mensaje: 'El certificado del servidor no es válido.',
        );
      case DioExceptionType.badResponse:
      case DioExceptionType.unknown:
        return ApiException._desdeRespuesta(error.response);
    }
  }

  factory ApiException._desdeRespuesta(Response<dynamic>? respuesta) {
    final codigo = respuesta?.statusCode;
    final cuerpo = respuesta?.data;
    final mensajeServidor = _extraerMensaje(cuerpo);

    switch (codigo) {
      case 401:
        return ApiException(
          mensaje: mensajeServidor ??
              'Tu sesión expiró. Vuelve a iniciar sesión.',
          codigoHttp: codigo,
        );
      case 403:
        return ApiException(
          mensaje: mensajeServidor ??
              'No tienes permiso para realizar esta acción.',
          codigoHttp: codigo,
        );
      case 404:
        return ApiException(
          mensaje: mensajeServidor ?? 'No se encontró lo que buscabas.',
          codigoHttp: codigo,
        );
      case 422:
        final errores = _extraerErroresPorCampo(cuerpo);
        return ApiException(
          // Con un solo campo inválido conviene mostrarlo directo; con varios,
          // el detalle va bajo cada input y arriba solo un resumen.
          mensaje: errores.length == 1
              ? errores.values.first
              : mensajeServidor ?? 'Revisa los datos ingresados.',
          codigoHttp: codigo,
          erroresPorCampo: errores,
        );
      case 429:
        return ApiException(
          mensaje: 'Demasiados intentos. Espera un momento.',
          codigoHttp: codigo,
        );
      default:
        if (codigo != null && codigo >= 500) {
          return ApiException(
            mensaje: 'El servidor tuvo un problema. Inténtalo más tarde.',
            codigoHttp: codigo,
          );
        }
        return ApiException(
          mensaje: mensajeServidor ?? 'Ocurrió un error inesperado.',
          codigoHttp: codigo,
        );
    }
  }

  /// Laravel devuelve el texto del error en la clave `message`.
  static String? _extraerMensaje(dynamic cuerpo) {
    if (cuerpo is Map && cuerpo['message'] is String) {
      final mensaje = cuerpo['message'] as String;
      return mensaje.isEmpty ? null : mensaje;
    }
    return null;
  }

  /// Aplana `errors: {campo: [mensaje, ...]}` a `{campo: primer mensaje}`.
  static Map<String, String> _extraerErroresPorCampo(dynamic cuerpo) {
    if (cuerpo is! Map || cuerpo['errors'] is! Map) return const {};

    final errores = <String, String>{};
    (cuerpo['errors'] as Map).forEach((campo, mensajes) {
      if (mensajes is List && mensajes.isNotEmpty) {
        errores[campo.toString()] = mensajes.first.toString();
      } else if (mensajes is String) {
        errores[campo.toString()] = mensajes;
      }
    });
    return errores;
  }

  @override
  String toString() => 'ApiException($codigoHttp): $mensaje';
}
