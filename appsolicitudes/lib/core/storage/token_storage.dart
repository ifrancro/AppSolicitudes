import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Guarda el token de sesión en el almacenamiento cifrado del sistema.
///
/// En Android usa EncryptedSharedPreferences y en iOS el llavero, de modo que
/// el token no queda en texto plano dentro del dispositivo.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? almacenamiento})
      : _almacenamiento = almacenamiento ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

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
