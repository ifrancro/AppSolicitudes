import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

/// Se dispara cuando el servidor responde 401, es decir, cuando la sesión dejó
/// de ser válida. La capa de autenticación se suscribe para cerrar sesión.
typedef AlExpirarSesion = void Function();

/// Punto único de acceso a la API REST.
///
/// Envuelve Dio y expone métodos que devuelven el cuerpo ya decodificado o
/// lanzan [ApiException]. Ningún repositorio debe manejar `DioException`.
class ApiClient {
  ApiClient({
    required TokenStorage tokenStorage,
    Dio? dio,
  })  : _tokenStorage = tokenStorage,
        _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = Env.baseUrl
      ..connectTimeout = Env.timeout
      ..receiveTimeout = Env.timeout
      ..sendTimeout = Env.timeout
      ..headers.addAll({
        // Sin esta cabecera Laravel responde con HTML en lugar de JSON.
        'Accept': 'application/json',
      });

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: _adjuntarToken,
        onError: _manejarError,
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;

  AlExpirarSesion? _alExpirarSesion;

  /// Registra el callback que se ejecuta cuando la sesión expira.
  void escucharExpiracionDeSesion(AlExpirarSesion callback) {
    _alExpirarSesion = callback;
  }

  Future<void> _adjuntarToken(
    RequestOptions opciones,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokenStorage.leer();
    if (token != null) {
      opciones.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(opciones);
  }

  Future<void> _manejarError(
    DioException error,
    ErrorInterceptorHandler handler,
  ) async {
    if (error.response?.statusCode == 401) {
      // El token ya no sirve: se borra antes de avisar, para que la siguiente
      // petición no vuelva a enviarlo.
      await _tokenStorage.borrar();
      _alExpirarSesion?.call();
    }
    handler.next(error);
  }

  Future<dynamic> get(
    String ruta, {
    Map<String, dynamic>? parametros,
  }) {
    return _ejecutar(() => _dio.get<dynamic>(ruta, queryParameters: parametros));
  }

  Future<dynamic> post(String ruta, {Object? cuerpo}) {
    return _ejecutar(() => _dio.post<dynamic>(ruta, data: cuerpo));
  }

  Future<dynamic> patch(String ruta, {Object? cuerpo}) {
    return _ejecutar(() => _dio.patch<dynamic>(ruta, data: cuerpo));
  }

  /// Envía un formulario multipart. Se usa para subir evidencias.
  ///
  /// [alEnviar] recibe los bytes enviados y el total, para mostrar progreso.
  Future<dynamic> postMultipart(
    String ruta, {
    required FormData formulario,
    void Function(int enviados, int total)? alEnviar,
  }) {
    return _ejecutar(
      () => _dio.post<dynamic>(
        ruta,
        data: formulario,
        onSendProgress: alEnviar,
      ),
    );
  }

  /// Descarga un archivo como bytes, conservando la cabecera de autorización.
  Future<List<int>> descargarBytes(String ruta) async {
    final respuesta = await _ejecutarRespuesta(
      () => _dio.get<List<int>>(
        ruta,
        options: Options(responseType: ResponseType.bytes),
      ),
    );
    return respuesta.data ?? const [];
  }

  Future<dynamic> _ejecutar(
    Future<Response<dynamic>> Function() peticion,
  ) async {
    final respuesta = await _ejecutarRespuesta(peticion);
    return respuesta.data;
  }

  Future<Response<T>> _ejecutarRespuesta<T>(
    Future<Response<T>> Function() peticion,
  ) async {
    try {
      return await peticion();
    } on DioException catch (error) {
      throw ApiException.desdeDio(error);
    }
  }
}

/// Instancia única de [TokenStorage] para toda la aplicación.
final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

/// Instancia única de [ApiClient]. Los repositorios la obtienen de aquí.
final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(tokenStorage: ref.watch(tokenStorageProvider));
});
