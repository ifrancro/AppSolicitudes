import 'package:appsolicitudes/core/storage/token_storage.dart';

/// Reemplaza el almacenamiento cifrado en las pruebas.
///
/// El paquete flutter_secure_storage necesita el canal de plataforma, que no
/// existe en `flutter test`, así que las pruebas inyectan esta versión en
/// memoria a través de `tokenStorageProvider`.
class TokenStorageFalso implements TokenStorage {
  TokenStorageFalso({String? tokenInicial}) : _token = tokenInicial;

  String? _token;

  /// Permite verificar en la prueba que el token se borró al cerrar sesión.
  bool get tieneToken => _token != null;

  @override
  Future<String?> leer() async => _token;

  @override
  Future<void> guardar(String token) async => _token = token;

  @override
  Future<void> borrar() async => _token = null;
}
