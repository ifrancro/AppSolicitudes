import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Guarda el token de sesión en el almacenamiento cifrado del sistema.
///
/// En Android el paquete cifra con AES-GCM respaldado por el KeyStore y en iOS
/// usa el llavero, de modo que el token no queda en texto plano en el
/// dispositivo. Se usan las opciones por defecto, que ya aplican ese cifrado.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? almacenamiento})
      : _almacenamiento = almacenamiento ?? const FlutterSecureStorage();

  static const String _clave = 'token_sesion';

  final FlutterSecureStorage _almacenamiento;

  /// Copia en memoria para no golpear el llavero en cada petición HTTP.
  String? _cache;

  Future<String?> leer() async {
    return _cache ??= await _almacenamiento.read(key: _clave);
  }

  Future<void> guardar(String token) async {
    _cache = token;
    await _almacenamiento.write(key: _clave, value: token);
  }

  Future<void> borrar() async {
    _cache = null;
    await _almacenamiento.delete(key: _clave);
  }
}
