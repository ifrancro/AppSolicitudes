import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import '../domain/usuario.dart';

/// Acceso a los tres endpoints de autenticación.
///
/// Es el único lugar del cliente que conoce la forma del JSON de sesión. Si el
/// backend cambia el nombre del campo del token o anida el usuario de otra
/// manera, solo se ajusta esta clase.
class AuthRepository {
  const AuthRepository({
    required ApiClient api,
    required TokenStorage tokenStorage,
  })  : _api = api,
        _tokenStorage = tokenStorage;

  final ApiClient _api;
  final TokenStorage _tokenStorage;

  /// Nombres posibles del campo que trae el token.
  ///
  /// Pendiente de confirmar con el backend: Laravel Sanctum suele devolverlo
  /// como `token`, pero `access_token` es igual de común. Se aceptan ambos
  /// para no bloquear el desarrollo del cliente.
  static const List<String> _clavesDeToken = [
    'token',
    'access_token',
    'plainTextToken',
  ];

  /// Inicia sesión, guarda el token y devuelve el usuario autenticado.
  Future<Usuario> iniciarSesion({
    required String email,
    required String password,
  }) async {
    final respuesta = await _api.post(
      '/auth/login',
      cuerpo: {'email': email, 'password': password},
    );

    final datos = _comoMapa(respuesta);
    final token = _extraerToken(datos);
    await _tokenStorage.guardar(token);

    return Usuario.desdeJson(_extraerUsuario(datos));
  }

  /// Recupera el usuario de la sesión vigente.
  Future<Usuario> consultarPerfil() async {
    final respuesta = await _api.get('/auth/me');
    return Usuario.desdeJson(_extraerUsuario(_comoMapa(respuesta)));
  }

  /// Cierra sesión en el servidor y borra el token local.
  ///
  /// El token se borra aunque la petición falle: si no hay red, la sesión debe
  /// terminar igual en el dispositivo.
  Future<void> cerrarSesion() async {
    try {
      await _api.post('/auth/logout');
    } finally {
      await _tokenStorage.borrar();
    }
  }

  static Map<String, dynamic> _comoMapa(dynamic respuesta) {
    if (respuesta is Map<String, dynamic>) return respuesta;
    throw const FormatException('La respuesta de sesión no es un objeto JSON.');
  }

  static String _extraerToken(Map<String, dynamic> datos) {
    // El token puede venir en la raíz o dentro de `data`.
    final fuentes = <Map<String, dynamic>>[
      datos,
      if (datos['data'] is Map<String, dynamic>)
        datos['data'] as Map<String, dynamic>,
    ];

    for (final fuente in fuentes) {
      for (final clave in _clavesDeToken) {
        final valor = fuente[clave];
        if (valor is String && valor.isNotEmpty) return valor;
      }
    }

    throw const FormatException(
      'La respuesta de login no trae el token de sesión.',
    );
  }

  static Map<String, dynamic> _extraerUsuario(Map<String, dynamic> datos) {
    for (final clave in ['usuario', 'user', 'data']) {
      final valor = datos[clave];
      if (valor is Map<String, dynamic> && valor['id'] != null) return valor;
    }
    // El endpoint puede devolver el usuario en la raíz.
    if (datos['id'] != null) return datos;

    throw const FormatException(
      'La respuesta no contiene los datos del usuario.',
    );
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    api: ref.watch(apiClientProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
  );
});
