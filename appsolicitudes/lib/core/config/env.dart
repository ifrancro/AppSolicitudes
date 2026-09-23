/// Configuración de entorno resuelta en tiempo de compilación.
///
/// El valor se inyecta con `--dart-define=API_BASE_URL=...`. El valor por
/// defecto apunta al emulador de Android, donde `10.0.2.2` es la dirección con
/// la que el emulador alcanza al host que ejecuta el backend Laravel.
class Env {
  const Env._();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  /// Tiempo máximo de espera para establecer la conexión y para recibir la
  /// respuesta completa.
  static const Duration timeout = Duration(seconds: 15);
}
