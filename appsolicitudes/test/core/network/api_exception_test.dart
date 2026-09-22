import 'package:appsolicitudes/core/network/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Construye el error que emitiría Dio ante una respuesta con [codigo].
DioException _errorDeRespuesta(int codigo, {Object? cuerpo}) {
  final peticion = RequestOptions(path: '/solicitudes');
  return DioException(
    requestOptions: peticion,
    type: DioExceptionType.badResponse,
    response: Response<dynamic>(
      requestOptions: peticion,
      statusCode: codigo,
      data: cuerpo,
    ),
  );
}

void main() {
  group('ApiException.desdeDio', () {
    test('traduce el tiempo de espera agotado', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/solicitudes'),
        type: DioExceptionType.connectionTimeout,
      );

      final excepcion = ApiException.desdeDio(error);

      expect(excepcion.mensaje, contains('tardó demasiado'));
      expect(excepcion.codigoHttp, isNull);
    });

    test('traduce la falta de conexion', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/solicitudes'),
        type: DioExceptionType.connectionError,
      );

      expect(
        ApiException.desdeDio(error).mensaje,
        contains('No se pudo conectar'),
      );
    });

    test('marca la sesion expirada en un 401', () {
      final excepcion = ApiException.desdeDio(_errorDeRespuesta(401));

      expect(excepcion.esSesionExpirada, isTrue);
      expect(excepcion.mensaje, contains('sesión expiró'));
    });

    test('un 403 no se confunde con sesion expirada', () {
      final excepcion = ApiException.desdeDio(_errorDeRespuesta(403));

      expect(excepcion.esSesionExpirada, isFalse);
      expect(excepcion.mensaje, contains('No tienes permiso'));
    });

    test('aplana los errores de validacion de un 422', () {
      final excepcion = ApiException.desdeDio(
        _errorDeRespuesta(422, cuerpo: {
          'message': 'Los datos son invalidos.',
          'errors': {
            'titulo': ['El título es obligatorio.'],
            'tipo_id': ['El tipo seleccionado no existe.'],
          },
        }),
      );

      expect(excepcion.erroresPorCampo, {
        'titulo': 'El título es obligatorio.',
        'tipo_id': 'El tipo seleccionado no existe.',
      });
      // Con varios campos invalidos el mensaje general viene del servidor.
      expect(excepcion.mensaje, 'Los datos son invalidos.');
    });

    test('con un solo campo invalido usa ese mensaje como principal', () {
      final excepcion = ApiException.desdeDio(
        _errorDeRespuesta(422, cuerpo: {
          'message': 'Los datos son invalidos.',
          'errors': {
            'email': ['Las credenciales no coinciden.'],
          },
        }),
      );

      expect(excepcion.mensaje, 'Las credenciales no coinciden.');
    });

    test('prefiere el mensaje del servidor cuando existe', () {
      final excepcion = ApiException.desdeDio(
        _errorDeRespuesta(404, cuerpo: {'message': 'Solicitud no encontrada.'}),
      );

      expect(excepcion.mensaje, 'Solicitud no encontrada.');
    });

    test('oculta el detalle de un error 500', () {
      final excepcion = ApiException.desdeDio(
        _errorDeRespuesta(500, cuerpo: {
          'message': 'SQLSTATE[42P01]: relacion inexistente',
        }),
      );

      // El detalle tecnico no se le muestra al estudiante.
      expect(excepcion.mensaje, 'El servidor tuvo un problema. Inténtalo más tarde.');
      expect(excepcion.codigoHttp, 500);
    });

    test('soporta un cuerpo que no es JSON', () {
      final excepcion = ApiException.desdeDio(
        _errorDeRespuesta(404, cuerpo: '<html>Not Found</html>'),
      );

      expect(excepcion.mensaje, 'No se encontró lo que buscabas.');
      expect(excepcion.erroresPorCampo, isEmpty);
    });
  });
}
